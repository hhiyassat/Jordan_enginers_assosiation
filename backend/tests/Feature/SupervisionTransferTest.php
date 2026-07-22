<?php

namespace Tests\Feature;

use Modules\JeaServices\Models\Application;
use Modules\JeaDiscipline\Models\Complaint;
use App\Models\Organization;
use Modules\JeaDiscipline\Models\Sanction;
use Modules\JeaServices\Models\ServiceDefinition;
use Modules\JeaDiscipline\Models\SupervisionTransfer;
use App\Models\User;
use Modules\JeaDiscipline\Services\SupervisionTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-83: supervision transfer queue per JEA manual p.30 (C-07).
 *
 * Pins:
 *   • suspension_2yr / deregistration → auto-open transfer rows
 *     for every APPROVED JEA-PROJ app the sanctioned office holds.
 *   • warning + suspension_1yr do NOT trigger — supervision can
 *     pause for a year; long/permanent suspensions can't.
 *   • Only approved apps count — draft/submitted have no supervision
 *     yet; certificate_issued is terminal.
 *   • Only JEA-PROJ apps count — CERT-* / MSC-* have no supervision.
 *   • fee_waived defaults to true per manual (free tier for takeover).
 *   • Admin flow: pending → assigned → accepted (or declined → back
 *     to pending for reassignment).
 */
class SupervisionTransferTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $sourceOffice;
    private User $receivingOffice;
    private ServiceDefinition $drawing;
    private ServiceDefinition $cert;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->admin = User::create([
            'organization_id' => $this->org->id, 'name' => 'admin', 'email' => 'a@t.esp',
            'password' => 'x', 'role' => 'admin', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->sourceOffice = User::create([
            'organization_id' => $this->org->id, 'name' => 'Source', 'email' => 'src@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->receivingOffice = User::create([
            'organization_id' => $this->org->id, 'name' => 'Receiver', 'email' => 'rcv@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->drawing = ServiceDefinition::create([
            'organization_id' => $this->org->id, 'code' => 'DRW-P-TEST',
            'parent_code' => 'JEA-PROJ',
            'name_ar' => 'd', 'name_en' => 'd', 'currency' => 'JOD',
            'schema' => ['fields' => [], 'workflow' => ['stages' => [['id'=>'r','label_ar'=>'r','role'=>'staff','sla_hours'=>24]]]],
            'status' => 'active',
        ]);
        $this->cert = ServiceDefinition::create([
            'organization_id' => $this->org->id, 'code' => 'CERT-TEST',
            'parent_code' => 'JEA-CERT',
            'name_ar' => 'c', 'name_en' => 'c', 'currency' => 'JOD',
            'schema' => ['fields' => [], 'workflow' => ['stages' => [['id'=>'r','label_ar'=>'r','role'=>'staff','sla_hours'=>24]]]],
            'status' => 'active',
        ]);
    }

    public function test_deregistration_opens_transfer_for_every_approved_drawing_app(): void
    {
        $this->approvedApp($this->drawing);
        $this->approvedApp($this->drawing);
        $this->approvedApp($this->drawing);
        $sanction = $this->sanctionFor(Sanction::KIND_DEREGISTRATION);

        $count = app(SupervisionTransferService::class)->openTransfersFor($this->sourceOffice, $sanction);
        $this->assertSame(3, $count);
        $this->assertSame(3, SupervisionTransfer::count());
        $this->assertTrue(SupervisionTransfer::first()->fee_waived,
            'C-07: fee waived by default for the receiving office');
    }

    public function test_suspension_2yr_also_opens_transfers(): void
    {
        $this->approvedApp($this->drawing);
        $sanction = $this->sanctionFor(Sanction::KIND_SUSPENSION_2YR);

        $count = app(SupervisionTransferService::class)->openTransfersFor($this->sourceOffice, $sanction);
        $this->assertSame(1, $count);
    }

    public function test_suspension_1yr_does_not_trigger_transfers(): void
    {
        $this->approvedApp($this->drawing);
        $sanction = $this->sanctionFor(Sanction::KIND_SUSPENSION_1YR);

        $count = app(SupervisionTransferService::class)->openTransfersFor($this->sourceOffice, $sanction);
        $this->assertSame(0, $count, 'A 1-year suspension pauses; supervision resumes after — no transfer');
    }

    public function test_warning_does_not_trigger_transfers(): void
    {
        $this->approvedApp($this->drawing);
        $sanction = $this->sanctionFor(Sanction::KIND_WARNING);
        $this->assertSame(0, app(SupervisionTransferService::class)->openTransfersFor($this->sourceOffice, $sanction));
    }

    public function test_only_approved_apps_are_flagged(): void
    {
        // Draft + submitted + cert_issued → NOT flagged. Only approved.
        $this->approvedApp($this->drawing);                          // → transfer
        $this->appWithStatus($this->drawing, Application::STATUS_DRAFT);
        $this->appWithStatus($this->drawing, Application::STATUS_SUBMITTED);
        $this->appWithStatus($this->drawing, Application::STATUS_CERTIFICATE_ISSUED);
        $sanction = $this->sanctionFor(Sanction::KIND_DEREGISTRATION);

        $count = app(SupervisionTransferService::class)->openTransfersFor($this->sourceOffice, $sanction);
        $this->assertSame(1, $count);
    }

    public function test_only_jea_proj_apps_are_flagged(): void
    {
        // CERT-* has no supervision — must not appear in transfer queue.
        $this->approvedApp($this->drawing);   // in scope
        $this->approvedApp($this->cert);      // out of scope

        $sanction = $this->sanctionFor(Sanction::KIND_DEREGISTRATION);
        $this->assertSame(1, app(SupervisionTransferService::class)->openTransfersFor($this->sourceOffice, $sanction));
    }

    public function test_service_is_idempotent(): void
    {
        $this->approvedApp($this->drawing);
        $sanction = $this->sanctionFor(Sanction::KIND_DEREGISTRATION);
        app(SupervisionTransferService::class)->openTransfersFor($this->sourceOffice, $sanction);
        app(SupervisionTransferService::class)->openTransfersFor($this->sourceOffice, $sanction);
        $this->assertSame(1, SupervisionTransfer::count(),
            'application_id unique constraint prevents double-open');
    }

    public function test_complaint_decide_triggers_transfer_flow_end_to_end(): void
    {
        $this->approvedApp($this->drawing);
        $this->approvedApp($this->drawing);
        $complaint = Complaint::create([
            'organization_id' => $this->org->id,
            'target_office_user_id' => $this->sourceOffice->id,
            'reporter_user_id' => $this->admin->id,
            'kind' => 'safety_violation', 'description' => 'x',
            'status' => Complaint::STATUS_OPEN,
            'investigation_deadline' => now()->addDays(30)->toDateString(),
        ]);

        Sanctum::actingAs($this->admin);
        $res = $this->postJson("/api/v1/admin/complaints/{$complaint->id}/decide", [
            'decision' => 'sanction',
            'sanction_kind' => 'deregistration',
            'reason' => 'مخالفة خطيرة',
        ])->assertOk();

        $this->assertSame(2, $res->json('transfers_opened'));
        $this->assertStringContainsString('2 طلب نقل', $res->json('message'));
        $this->assertSame(2, SupervisionTransfer::count());
    }

    public function test_admin_assigns_target_office_moving_pending_to_assigned(): void
    {
        $app = $this->approvedApp($this->drawing);
        $transfer = SupervisionTransfer::create([
            'organization_id' => $this->org->id,
            'application_id' => $app->id,
            'source_office_user_id' => $this->sourceOffice->id,
            'status' => SupervisionTransfer::STATUS_PENDING,
        ]);
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/supervision-transfers/{$transfer->id}/assign", [
            'target_office_user_id' => $this->receivingOffice->id,
        ])->assertOk();

        $fresh = $transfer->fresh();
        $this->assertSame(SupervisionTransfer::STATUS_ASSIGNED, $fresh->status);
        $this->assertSame($this->receivingOffice->id, $fresh->target_office_user_id);
        $this->assertNotNull($fresh->assigned_at);
    }

    public function test_assign_refuses_source_office_as_target(): void
    {
        // Source office trying to take back its own transfer is nonsense.
        $app = $this->approvedApp($this->drawing);
        $transfer = SupervisionTransfer::create([
            'organization_id' => $this->org->id, 'application_id' => $app->id,
            'source_office_user_id' => $this->sourceOffice->id,
            'status' => SupervisionTransfer::STATUS_PENDING,
        ]);
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/supervision-transfers/{$transfer->id}/assign", [
            'target_office_user_id' => $this->sourceOffice->id,
        ])->assertStatus(422);
    }

    public function test_accept_moves_assigned_to_accepted(): void
    {
        $app = $this->approvedApp($this->drawing);
        $transfer = SupervisionTransfer::create([
            'organization_id' => $this->org->id, 'application_id' => $app->id,
            'source_office_user_id' => $this->sourceOffice->id,
            'target_office_user_id' => $this->receivingOffice->id,
            'status' => SupervisionTransfer::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/supervision-transfers/{$transfer->id}/accept-decline", [
            'outcome' => 'accept',
        ])->assertOk();
        $this->assertSame(SupervisionTransfer::STATUS_ACCEPTED, $transfer->fresh()->status);
        $this->assertNotNull($transfer->fresh()->accepted_at);
    }

    public function test_decline_returns_row_to_pending_and_clears_target(): void
    {
        $app = $this->approvedApp($this->drawing);
        $transfer = SupervisionTransfer::create([
            'organization_id' => $this->org->id, 'application_id' => $app->id,
            'source_office_user_id' => $this->sourceOffice->id,
            'target_office_user_id' => $this->receivingOffice->id,
            'status' => SupervisionTransfer::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/supervision-transfers/{$transfer->id}/accept-decline", [
            'outcome' => 'decline',
        ])->assertOk();

        $fresh = $transfer->fresh();
        $this->assertSame(SupervisionTransfer::STATUS_DECLINED, $fresh->status);
        $this->assertNull($fresh->target_office_user_id);
        $this->assertNull($fresh->assigned_at);
    }

    public function test_index_endpoint_filters_by_status(): void
    {
        $app1 = $this->approvedApp($this->drawing);
        $app2 = $this->approvedApp($this->drawing);
        SupervisionTransfer::create([
            'organization_id' => $this->org->id, 'application_id' => $app1->id,
            'source_office_user_id' => $this->sourceOffice->id,
            'status' => SupervisionTransfer::STATUS_PENDING,
        ]);
        SupervisionTransfer::create([
            'organization_id' => $this->org->id, 'application_id' => $app2->id,
            'source_office_user_id' => $this->sourceOffice->id,
            'status' => SupervisionTransfer::STATUS_ACCEPTED,
        ]);

        Sanctum::actingAs($this->admin);
        $all = $this->getJson('/api/v1/admin/supervision-transfers')->json('transfers');
        $this->assertCount(2, $all);
        $pending = $this->getJson('/api/v1/admin/supervision-transfers?status=pending')->json('transfers');
        $this->assertCount(1, $pending);
    }

    private function sanctionFor(string $kind): Sanction
    {
        return Sanction::create([
            'organization_id' => $this->org->id,
            'office_user_id'  => $this->sourceOffice->id,
            'kind'            => $kind,
            'effective_from'  => now()->toDateString(),
            'effective_until' => $kind === Sanction::KIND_DEREGISTRATION
                ? null
                : now()->addYear()->toDateString(),
            'reason'          => 'test',
            'issued_by_user_id' => $this->admin->id,
        ]);
    }

    private function approvedApp(ServiceDefinition $svc): Application
    {
        return $this->appWithStatus($svc, Application::STATUS_APPROVED);
    }

    private function appWithStatus(ServiceDefinition $svc, string $status): Application
    {
        return Application::create([
            'reference_number'      => strtoupper(bin2hex(random_bytes(4))),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $svc->id,
            'applicant_id'          => $this->sourceOffice->id,
            'status'                => $status,
            'data'                  => [],
            'fee_amount'            => 0,
        ]);
    }
}
