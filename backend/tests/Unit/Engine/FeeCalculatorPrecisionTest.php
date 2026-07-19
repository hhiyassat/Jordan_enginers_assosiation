<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\FeeCalculator;
use App\Models\ServiceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * JORD-4: money math runs on bcmath decimal strings; PHP float precision
 * surprises don't leak through the public API. Currency mismatch on the
 * fee block aborts loudly.
 */
class FeeCalculatorPrecisionTest extends TestCase
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

    public function test_fixed_fee_returns_the_authored_amount_at_storage_precision(): void
    {
        $this->assertSame(150.00, (new FeeCalculator(
            $this->svc(['type' => 'fixed', 'amount' => 150])
        ))->calculate([]));
    }

    public function test_formula_computes_base_plus_rate_times_field_with_bcmath_precision(): void
    {
        // 100 + (0.1 × 3) — with floats you'd get 100.30000000000001; the
        // bcmath path lands exactly on 100.30 at storage scale.
        $fee = ['type' => 'formula', 'base' => 100, 'rate' => 0.1, 'field' => 'qty'];
        $this->assertSame(100.30, (new FeeCalculator($this->svc($fee)))->calculate(['qty' => 3]));
    }

    public function test_formula_result_never_goes_negative(): void
    {
        // Schema-authoring slip: negative rate × positive value would
        // produce a refund-shaped bill. Floor at 0 preserves the invariant.
        $fee = ['type' => 'formula', 'base' => 10, 'rate' => -5, 'field' => 'qty'];
        $this->assertSame(0.00, (new FeeCalculator($this->svc($fee)))->calculate(['qty' => 100]));
    }

    public function test_tiered_lookup_returns_the_matching_tier(): void
    {
        $fee = ['type' => 'tiered', 'field' => 'category', 'default' => 50, 'tiers' => [
            'commercial'  => 500,
            'residential' => 200,
        ]];
        $this->assertSame(500.00, (new FeeCalculator($this->svc($fee)))->calculate(['category' => 'commercial']));
        $this->assertSame(200.00, (new FeeCalculator($this->svc($fee)))->calculate(['category' => 'residential']));
    }

    public function test_tiered_lookup_falls_back_to_default_on_unknown_key(): void
    {
        $fee = ['type' => 'tiered', 'field' => 'category', 'default' => 50, 'tiers' => ['x' => 100]];
        $this->assertSame(50.00, (new FeeCalculator($this->svc($fee)))->calculate(['category' => 'unknown']));
    }

    public function test_currency_mismatch_between_fee_and_service_aborts(): void
    {
        // Schema-authoring bug: fee.currency='USD' on a service.currency='JOD'
        // would silently produce a wrong bill. Throw loudly so the admin
        // fixes the schema during authoring instead of after billing.
        $svc = $this->svc(['type' => 'fixed', 'amount' => 100, 'currency' => 'USD'], 'JOD');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/currency mismatch/i');
        (new FeeCalculator($svc))->calculate([]);
    }

    public function test_matching_currency_on_fee_block_is_accepted(): void
    {
        $svc = $this->svc(['type' => 'fixed', 'amount' => 100, 'currency' => 'jod'], 'JOD');
        // Case-insensitive comparison — jod ≡ JOD.
        $this->assertSame(100.00, (new FeeCalculator($svc))->calculate([]));
    }

    public function test_missing_fee_config_returns_zero(): void
    {
        $svc = new ServiceDefinition();
        $svc->setRawAttributes(['currency' => 'JOD', 'schema' => json_encode(['fee' => null])]);
        $svc->syncOriginal();
        $this->assertSame(0.0, (new FeeCalculator($svc))->calculate([]));
    }

    public function test_non_numeric_amount_coerces_to_zero_instead_of_crashing(): void
    {
        // Schema-authoring slip: amount is a nested array. Prior float
        // cast would silently produce 0.0 too, but with a PHP warning.
        // The bcmath path handles it explicitly.
        $svc = $this->svc(['type' => 'fixed', 'amount' => ['nested', 'garbage']]);
        $this->assertSame(0.0, (new FeeCalculator($svc))->calculate([]));
    }
}
