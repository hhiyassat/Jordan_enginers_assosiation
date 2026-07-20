<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\FeeCalculator;
use App\Models\ServiceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * JORD-63: matrix fee type — 2-D lookup with optional bucket
 * collapse. Pins the exact math the JEA 2025 manual (p. 92) shipped
 * for governorate × building-class × area_m2.
 */
class FeeCalculatorMatrixTest extends TestCase
{
    private function svc(array $feeConfig, string $currency = 'JOD'): ServiceDefinition
    {
        $svc = new ServiceDefinition();
        $svc->setRawAttributes([
            'currency' => $currency,
            'schema'   => json_encode(['fee' => $feeConfig]),
        ]);
        $svc->syncOriginal();
        return $svc;
    }

    /** The exact rate table from manual p. 92 (design column). */
    private function ammanVsOtherFee(): array
    {
        return [
            'type'    => 'matrix',
            'keys'    => ['governorate', 'building_class'],
            'buckets' => [
                'governorate' => [
                    'amman'   => 'amman',
                    'irbid'   => 'other', 'zarqa' => 'other', 'mafraq' => 'other',
                    'balqa'   => 'other', 'karak' => 'other', 'maan'   => 'other',
                    'tafilah' => 'other', 'aqaba' => 'other', 'madaba' => 'other',
                    'jerash'  => 'other', 'ajloun' => 'other',
                ],
            ],
            'rates'   => [
                'amman|green_commercial' => 3.5, 'amman|private'         => 5.0,
                'amman|residential_cd'   => 2.5, 'amman|rural_shaabi'    => 2.0,
                'other|green_commercial' => 2.5, 'other|private'         => 4.0,
                'other|residential_cd'   => 2.0, 'other|rural_shaabi'    => 1.5,
            ],
            'basis'    => 'area_m2',
            'default'  => 0,
            'currency' => 'JOD',
        ];
    }

    public function test_amman_private_200m2_computes_1000_JOD(): void
    {
        // 5.0 JOD/m² (Amman private) × 200 m² = 1000.00.
        $calc = new FeeCalculator($this->svc($this->ammanVsOtherFee()));
        $result = $calc->calculate([
            'governorate'    => 'amman',
            'building_class' => 'private',
            'area_m2'        => 200,
        ]);
        $this->assertSame(1000.00, $result);
    }

    public function test_other_governorate_bucketizes_to_other_rate(): void
    {
        // Irbid → other bucket → 4.0 JOD/m² (private) × 150 = 600.00.
        $calc = new FeeCalculator($this->svc($this->ammanVsOtherFee()));
        $result = $calc->calculate([
            'governorate'    => 'irbid',
            'building_class' => 'private',
            'area_m2'        => 150,
        ]);
        $this->assertSame(600.00, $result);
    }

    public function test_every_manual_page_92_rate_matches_at_100m2(): void
    {
        // Iterates the full grid so a future edit that flips a single
        // rate gets caught by name. Expected = rate × 100.
        $calc = new FeeCalculator($this->svc($this->ammanVsOtherFee()));
        $expected = [
            ['amman', 'green_commercial', 350.00],
            ['amman', 'private',           500.00],
            ['amman', 'residential_cd',    250.00],
            ['amman', 'rural_shaabi',      200.00],
            ['irbid', 'green_commercial',  250.00],
            ['zarqa', 'private',           400.00],
            ['karak', 'residential_cd',    200.00],
            ['aqaba', 'rural_shaabi',      150.00],
        ];
        foreach ($expected as [$gov, $bldg, $want]) {
            $got = $calc->calculate([
                'governorate' => $gov, 'building_class' => $bldg, 'area_m2' => 100,
            ]);
            $this->assertSame($want, $got, "Rate for {$gov}/{$bldg} × 100 m² must be {$want} JOD");
        }
    }

    public function test_missing_governorate_falls_to_default(): void
    {
        // A submitted form must not silently produce a wrong bill if
        // one of the matrix keys is missing — better to fall to
        // default (which is 0 in the seeded table, i.e. zero fee
        // preview) than to guess a bucket.
        $calc = new FeeCalculator($this->svc($this->ammanVsOtherFee()));
        $this->assertSame(0.00, $calc->calculate([
            'building_class' => 'private', 'area_m2' => 200,
        ]));
    }

    public function test_unknown_building_class_falls_to_default(): void
    {
        // 'commercial_tower' isn't in the rates table — must not
        // silently pick a neighboring row.
        $calc = new FeeCalculator($this->svc($this->ammanVsOtherFee()));
        $this->assertSame(0.00, $calc->calculate([
            'governorate' => 'amman', 'building_class' => 'commercial_tower', 'area_m2' => 100,
        ]));
    }

    public function test_zero_area_yields_zero_fee(): void
    {
        // A draft or admin preview before entering area — legitimate
        // 0 fee, not an error.
        $calc = new FeeCalculator($this->svc($this->ammanVsOtherFee()));
        $this->assertSame(0.00, $calc->calculate([
            'governorate' => 'amman', 'building_class' => 'private', 'area_m2' => 0,
        ]));
    }

    public function test_negative_rate_authoring_bug_is_floored_at_zero(): void
    {
        // Same guard as the formula() branch — a negative rate × positive
        // area would produce a refund-shaped fee that certificate issuance
        // can't act on.
        $fee = $this->ammanVsOtherFee();
        $fee['rates']['amman|private'] = -5.0;
        $calc = new FeeCalculator($this->svc($fee));
        $this->assertSame(0.00, $calc->calculate([
            'governorate' => 'amman', 'building_class' => 'private', 'area_m2' => 100,
        ]));
    }

    public function test_matrix_without_buckets_uses_raw_values(): void
    {
        // Not every service needs bucketization — a simple 2-key matrix
        // (e.g. discipline × phase) can skip the buckets map and match
        // form values directly.
        $fee = [
            'type'    => 'matrix',
            'keys'    => ['discipline', 'phase'],
            'rates'   => ['architectural|1' => 300, 'structural|2' => 500],
            'basis'   => 'count',
            'default' => 0,
            'currency'=> 'JOD',
        ];
        $calc = new FeeCalculator($this->svc($fee));
        $this->assertSame(1500.00, $calc->calculate([
            'discipline' => 'structural', 'phase' => '2', 'count' => 3,
        ]));
    }

    public function test_missing_basis_field_yields_zero(): void
    {
        $calc = new FeeCalculator($this->svc($this->ammanVsOtherFee()));
        $this->assertSame(0.00, $calc->calculate([
            'governorate' => 'amman', 'building_class' => 'private',
        ]));
    }

    public function test_matrix_respects_currency_mismatch_guard(): void
    {
        // Existing invariant: currency mismatch aborts the calculation.
        // Matrix must inherit the guard from the parent calculate() flow.
        $fee = $this->ammanVsOtherFee();
        $fee['currency'] = 'USD';
        $this->expectException(\InvalidArgumentException::class);
        $calc = new FeeCalculator($this->svc($fee, 'JOD'));
        $calc->calculate(['governorate' => 'amman', 'building_class' => 'private', 'area_m2' => 100]);
    }
}
