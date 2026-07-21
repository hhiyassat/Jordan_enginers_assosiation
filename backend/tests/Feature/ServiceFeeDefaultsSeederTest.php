<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use Database\Seeders\ServiceFeeDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JORD-85 (partial F-07): ServiceFeeDefaultsSeeder replaces placeholder
 * `fixed 0` fee blocks with the admin default. Non-placeholder fees
 * (real per_unit, non-zero fixed, admin-set) stay untouched — the
 * seeder must be safely re-runnable without clobbering real data.
 */
class ServiceFeeDefaultsSeederTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
    }

    private function makeService(string $code, array $fee): ServiceDefinition
    {
        return ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'            => $code,
            'name_ar'         => $code,
            'name_en'         => $code,
            'status'          => 'draft',
            'is_locked'       => false,
            'schema'          => ['fee' => $fee],
        ]);
    }

    public function test_replaces_placeholder_fixed_zero_with_admin_default(): void
    {
        $svc = $this->makeService('MSC-001', ['type' => 'fixed', 'amount' => 0, 'currency' => 'JOD']);
        (new ServiceFeeDefaultsSeeder())->run();

        $fresh = $svc->fresh();
        $fee = $fresh->schema['fee'];
        $this->assertSame('fixed', $fee['type']);
        $this->assertEqualsWithDelta(ServiceFeeDefaultsSeeder::DEFAULT_AMOUNT_JOD, (float) $fee['amount'], 0.01);
        $this->assertSame('JOD', $fee['currency']);
        $this->assertStringContainsString('JORD-85', $fee['source']);
    }

    public function test_does_not_clobber_a_real_per_unit_fee(): void
    {
        $realFee = [
            'type' => 'per_unit', 'basis' => 'length_lm', 'rate' => 0.15, 'currency' => 'JOD',
        ];
        $svc = $this->makeService('SRV-001', $realFee);
        (new ServiceFeeDefaultsSeeder())->run();
        $this->assertSame($realFee, $svc->fresh()->schema['fee']);
    }

    public function test_does_not_clobber_a_real_nonzero_fixed_fee(): void
    {
        $realFee = ['type' => 'fixed', 'amount' => 30, 'currency' => 'JOD'];
        $svc = $this->makeService('DEMO-1', $realFee);
        (new ServiceFeeDefaultsSeeder())->run();
        // Amount stays 30, not 50000.
        $this->assertEqualsWithDelta(30, (float) $svc->fresh()->schema['fee']['amount'], 0.01);
    }

    public function test_is_idempotent_second_run_is_a_noop(): void
    {
        $svc = $this->makeService('MSC-002', ['type' => 'fixed', 'amount' => 0, 'currency' => 'JOD']);
        (new ServiceFeeDefaultsSeeder())->run();
        $firstSource = $svc->fresh()->schema['fee']['source'];
        (new ServiceFeeDefaultsSeeder())->run();
        // After first run, amount is 50000 → no longer "placeholder" →
        // second run skips it and source is unchanged.
        $this->assertSame($firstSource, $svc->fresh()->schema['fee']['source']);
    }
}
