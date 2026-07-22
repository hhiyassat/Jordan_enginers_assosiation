<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use Modules\JeaServices\Engine\FeeCalculator;
use Modules\JeaServices\Models\ServiceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * JORD-64: per_unit fee type — rate × form_value with optional
 * min/max caps. Pins the two rules the manual actually uses this
 * shape for:
 *   F-02  solar 4 JOD/kW  (uncapped)
 *   F-03  excavation review 500 fils/m² capped at 5000 JOD
 */
class FeeCalculatorPerUnitTest extends TestCase
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

    public function test_solar_4_JOD_per_kW_computes_500_JOD_at_125kW(): void
    {
        // Real seeded shape: SolarFeeSeeder writes exactly this.
        $calc = new FeeCalculator($this->svc([
            'type' => 'per_unit', 'basis' => 'capacity_kw', 'rate' => 4.0, 'currency' => 'JOD',
        ]));
        $this->assertSame(500.00, $calc->calculate(['capacity_kw' => 125]));
    }

    public function test_excavation_design_3_5_per_m2_computes_1750_JOD_at_500m2(): void
    {
        // ExcavationFeeSeeder shape.
        $calc = new FeeCalculator($this->svc([
            'type' => 'per_unit', 'basis' => 'area_m2', 'rate' => 3.5, 'currency' => 'JOD',
        ]));
        $this->assertSame(1750.00, $calc->calculate(['area_m2' => 500]));
    }

    public function test_upper_cap_clips_the_total(): void
    {
        // JEA p.40 review-committee fee: 500 fils/m² capped at 5000 JOD.
        // At 15,000 m² the uncapped math says 7,500 — cap wins.
        $calc = new FeeCalculator($this->svc([
            'type' => 'per_unit', 'basis' => 'area_m2', 'rate' => 0.5, 'max' => 5000, 'currency' => 'JOD',
        ]));
        $this->assertSame(5000.00, $calc->calculate(['area_m2' => 15000]));
    }

    public function test_upper_cap_does_not_touch_a_below_cap_value(): void
    {
        $calc = new FeeCalculator($this->svc([
            'type' => 'per_unit', 'basis' => 'area_m2', 'rate' => 0.5, 'max' => 5000, 'currency' => 'JOD',
        ]));
        // 100 m² × 0.5 = 50 — nowhere near the 5000 cap.
        $this->assertSame(50.00, $calc->calculate(['area_m2' => 100]));
    }

    public function test_lower_cap_lifts_a_below_min_value(): void
    {
        // A minimum-fee floor. 10 kW × 4.0 = 40, but service floor = 100.
        $calc = new FeeCalculator($this->svc([
            'type' => 'per_unit', 'basis' => 'capacity_kw', 'rate' => 4.0, 'min' => 100, 'currency' => 'JOD',
        ]));
        $this->assertSame(100.00, $calc->calculate(['capacity_kw' => 10]));
    }

    public function test_missing_basis_yields_zero(): void
    {
        // Applicant hasn't entered capacity yet — draft/preview, not
        // an error. Same policy as matrix() and formula().
        $calc = new FeeCalculator($this->svc([
            'type' => 'per_unit', 'basis' => 'capacity_kw', 'rate' => 4.0, 'currency' => 'JOD',
        ]));
        $this->assertSame(0.00, $calc->calculate([]));
    }

    public function test_non_numeric_basis_yields_zero(): void
    {
        // A malformed form (basis field is a string) must not throw
        // or coerce silently — just produce a zero the admin can see.
        $calc = new FeeCalculator($this->svc([
            'type' => 'per_unit', 'basis' => 'capacity_kw', 'rate' => 4.0, 'currency' => 'JOD',
        ]));
        $this->assertSame(0.00, $calc->calculate(['capacity_kw' => 'huge']));
    }

    public function test_negative_rate_is_floored_at_zero(): void
    {
        // Consistent with matrix() / formula() — a schema-authored
        // negative rate must not refund-shape the fee.
        $calc = new FeeCalculator($this->svc([
            'type' => 'per_unit', 'basis' => 'capacity_kw', 'rate' => -4.0, 'currency' => 'JOD',
        ]));
        $this->assertSame(0.00, $calc->calculate(['capacity_kw' => 100]));
    }

    public function test_zero_basis_yields_zero(): void
    {
        $calc = new FeeCalculator($this->svc([
            'type' => 'per_unit', 'basis' => 'area_m2', 'rate' => 3.5, 'currency' => 'JOD',
        ]));
        $this->assertSame(0.00, $calc->calculate(['area_m2' => 0]));
    }

    public function test_per_unit_respects_currency_mismatch_guard(): void
    {
        // Same invariant every fee type inherits from calculate().
        $this->expectException(\InvalidArgumentException::class);
        $calc = new FeeCalculator($this->svc([
            'type' => 'per_unit', 'basis' => 'capacity_kw', 'rate' => 4.0, 'currency' => 'USD',
        ], 'JOD'));
        $calc->calculate(['capacity_kw' => 10]);
    }
}
