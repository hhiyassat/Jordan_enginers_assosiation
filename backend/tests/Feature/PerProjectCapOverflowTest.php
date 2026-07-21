<?php

namespace Tests\Feature;

use App\Engine\Disciplines;
use App\Engine\QuotaLedger;
use App\Models\Application;
use App\Models\Engineer;
use App\Models\EngineerDisciplineQuota;
use App\Models\OfficeCeiling;
use App\Models\Organization;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-72: per-project cap + 25% overflow surcharge. Pins:
 *   • Under cap → no surcharge.
 *   • Over cap → 25% × excess × per-m² base rate → surcharge.
 *   • Null per_project_cap_m2 → pass-through (no cap configured).
 *   • Matrix fee → correct rate looked up from applicant's key values.
 *   • per_unit fee → the rate directly.
 *   • Fee types without a per-m² concept (fixed/tiered/formula) →
 *     no surcharge (conservative — better than an arbitrary guess).
 *   • End-to-end: the show endpoint returns the overflow surcharge
 *     inside fee_breakdown['surcharges'].
 */
class PerProjectCapOverflowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $officeUser;
    private Engineer $engineer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->officeUser = User::create([
            'organization_id' => $this->org->id, 'name' => 'o', 'email' => 'o@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->engineer = Engineer::create([
            'organization_id' => $this->org->id, 'office_user_id' => $this->officeUser->id,
            'name_ar' => 'م', 'membership_number' => 'EN-001',
            'specialization' => Disciplines::ARCHITECTURAL,
        ]);
        EngineerDisciplineQuota::create([
            'engineer_id' => $this->engineer->id,
            'discipline'  => Disciplines::ARCHITECTURAL,
            'year'        => (int) now()->year, 'm2_allowed' => 100000,
        ]);
        OfficeCeiling::create([
            'organization_id' => $this->org->id,
            'office_user_id'  => $this->officeUser->id,
            'discipline'      => Disciplines::ARCHITECTURAL,
            'year'            => (int) now()->year,
            'm2_allowed'      => 100000,
            'per_project_cap_m2' => 10000, // JEA p.129 consultant tier as example
        ]);
    }

    public function test_area_within_cap_yields_no_overflow(): void
    {
        $app = $this->makeApp('per_unit', ['engineer_id' => $this->engineer->id, 'area_m2' => 10000]);
        $this->assertNull(app(QuotaLedger::class)->overflowSurchargeFor($app));
    }

    public function test_per_unit_over_cap_computes_25_percent_of_excess_times_rate(): void
    {
        // 15,000 m² submitted, cap 10,000 → excess 5,000.
        // per_unit rate = 3.5 JOD/m² (excavation).
        // 25% × 5,000 × 3.5 = 4,375 JOD.
        $app = $this->makeApp('per_unit', ['engineer_id' => $this->engineer->id, 'area_m2' => 15000]);
        $surcharge = app(QuotaLedger::class)->overflowSurchargeFor($app);
        $this->assertNotNull($surcharge);
        $this->assertSame(4375.00, $surcharge['amount']);
        $this->assertSame('per_project_cap_overflow_25pct', $surcharge['code']);
        $this->assertStringContainsString('5000', $surcharge['label_ar']);
    }

    public function test_matrix_over_cap_looks_up_rate_from_applicant_keys(): void
    {
        // Applicant picked amman + private → 5.0 JOD/m². Cap 10,000, area 12,000.
        // Excess 2,000 × 5.0 × 0.25 = 2,500 JOD.
        $app = $this->makeApp('matrix', [
            'engineer_id'    => $this->engineer->id,
            'area_m2'        => 12000,
            'governorate'    => 'amman',
            'building_class' => 'private',
        ]);
        $surcharge = app(QuotaLedger::class)->overflowSurchargeFor($app);
        $this->assertNotNull($surcharge);
        $this->assertSame(2500.00, $surcharge['amount']);
    }

    public function test_null_per_project_cap_is_pass_through(): void
    {
        // Some tiers don't have a per-project cap — the null-cap
        // office must NEVER trigger the surcharge.
        OfficeCeiling::where('organization_id', $this->org->id)->update(['per_project_cap_m2' => null]);
        $app = $this->makeApp('per_unit', ['engineer_id' => $this->engineer->id, 'area_m2' => 999999]);
        $this->assertNull(app(QuotaLedger::class)->overflowSurchargeFor($app));
    }

    public function test_fixed_fee_service_yields_no_surcharge(): void
    {
        // No per-m² rate to decompose → conservatively no surcharge.
        // Better than an arbitrary "assume 1 JOD/m²" guess.
        $app = $this->makeApp('fixed', ['engineer_id' => $this->engineer->id, 'area_m2' => 20000]);
        $this->assertNull(app(QuotaLedger::class)->overflowSurchargeFor($app));
    }

    public function test_missing_engineer_id_yields_no_surcharge(): void
    {
        $app = $this->makeApp('per_unit', ['area_m2' => 15000]);
        $this->assertNull(app(QuotaLedger::class)->overflowSurchargeFor($app));
    }

    public function test_show_endpoint_appends_overflow_surcharge_to_breakdown(): void
    {
        $app = $this->makeApp('per_unit', ['engineer_id' => $this->engineer->id, 'area_m2' => 15000]);

        Sanctum::actingAs($this->officeUser);
        $res = $this->getJson("/api/v1/applications/{$app->id}");
        $res->assertOk();

        $surcharges = collect($res->json('fee_breakdown.surcharges'));
        $overflow = $surcharges->firstWhere('code', 'per_project_cap_overflow_25pct');
        $this->assertNotNull($overflow, 'Overflow surcharge must appear in the show payload');
        $this->assertEqualsWithDelta(4375.00, $overflow['amount'], 0.01);

        // Total must include the overflow.
        // per_unit base (15,000 × 3.5) + overflow 4,375 = 52,500 + 4,375 = 56,875.
        $this->assertEqualsWithDelta(56875.00, $res->json('fee_breakdown.total'), 0.01);
    }

    /**
     * Helper that creates an Application against a bespoke test service.
     * The service's schema.fee.type is switched per call so we can
     * exercise all three feeMap branches without dragging in
     * ServicePlan2026Seeder.
     */
    private function makeApp(string $feeType, array $data): Application
    {
        $fee = match ($feeType) {
            'per_unit' => [
                'type' => 'per_unit', 'basis' => 'area_m2', 'rate' => 3.5, 'currency' => 'JOD',
            ],
            'matrix' => [
                'type'    => 'matrix',
                'keys'    => ['governorate', 'building_class'],
                'buckets' => ['governorate' => ['amman' => 'amman', 'irbid' => 'other']],
                'rates'   => ['amman|private' => 5.0, 'other|private' => 4.0],
                'basis'   => 'area_m2',
                'default' => 0,
                'currency'=> 'JOD',
            ],
            default => ['type' => 'fixed', 'amount' => 100, 'currency' => 'JOD'],
        };
        $svc = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code' => 'DRW-P-TEST-' . strtoupper(bin2hex(random_bytes(2))),
            'name_ar' => 'test', 'name_en' => 'test', 'currency' => 'JOD',
            'schema' => [
                'fields' => [
                    ['id' => 'area_m2',    'label_ar' => 'م', 'type' => 'number', 'required' => true],
                    ['id' => 'engineer_id','label_ar' => 'م', 'type' => 'number', 'required' => true],
                    ['id' => 'governorate','label_ar' => 'م', 'type' => 'text'],
                    ['id' => 'building_class','label_ar' => 'م', 'type' => 'text'],
                ],
                'workflow' => ['stages' => [[
                    'id' => 'r', 'label_ar' => 'r', 'role' => 'staff', 'sla_hours' => 24,
                ]]],
                'fee' => $fee,
            ],
            'status' => 'active',
        ]);
        return Application::create([
            'reference_number'      => strtoupper(bin2hex(random_bytes(4))),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $svc->id,
            'applicant_id'          => $this->officeUser->id,
            'status'                => Application::STATUS_DRAFT,
            'data'                  => $data,
            'fee_amount'            => 0,
        ]);
    }
}
