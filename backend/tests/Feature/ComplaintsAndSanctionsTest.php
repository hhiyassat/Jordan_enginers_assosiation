<?php

namespace Tests\Feature;

use App\Engine\SanctionGuard;
use App\Models\Application;
use App\Models\Complaint;
use App\Models\Organization;
use App\Models\Sanction;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-81: complaints + sanctions + submit gate.
 *
 * Pins the disciplinary spine end-to-end:
 *   • Intake: applicant files, cross-org guarded.
 *   • Investigation SLA: 30-day deadline persisted on intake.
 *   • Admin decide: creates a Sanction with correct effective_until
 *     from the ladder (1yr/2yr/permanent) OR dismisses.
 *   • SanctionGuard: an active blocking sanction produces 422 on submit;
 *     warnings never block; expired/future sanctions don't block.
 *   • Guardrails: complaint can't be decided twice; deregistration
 *     has no effective_until (permanent).
 */
class ComplaintsAndSanctionsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $reporter;
    private User $targetOffice;
    private ServiceDefinition $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->admin = User::create([
            'organization_id' => $this->org->id, 'name' => 'admin', 'email' => 'admin@t.esp',
            'password' => 'x', 'role' => 'admin', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->reporter = User::create([
            'organization_id' => $this->org->id, 'name' => 'rep', 'email' => 'rep@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->targetOffice = User::create([
            'organization_id' => $this->org->id, 'name' => 'Target', 'email' => 'target@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id, 'code' => 'CERT-TEST',
            'name_ar' => 't', 'name_en' => 't', 'currency' => 'JOD',
            'schema' => ['fields' => [], 'workflow' => ['stages' => [['id' => 'r', 'label_ar' => 'r', 'role' => 'staff', 'sla_hours' => 24]]]],
            'status' => 'active',
        ]);
    }

    public function test_complaint_intake_sets_30_day_investigation_deadline(): void
    {
        Sanctum::actingAs($this->reporter);
        $res = $this->postJson('/api/v1/complaints', [
            'target_office_user_id' => $this->targetOffice->id,
            'kind'                  => 'fee_undercutting',
            'description'           => 'المكتب المستهدف عرض أتعاب بأقل من الحد الأدنى للتنافس على مشروع.',
        ]);
        $res->assertCreated();
        $complaint = Complaint::first();
        $this->assertNotNull($complaint);
        $this->assertSame(Complaint::STATUS_OPEN, $complaint->status);
        // Compare dates only (diffInDays sub-day precision jitters at boundaries).
        $this->assertSame(
            now()->addDays(30)->toDateString(),
            $complaint->investigation_deadline->toDateString(),
        );
    }

    public function test_intake_refuses_cross_org_target(): void
    {
        // Reporter tries to file against an office in another org.
        $otherOrg = Organization::create([
            'name_ar' => 'other', 'name_en' => 'other', 'slug' => 'other', 'is_active' => true,
        ]);
        $foreignOffice = User::create([
            'organization_id' => $otherOrg->id, 'name' => 'x', 'email' => 'x@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        Sanctum::actingAs($this->reporter);
        $this->postJson('/api/v1/complaints', [
            'target_office_user_id' => $foreignOffice->id,
            'kind'                  => 'safety_violation',
            'description'           => 'aaaaaaaaaaaaaaaaaaaa need min 20 chars.',
        ])->assertNotFound();
        $this->assertSame(0, Complaint::count());
    }

    public function test_decide_sanction_1yr_suspension_creates_correct_effective_until(): void
    {
        $complaint = Complaint::create([
            'organization_id' => $this->org->id,
            'target_office_user_id' => $this->targetOffice->id,
            'reporter_user_id' => $this->reporter->id,
            'kind' => 'safety_violation', 'description' => 'x',
            'status' => Complaint::STATUS_OPEN,
            'investigation_deadline' => now()->addDays(30)->toDateString(),
        ]);

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/complaints/{$complaint->id}/decide", [
            'decision'      => 'sanction',
            'sanction_kind' => 'suspension_1yr',
            'reason'        => 'مخالفة تصنيف',
        ])->assertOk();

        $sanction = Sanction::first();
        $this->assertNotNull($sanction);
        $this->assertSame('suspension_1yr', $sanction->kind);
        // Compare date-only — sub-day precision jitters at boundaries.
        $this->assertSame(
            now()->addYear()->toDateString(),
            $sanction->effective_until->toDateString(),
        );
    }

    public function test_deregistration_sanction_is_permanent(): void
    {
        $complaint = Complaint::create([
            'organization_id' => $this->org->id,
            'target_office_user_id' => $this->targetOffice->id,
            'reporter_user_id' => $this->reporter->id,
            'kind' => 'safety_violation', 'description' => 'x',
            'status' => Complaint::STATUS_OPEN,
            'investigation_deadline' => now()->addDays(30)->toDateString(),
        ]);
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/complaints/{$complaint->id}/decide", [
            'decision' => 'sanction', 'sanction_kind' => 'deregistration', 'reason' => 'x',
        ])->assertOk();

        $sanction = Sanction::first();
        $this->assertNull($sanction->effective_until, 'Deregistration is permanent (no until date)');
    }

    public function test_decide_dismiss_creates_no_sanction(): void
    {
        $complaint = Complaint::create([
            'organization_id' => $this->org->id,
            'target_office_user_id' => $this->targetOffice->id,
            'reporter_user_id' => $this->reporter->id,
            'kind' => 'other', 'description' => 'x',
            'status' => Complaint::STATUS_OPEN,
            'investigation_deadline' => now()->addDays(30)->toDateString(),
        ]);
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/complaints/{$complaint->id}/decide", [
            'decision' => 'dismiss',
        ])->assertOk();

        $this->assertSame(Complaint::STATUS_DISMISSED, $complaint->fresh()->status);
        $this->assertSame(0, Sanction::count());
    }

    public function test_decide_twice_is_refused(): void
    {
        $complaint = Complaint::create([
            'organization_id' => $this->org->id,
            'target_office_user_id' => $this->targetOffice->id,
            'reporter_user_id' => $this->reporter->id,
            'kind' => 'other', 'description' => 'x',
            'status' => Complaint::STATUS_DISMISSED,
            'investigation_deadline' => now()->addDays(30)->toDateString(),
            'decided_at' => now(), 'decided_by_user_id' => $this->admin->id,
        ]);
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/complaints/{$complaint->id}/decide", [
            'decision' => 'dismiss',
        ])->assertStatus(422);
    }

    public function test_sanction_guard_blocks_submit_during_active_suspension(): void
    {
        // Active 1yr suspension → submission by that office → 422.
        Sanction::create([
            'organization_id' => $this->org->id,
            'office_user_id'  => $this->targetOffice->id,
            'kind'            => Sanction::KIND_SUSPENSION_1YR,
            'effective_from'  => now()->subMonth()->toDateString(),
            'effective_until' => now()->addMonths(11)->toDateString(),
            'reason'          => 'test',
            'issued_by_user_id' => $this->admin->id,
        ]);
        $app = Application::create([
            'reference_number' => strtoupper(bin2hex(random_bytes(4))),
            'organization_id' => $this->org->id,
            'service_definition_id' => $this->service->id,
            'applicant_id' => $this->targetOffice->id,
            'status' => Application::STATUS_DRAFT,
            'data' => [], 'fee_amount' => 0,
        ]);

        Sanctum::actingAs($this->targetOffice);
        $res = $this->postJson("/api/v1/applications/{$app->id}/submit");
        $res->assertStatus(422);
        $res->assertJsonPath('errors.sanction', fn ($msg) => str_contains($msg, 'إيقاف'));
    }

    public function test_sanction_guard_ignores_warning_kind(): void
    {
        // Warnings never block. Office should submit fine.
        Sanction::create([
            'organization_id' => $this->org->id,
            'office_user_id'  => $this->targetOffice->id,
            'kind'            => Sanction::KIND_WARNING,
            'effective_from'  => now()->subDay()->toDateString(),
            'effective_until' => now()->toDateString(),
            'reason'          => 'test',
            'issued_by_user_id' => $this->admin->id,
        ]);
        $app = Application::create([
            'reference_number' => strtoupper(bin2hex(random_bytes(4))),
            'organization_id' => $this->org->id,
            'service_definition_id' => $this->service->id,
            'applicant_id' => $this->targetOffice->id,
            'status' => Application::STATUS_DRAFT,
            'data' => [], 'fee_amount' => 0,
        ]);
        $this->assertSame([], app(SanctionGuard::class)->validate($app));
    }

    public function test_sanction_guard_ignores_expired_suspension(): void
    {
        Sanction::create([
            'organization_id' => $this->org->id,
            'office_user_id'  => $this->targetOffice->id,
            'kind'            => Sanction::KIND_SUSPENSION_1YR,
            'effective_from'  => now()->subYears(2)->toDateString(),
            'effective_until' => now()->subYear()->toDateString(), // ended a year ago
            'reason'          => 'old',
            'issued_by_user_id' => $this->admin->id,
        ]);
        $app = Application::create([
            'reference_number' => strtoupper(bin2hex(random_bytes(4))),
            'organization_id' => $this->org->id,
            'service_definition_id' => $this->service->id,
            'applicant_id' => $this->targetOffice->id,
            'status' => Application::STATUS_DRAFT,
            'data' => [], 'fee_amount' => 0,
        ]);
        $this->assertSame([], app(SanctionGuard::class)->validate($app));
    }

    public function test_admin_index_returns_org_complaints_only(): void
    {
        Complaint::create([
            'organization_id' => $this->org->id,
            'target_office_user_id' => $this->targetOffice->id,
            'reporter_user_id' => $this->reporter->id,
            'kind' => 'other', 'description' => 'x',
            'status' => Complaint::STATUS_OPEN,
            'investigation_deadline' => now()->addDays(30)->toDateString(),
        ]);
        // Other-org complaint MUST NOT leak.
        $otherOrg = Organization::create([
            'name_ar' => 'x', 'name_en' => 'x', 'slug' => 'x', 'is_active' => true,
        ]);
        $otherUser = User::create([
            'organization_id' => $otherOrg->id, 'name' => 'x', 'email' => 'other@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        Complaint::create([
            'organization_id' => $otherOrg->id,
            'target_office_user_id' => $otherUser->id,
            'reporter_user_id' => $otherUser->id,
            'kind' => 'other', 'description' => 'y',
            'status' => Complaint::STATUS_OPEN,
            'investigation_deadline' => now()->addDays(30)->toDateString(),
        ]);

        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/admin/complaints');
        $res->assertOk();
        $this->assertCount(1, $res->json('complaints'));
    }
}
