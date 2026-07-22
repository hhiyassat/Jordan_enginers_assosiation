<?php

namespace Tests\Feature;

use Modules\JeaProjects\Engine\Disciplines;
use Modules\JeaProjects\Engine\QuotaLedger;
use Modules\JeaServices\Engine\WorkflowEngine;
use Modules\JeaServices\Models\Application;
use Modules\JeaProjects\Models\Engineer;
use Modules\JeaProjects\Models\EngineerDisciplineQuota;
use Modules\JeaProjects\Models\OfficeCeiling;
use App\Models\Organization;
use Modules\JeaProjects\Models\QuotaConsumption;
use Modules\JeaServices\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JORD-68: consumption tracking. Pins:
 *   • Consumption row created on FINAL approval (not mid-workflow).
 *   • Idempotent — a decide() retry doesn't double the m² deduction.
 *   • Soft-deleting the application releases the quota.
 *   • Remaining-quota helpers reflect live state.
 *   • Missing engineer_id or area_m2 → silent skip (JORD-69 gate
 *     enforces at submit; this must not fail approval).
 */
class QuotaLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $officeUser;
    private User $reviewer;
    private Engineer $engineer;
    private ServiceDefinition $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->officeUser = User::create([
            'organization_id' => $this->org->id, 'name' => 'office', 'email' => 'office@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->reviewer = User::create([
            'organization_id' => $this->org->id, 'name' => 'r', 'email' => 'r@t.esp',
            'password' => 'x', 'role' => 'staff', 'is_active' => true, 'password_changed_at' => now(),
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
            'm2_allowed'  => 10000,
        ]);
        OfficeCeiling::create([
            'organization_id' => $this->org->id,
            'office_user_id'  => $this->officeUser->id,
            'discipline'      => Disciplines::ARCHITECTURAL,
            'year'            => (int) now()->year,
            'm2_allowed'      => 30000,
        ]);
        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'    => 'DRW-P-TEST',
            'name_ar' => 'test', 'name_en' => 'test',
            'currency'=> 'JOD',
            'schema'  => [
                'workflow' => ['stages' => [[
                    'id' => 'review', 'label_ar' => 'مراجعة', 'role' => 'staff',
                    'sla_hours' => 24, 'actions' => ['approve', 'reject'],
                ]]],
            ],
            'status' => 'active',
        ]);
    }

    public function test_record_approval_inserts_a_consumption_row(): void
    {
        $app = $this->makeApp(['engineer_id' => $this->engineer->id, 'area_m2' => 500]);
        app(QuotaLedger::class)->recordApproval($app);

        $rows = QuotaConsumption::where('application_id', $app->id)->get();
        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame($this->engineer->id, $row->engineer_id);
        $this->assertSame(Disciplines::ARCHITECTURAL, $row->discipline);
        $this->assertSame(500, $row->m2);
    }

    public function test_record_approval_is_idempotent(): void
    {
        // Composite unique (application_id, engineer_id, discipline)
        // enforces the DB-level guard; the ledger uses updateOrCreate
        // so the write itself is safe. Second call overwrites the m²
        // (if the app was updated between retries) — pinning that.
        $app = $this->makeApp(['engineer_id' => $this->engineer->id, 'area_m2' => 500]);
        app(QuotaLedger::class)->recordApproval($app);
        // Simulate a form edit + re-approval — m² changes.
        $app->data = ['engineer_id' => $this->engineer->id, 'area_m2' => 700];
        $app->save();
        app(QuotaLedger::class)->recordApproval($app);

        $this->assertSame(1, QuotaConsumption::count(),
            'Only one consumption row survives despite two recordApproval() calls');
        $this->assertSame(700, QuotaConsumption::first()->m2);
    }

    public function test_release_removes_consumption_on_soft_delete(): void
    {
        $app = $this->makeApp(['engineer_id' => $this->engineer->id, 'area_m2' => 500]);
        app(QuotaLedger::class)->recordApproval($app);
        $this->assertSame(1, QuotaConsumption::count());

        $app->delete(); // soft-delete triggers the booted() observer.
        $this->assertSame(0, QuotaConsumption::count(),
            'Soft-delete must release the quota');
    }

    public function test_missing_engineer_id_skips_consumption_silently(): void
    {
        // Approval must NOT fail if the data is missing — Phase 3 Tier A
        // hasn't shipped the submit-time gate yet (JORD-69), so
        // in-flight applications with old form shapes must still be
        // approvable without an exception.
        $app = $this->makeApp(['area_m2' => 500]); // no engineer_id
        app(QuotaLedger::class)->recordApproval($app);
        $this->assertSame(0, QuotaConsumption::count());
    }

    public function test_missing_area_skips_consumption_silently(): void
    {
        $app = $this->makeApp(['engineer_id' => $this->engineer->id]); // no area_m2
        app(QuotaLedger::class)->recordApproval($app);
        $this->assertSame(0, QuotaConsumption::count());
    }

    public function test_remaining_engineer_quota_reflects_consumption(): void
    {
        // 10,000 seeded, 500 consumed → 9,500 remaining.
        $app = $this->makeApp(['engineer_id' => $this->engineer->id, 'area_m2' => 500]);
        app(QuotaLedger::class)->recordApproval($app);

        $remaining = app(QuotaLedger::class)->remainingEngineerQuota(
            $this->engineer, Disciplines::ARCHITECTURAL, (int) now()->year,
        );
        $this->assertSame(9500, $remaining);
    }

    public function test_remaining_office_ceiling_reflects_consumption(): void
    {
        // 30,000 ceiling seeded, 500 consumed → 29,500 remaining.
        $app = $this->makeApp(['engineer_id' => $this->engineer->id, 'area_m2' => 500]);
        app(QuotaLedger::class)->recordApproval($app);

        $remaining = app(QuotaLedger::class)->remainingOfficeCeiling(
            $this->officeUser->id, Disciplines::ARCHITECTURAL, (int) now()->year,
        );
        $this->assertSame(29500, $remaining);
    }

    public function test_remaining_returns_null_when_no_quota_row_exists(): void
    {
        // Fresh org, no seeded quota — treat as "no cap configured"
        // (null) rather than 0. Otherwise the JORD-69 gate would reject
        // every submission from an org that hasn't been onboarded.
        $newEng = Engineer::create([
            'organization_id' => $this->org->id,
            'office_user_id'  => $this->officeUser->id,
            'name_ar' => 'م', 'membership_number' => 'EN-999',
            'specialization' => Disciplines::MECHANICAL,
        ]);
        $this->assertNull(app(QuotaLedger::class)->remainingEngineerQuota(
            $newEng, Disciplines::MECHANICAL, (int) now()->year,
        ));
    }

    public function test_workflow_engine_records_consumption_on_final_approve(): void
    {
        // End-to-end: use the real WorkflowEngine::decide() path so the
        // consumption hook is exercised through the actual approval
        // route, not a direct QuotaLedger call.
        $app = $this->makeApp(['engineer_id' => $this->engineer->id, 'area_m2' => 500]);
        // Bypass the multi-step path and land the app directly in
        // under_review — the review-and-approve transition is what
        // exercises the consumption hook, and we don't need to walk
        // the whole submit → claim → decide chain to test that.
        $app->update([
            'status' => Application::STATUS_UNDER_REVIEW,
            'current_stage' => 'review',
            'assigned_reviewer_id' => $this->reviewer->id,
        ]);
        (new WorkflowEngine($this->service))
            ->decide($app->fresh(), $this->reviewer, Application::STATUS_APPROVED);

        $this->assertSame(1, QuotaConsumption::where('application_id', $app->id)->count(),
            'The real WorkflowEngine::decide() path must trigger consumption');
    }

    private function makeApp(array $data): Application
    {
        return Application::create([
            'reference_number'      => strtoupper(bin2hex(random_bytes(4))),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $this->service->id,
            'applicant_id'          => $this->officeUser->id,
            'status'                => Application::STATUS_APPROVED,
            'data'                  => $data,
            'fee_amount'            => 0,
        ]);
    }
}
