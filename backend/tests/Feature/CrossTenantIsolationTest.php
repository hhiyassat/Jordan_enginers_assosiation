<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Modules\JeaProjects\Models\Engineer;
use Modules\JeaProjects\Models\Project;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * P1-05 · Cross-tenant negative tests for the controllers that
 * previously relied only on the global org scope.
 *
 * Seeds two organizations (A + B), creates a resource under each,
 * signs in as an admin of Org B, and asserts that hitting Org A's
 * resource returns 404 (BelongsToOrganization scoping the query
 * to empty) or 403 (explicit ownership guard). Never 200 with the
 * foreign row.
 *
 * Controllers covered:
 *   - AdminDashboardController::allApplications  (list is org-filtered)
 *   - AdminDashboardController::auditLogs         (list is org-filtered)
 *   - ProjectController::show                    (owner_user_id + org)
 *   - ProjectController::index                    (list is org-filtered)
 *   - EngineerController::show                    (office_user_id + org)
 *   - EngineerController::index                    (list is org-filtered)
 *   - OfficeSettingsController::show/update       (org-scoped by findOrFail)
 *   - PaymentsController::confirm                 (org-scoped)
 *   - CertificatesController::issue               (org-scoped)
 *   - ReviewQueueController::index                (list is org-filtered)
 *
 * Coverage for UserManagementController / RecurringDuesController /
 * ComplaintController / LegalFineController / SupervisionTransferController
 * already lives in their dedicated feature tests (documented in
 * ledger); this file fills the gap identified in the review's
 * "TENANCY_TEST_GAPS" list without duplicating them.
 */
class CrossTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $adminB;
    private User $applicantA;
    private ServiceDefinition $serviceA;
    private Application $appA;
    private Project $projectA;
    private Engineer $engineerA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orgA = Organization::create(['name_ar' => 'a', 'name_en' => 'a', 'slug' => 'iso-a', 'is_active' => true]);
        $this->orgB = Organization::create(['name_ar' => 'b', 'name_en' => 'b', 'slug' => 'iso-b', 'is_active' => true]);
        $this->applicantA = $this->makeUser($this->orgA, 'applicant', 'iso-app-a@t.esp');
        $officeA          = $this->makeUser($this->orgA, 'applicant', 'iso-office-a@t.esp');
        $this->adminB     = $this->makeUser($this->orgB, 'admin',     'iso-admin-b@t.esp');

        $this->serviceA = ServiceDefinition::create([
            'organization_id' => $this->orgA->id, 'code' => 'ISO-SVC', 'name_ar' => 's', 'name_en' => 's',
            'currency' => 'JOD', 'status' => 'active', 'is_locked' => false,
            'schema' => [
                'workflow' => ['stages' => [
                    ['id' => 'r', 'role' => 'auditor', 'label_ar' => 'r', 'sla_hours' => 24, 'actions' => ['approve']],
                ]],
                'certificate' => ['validity_months' => 12, 'title_ar' => '.', 'title_en' => '.', 'fields_on_cert' => []],
                'fields' => [], 'documents' => [], 'sections' => [],
            ],
        ]);
        $this->appA = Application::create([
            'reference_number' => 'ISO-A', 'organization_id' => $this->orgA->id,
            'service_definition_id' => $this->serviceA->id, 'applicant_id' => $this->applicantA->id,
            'status' => Application::STATUS_APPROVED, 'current_stage' => 'r',
            'data' => [], 'fee_amount' => 0, 'payment_status' => 'waived',
        ]);
        $this->projectA = Project::create([
            'organization_id' => $this->orgA->id, 'owner_user_id' => $this->applicantA->id,
            'name_ar' => 'p', 'type' => 'سكني', 'area_m2' => 100, 'status' => 'active',
        ]);
        $this->engineerA = Engineer::create([
            'organization_id' => $this->orgA->id, 'office_user_id' => $officeA->id,
            'name_ar' => 'مهندس', 'name_en' => 'Engineer',
            'membership_number' => 'M-1', 'specialization' => 'مدني', 'is_active' => true,
        ]);
    }

    // ── AdminDashboardController (org-scoped list endpoints) ─────

    public function test_org_b_admin_dashboard_does_not_contain_org_a_applications(): void
    {
        Sanctum::actingAs($this->adminB);
        $res = $this->getJson('/api/v1/admin/applications');
        $res->assertOk();
        $ids = collect($res->json('applications.data', $res->json('data', [])))->pluck('id')->all();
        $this->assertNotContains($this->appA->id, $ids,
            'P1-05: Org B admin must not see any application row from Org A.');
    }

    public function test_org_b_admin_audit_logs_does_not_contain_org_a_rows(): void
    {
        // Seed an audit-log row belonging to Org A.
        \App\Models\AuditLog::record(
            user:    $this->applicantA,
            subject: $this->appA,
            action:  'iso.probe',
            extra:   ['rule_id' => 'ISO-01'],
        );
        Sanctum::actingAs($this->adminB);
        $res = $this->getJson('/api/v1/admin/audit-logs');
        $res->assertOk();
        $actions = collect($res->json('logs.data', $res->json('data', [])))->pluck('action')->all();
        $this->assertNotContains('iso.probe',
            $actions,
            'P1-05: Org B admin must not see Org A audit log rows.');
    }

    // ── ProjectController + EngineerController (show/index) ──────

    public function test_org_b_admin_cannot_read_org_a_project(): void
    {
        Sanctum::actingAs($this->adminB);
        // 404 because global scope filters projects to Org B (empty).
        $this->getJson("/api/v1/projects/{$this->projectA->id}")->assertNotFound();
    }

    public function test_org_b_admin_project_list_omits_org_a_projects(): void
    {
        Sanctum::actingAs($this->adminB);
        $res = $this->getJson('/api/v1/projects');
        $res->assertOk();
        $ids = collect($res->json('projects', $res->json('data', [])))->pluck('id')->all();
        $this->assertNotContains($this->projectA->id, $ids);
    }

    public function test_org_b_admin_cannot_read_org_a_engineer(): void
    {
        Sanctum::actingAs($this->adminB);
        $this->getJson("/api/v1/engineers/{$this->engineerA->id}")->assertNotFound();
    }

    public function test_org_b_admin_engineer_list_omits_org_a_engineers(): void
    {
        Sanctum::actingAs($this->adminB);
        $res = $this->getJson('/api/v1/engineers');
        $res->assertOk();
        $ids = collect($res->json('engineers', $res->json('data', [])))->pluck('id')->all();
        $this->assertNotContains($this->engineerA->id, $ids);
    }

    // ── OfficeSettingsController ─────────────────────────────────

    public function test_org_b_admin_cannot_read_org_a_office_settings(): void
    {
        Sanctum::actingAs($this->adminB);
        $this->getJson("/api/v1/admin/offices/{$this->applicantA->id}")->assertNotFound();
    }

    public function test_org_b_admin_cannot_update_org_a_office_settings(): void
    {
        Sanctum::actingAs($this->adminB);
        $this->patchJson("/api/v1/admin/offices/{$this->applicantA->id}", [
            'has_excellence_award' => true,
        ])->assertNotFound();
    }

    // ── PaymentsController + CertificatesController (mutation) ───

    public function test_org_b_admin_cannot_confirm_payment_on_org_a_application(): void
    {
        // CS-03: confirm-payment is now admin-only manual reconciliation
        // and requires a manual_reason. Even a valid admin in Org B must
        // hit the tenant boundary (404) before touching Org A's app.
        $adminB = $this->makeUser($this->orgB, 'admin', 'iso-admin-b-cs03@t.esp');
        Sanctum::actingAs($adminB);
        $this->postJson("/api/v1/applications/{$this->appA->id}/confirm-payment", [
            'payment_reference' => 'X-1',
            'manual_reason'     => 'cross-tenant isolation regression check',
        ])->assertNotFound();
    }

    public function test_org_b_staff_cannot_issue_certificate_on_org_a_application(): void
    {
        $staffB = $this->makeUser($this->orgB, 'staff', 'iso-staff-b2@t.esp');
        Sanctum::actingAs($staffB);
        $this->postJson("/api/v1/applications/{$this->appA->id}/issue-certificate")
            ->assertNotFound();
    }

    // ── ReviewQueueController ─────────────────────────────────────

    public function test_org_b_reviewer_queue_omits_org_a_applications(): void
    {
        $staffB = $this->makeUser($this->orgB, 'staff', 'iso-staff-b3@t.esp');
        // Move Org A's app to submitted so it would appear in a review queue.
        $this->appA->update(['status' => Application::STATUS_SUBMITTED]);
        Sanctum::actingAs($staffB);
        $res = $this->getJson('/api/v1/review/queue');
        $res->assertOk();
        $ids = collect($res->json('applications', $res->json('data', [])))->pluck('id')->all();
        $this->assertNotContains($this->appA->id, $ids,
            'P1-05: Reviewer queue must not surface applications from other orgs.');
    }

    private function makeUser(Organization $org, string $role, string $email): User
    {
        return User::create([
            'organization_id' => $org->id, 'name' => $role, 'email' => $email,
            'password' => Hash::make('Aa123456!Bcd'),
            'role' => $role, 'is_active' => true, 'password_changed_at' => now(),
        ]);
    }
}
