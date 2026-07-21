<?php

namespace Tests\Feature;

use App\Engine\Disciplines;
use App\Engine\QuotaLedger;
use App\Models\Application;
use App\Models\Engineer;
use App\Models\EngineerDisciplineQuota;
use App\Models\OfficeCeiling;
use App\Models\Organization;
use App\Models\QuotaConsumption;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JORD-71: governorate-scoped 90% → +10% overflow (JEA p.127).
 *
 * Pins:
 *   • <90% consumption in a governorate → no overflow.
 *   • ≥90% consumption in gov X → +10% ONLY when the guard is asked
 *     about gov X. Asking about gov Y still yields base ceiling.
 *   • Overflow stacks additively with JORD-70 boosts (not
 *     multiplicatively) — 1.15 base × 1.10 overflow would be 1.265
 *     but our contract says 1.25 (0.15 + 0.10 additive).
 *   • Governorate column persists on recordApproval() from app.data.
 *   • Ceiling helper without governorate param → no overflow branch fires.
 */
class GovernorateOverflowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $officeUser;
    private Engineer $engineer;
    private ServiceDefinition $service;

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
            'year'        => (int) now()->year,
            'm2_allowed'  => 100000,
        ]);
        OfficeCeiling::create([
            'organization_id' => $this->org->id,
            'office_user_id'  => $this->officeUser->id,
            'discipline'      => Disciplines::ARCHITECTURAL,
            'year'            => (int) now()->year,
            'm2_allowed'      => 10000,
        ]);
        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id, 'code' => 'DRW-P-TEST',
            'name_ar' => 't', 'name_en' => 't', 'currency' => 'JOD',
            'schema' => ['fields' => [], 'workflow' => ['stages' => []]],
        ]);
    }

    public function test_under_90_percent_yields_no_overflow(): void
    {
        // 10,000 ceiling × 0.80 = 8,000 in Amman. Trigger is at 9,000.
        $this->consume('amman', 8000);
        $rem = app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->officeUser->id, Disciplines::ARCHITECTURAL, (int) now()->year, 'amman',
        );
        // 10,000 base - 8,000 consumed = 2,000.
        $this->assertSame(2000, $rem);
    }

    public function test_at_90_percent_grants_plus_10_percent_ceiling(): void
    {
        // 9,000 consumed in Amman → 9,000 >= ceil(10,000 × 0.90).
        // Effective = 10,000 × 1.10 = 11,000. Remaining = 11,000 - 9,000 = 2,000.
        $this->consume('amman', 9000);
        $rem = app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->officeUser->id, Disciplines::ARCHITECTURAL, (int) now()->year, 'amman',
        );
        $this->assertSame(2000, $rem);
    }

    public function test_overflow_only_scopes_to_the_triggering_governorate(): void
    {
        // Consumption is entirely in Amman → Amman triggers overflow.
        // Irbid must NOT benefit — asking about Irbid returns base ceiling.
        $this->consume('amman', 9000);

        $ammanRem = app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->officeUser->id, Disciplines::ARCHITECTURAL, (int) now()->year, 'amman',
        );
        $irbidRem = app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->officeUser->id, Disciplines::ARCHITECTURAL, (int) now()->year, 'irbid',
        );

        // Amman: 11,000 - 9,000 = 2,000.
        $this->assertSame(2000, $ammanRem);
        // Irbid: base 10,000 - 9,000 already-consumed (any governorate) = 1,000.
        // No overflow because Irbid consumption is 0 < 90% of ceiling.
        $this->assertSame(1000, $irbidRem);
    }

    public function test_null_governorate_does_not_apply_overflow(): void
    {
        // Legacy caller not passing governorate → no overflow branch.
        // Same behavior as pre-JORD-71.
        $this->consume('amman', 9000);
        $rem = app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->officeUser->id, Disciplines::ARCHITECTURAL, (int) now()->year, null,
        );
        // 10,000 - 9,000 = 1,000 (no overflow).
        $this->assertSame(1000, $rem);
    }

    public function test_overflow_stacks_additively_with_JORD_70_boosts(): void
    {
        // Boosted ceiling: 10,000 × 1.15 = 11,500. Trigger at 90% × 11,500 = 10,350.
        // Consume 10,350 in Amman → overflow adds +10 percentage points.
        // Effective = 10,000 × (1.15 + 0.10) = 12,500.
        // Remaining = 12,500 - 10,350 = 2,150.
        $this->officeUser->update([
            'has_excellence_award' => true, 'is_bit_khibra' => true, 'has_iso_cert' => true,
        ]);
        $this->consume('amman', 10350);
        $rem = app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->officeUser->id, Disciplines::ARCHITECTURAL, (int) now()->year, 'amman',
        );
        $this->assertSame(2150, $rem);
    }

    public function test_record_approval_persists_governorate_from_form_data(): void
    {
        // recordApproval() reads app.data.governorate and stores it on
        // the consumption row so later 90% checks can filter.
        $app = Application::create([
            'reference_number'      => 'X', 'organization_id' => $this->org->id,
            'service_definition_id' => $this->service->id,
            'applicant_id'          => $this->officeUser->id,
            'status'                => Application::STATUS_APPROVED,
            'data'                  => [
                'engineer_id' => $this->engineer->id, 'area_m2' => 500,
                'governorate' => 'irbid',
            ],
            'fee_amount' => 0,
        ]);
        app(QuotaLedger::class)->recordApproval($app);

        $row = QuotaConsumption::where('application_id', $app->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('irbid', $row->governorate);
    }

    private function consume(string $governorate, int $m2): void
    {
        // Create a distinct application per consumption row (composite
        // unique on quota_consumptions is (app_id, engineer_id, discipline)).
        $app = Application::create([
            'reference_number'      => strtoupper(bin2hex(random_bytes(4))),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $this->service->id,
            'applicant_id'          => $this->officeUser->id,
            'status'                => Application::STATUS_APPROVED,
            'data'                  => ['governorate' => $governorate],
            'fee_amount'            => 0,
        ]);
        QuotaConsumption::create([
            'application_id'  => $app->id,
            'engineer_id'     => $this->engineer->id,
            'organization_id' => $this->org->id,
            'office_user_id'  => $this->officeUser->id,
            'discipline'      => Disciplines::ARCHITECTURAL,
            'year'            => (int) now()->year,
            'm2'              => $m2,
            'governorate'     => $governorate,
        ]);
    }
}
