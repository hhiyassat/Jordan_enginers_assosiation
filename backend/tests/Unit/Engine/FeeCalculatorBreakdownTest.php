<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\FeeCalculator;
use App\Models\ServiceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * JORD-65: itemized fee breakdown. Pins:
 *   • The old calculate() total exactly matches
 *     calculateBreakdown()['total'] (backwards compat).
 *   • percent_of_base and per_unit surcharges are added on top of
 *     the base per the JEA p.96 rules.
 *   • Unknown kinds are silently skipped (safe fallback).
 */
class FeeCalculatorBreakdownTest extends TestCase
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

    public function test_no_surcharges_returns_base_as_total(): void
    {
        // Backwards-compat: a service without surcharges gets
        // total = base and empty surcharges[]. Old calculate() callers
        // see the same number they always did.
        $calc = new FeeCalculator($this->svc([
            'type' => 'fixed', 'amount' => 100.0, 'currency' => 'JOD',
        ]));
        $breakdown = $calc->calculateBreakdown([]);
        $this->assertSame(100.00, $breakdown['base']);
        $this->assertSame(100.00, $breakdown['total']);
        $this->assertSame([], $breakdown['surcharges']);
        $this->assertSame(100.00, $calc->calculate([])); // old API unchanged
    }

    public function test_1_percent_syndicate_surcharge_on_a_500_JOD_base_adds_5(): void
    {
        // JEA p.96 syndicate fee: 1% of base.
        $calc = new FeeCalculator($this->svc([
            'type' => 'fixed', 'amount' => 500.0, 'currency' => 'JOD',
            'surcharges' => [[
                'code'     => 'syndicate_1pct',
                'kind'     => 'percent_of_base',
                'rate'     => 0.01,
                'label_ar' => 'رسم النقابة',
                'label_en' => 'Syndicate Fee',
            ]],
        ]));
        $breakdown = $calc->calculateBreakdown([]);
        $this->assertSame(500.00, $breakdown['base']);
        $this->assertCount(1, $breakdown['surcharges']);
        $this->assertSame(5.00, $breakdown['surcharges'][0]['amount']);
        $this->assertSame(505.00, $breakdown['total']);
    }

    public function test_per_unit_surcharge_uses_form_field_for_amount(): void
    {
        // JEA p.96 drawing-review: 40 fils/m² × area.
        // 200 m² × 0.04 = 8.00 JOD.
        $calc = new FeeCalculator($this->svc([
            'type' => 'fixed', 'amount' => 500.0, 'currency' => 'JOD',
            'surcharges' => [[
                'code'  => 'drawing_review_40fils',
                'kind'  => 'per_unit',
                'basis' => 'area_m2',
                'rate'  => 0.04,
                'label_ar' => 'رسم تدقيق المخططات',
                'label_en' => 'Drawing Review',
            ]],
        ]));
        $breakdown = $calc->calculateBreakdown(['area_m2' => 200]);
        $this->assertSame(500.00, $breakdown['base']);
        $this->assertSame(8.00, $breakdown['surcharges'][0]['amount']);
        $this->assertSame(508.00, $breakdown['total']);
    }

    public function test_multiple_surcharges_all_add_to_the_total(): void
    {
        // Realistic drawings shape: matrix base 1000 + 1% syndicate + 40 fils/m² review.
        // base = Amman|private × 200 m² = 5.0 × 200 = 1000.
        // syndicate = 1000 × 0.01 = 10.
        // review = 200 × 0.04 = 8.
        // total = 1018.
        $calc = new FeeCalculator($this->svc([
            'type'    => 'matrix',
            'keys'    => ['governorate', 'building_class'],
            'rates'   => ['amman|private' => 5.0],
            'basis'   => 'area_m2',
            'default' => 0,
            'currency'=> 'JOD',
            'surcharges' => [
                ['code' => 'syndicate_1pct', 'kind' => 'percent_of_base', 'rate' => 0.01,
                 'label_ar' => '', 'label_en' => ''],
                ['code' => 'drawing_review_40fils', 'kind' => 'per_unit',
                 'basis' => 'area_m2', 'rate' => 0.04, 'label_ar' => '', 'label_en' => ''],
            ],
        ]));
        $breakdown = $calc->calculateBreakdown([
            'governorate' => 'amman', 'building_class' => 'private', 'area_m2' => 200,
        ]);
        $this->assertSame(1000.00, $breakdown['base']);
        $this->assertCount(2, $breakdown['surcharges']);
        $this->assertSame(10.00, $breakdown['surcharges'][0]['amount']);
        $this->assertSame(8.00,  $breakdown['surcharges'][1]['amount']);
        $this->assertSame(1018.00, $breakdown['total']);
    }

    public function test_unknown_surcharge_kind_is_silently_skipped(): void
    {
        // Defensive: if a schema is hand-patched with a kind the engine
        // doesn't recognize, don't 500 — just skip that line item.
        // Schema validator refuses these at save time; this branch is
        // for out-of-band manipulation only.
        $calc = new FeeCalculator($this->svc([
            'type' => 'fixed', 'amount' => 100.0, 'currency' => 'JOD',
            'surcharges' => [
                ['kind' => 'percent_of_base', 'rate' => 0.01, 'code' => 'ok',
                 'label_ar' => '', 'label_en' => ''],
                ['kind' => 'garbage_kind', 'rate' => 999.9, 'code' => 'skip',
                 'label_ar' => '', 'label_en' => ''],
            ],
        ]));
        $breakdown = $calc->calculateBreakdown([]);
        $this->assertCount(1, $breakdown['surcharges'], 'Only the valid entry should render');
        $this->assertSame(101.00, $breakdown['total']);
    }

    public function test_breakdown_carries_the_service_currency(): void
    {
        $calc = new FeeCalculator($this->svc([
            'type' => 'fixed', 'amount' => 50.0, 'currency' => 'JOD',
        ], 'JOD'));
        $this->assertSame('JOD', $calc->calculateBreakdown([])['currency']);
    }

    public function test_empty_fee_config_yields_zero_breakdown_shape(): void
    {
        // Service with no fee config at all — must still return the
        // full shape (empty arrays and 0s) so the frontend can render
        // "لا توجد رسوم" instead of crashing on a null field.
        $svc = new ServiceDefinition();
        $svc->setRawAttributes(['currency' => 'JOD', 'schema' => json_encode([])]);
        $svc->syncOriginal();
        $breakdown = (new FeeCalculator($svc))->calculateBreakdown([]);
        $this->assertSame(0.0, $breakdown['base']);
        $this->assertSame(0.0, $breakdown['total']);
        $this->assertSame([], $breakdown['surcharges']);
        $this->assertSame('JOD', $breakdown['currency']);
    }
}
