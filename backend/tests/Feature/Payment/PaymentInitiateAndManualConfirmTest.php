<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\Payment\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\Support\SignedTestPaymentGateway;
use Tests\TestCase;

/**
 * CS-03: PaymentsController now RESOLVES the gateway from the
 * container for `initiate()` and only allows admins to run manual
 * reconciliation via `confirm()`. Applicant users can no longer
 * flip payment_status by POSTing a raw payment_reference to
 * confirm-payment — that path requires admin role AND a
 * manual_reason string.
 */
class PaymentInitiateAndManualConfirmTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $applicant;
    private User $admin;
    private User $staff;
    private ServiceDefinition $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PaymentGateway::class, new SignedTestPaymentGateway('secret-cs03'));

        $this->org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org', 'slug' => 'org-init-' . uniqid(),
            'is_active' => true,
        ]);
        $this->applicant = $this->makeUser('applicant');
        $this->admin     = $this->makeUser('admin');
        $this->staff     = $this->makeUser('staff');
        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'    => 'INIT-01',
            'name_ar' => 'دفع', 'name_en' => 'Pay',
            'currency' => 'JOD',
            'status' => 'active', 'is_locked' => false,
            'schema' => ['workflow' => ['stages' => [
                ['id' => 's', 'role' => 'staff', 'label_ar' => '.', 'sla_hours' => 24, 'actions' => ['confirm_payment']],
            ]]],
        ]);
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'organization_id' => $this->org->id,
            'name' => $role, 'email' => "cs03-{$role}-" . uniqid() . "@t.esp",
            'password' => Hash::make('Secret-Password-1234!'),
            'role' => $role, 'is_active' => true,
            'password_changed_at' => now(),
        ]);
    }

    private function approvedApp(float $fee = 100.0): Application
    {
        return Application::create([
            'reference_number' => 'A-INIT-' . random_int(1000, 9999),
            'organization_id' => $this->org->id,
            'service_definition_id' => $this->service->id,
            'applicant_id' => $this->applicant->id,
            'status' => Application::STATUS_APPROVED,
            'current_stage' => 's',
            'data' => [], 'fee_amount' => $fee,
            'payment_status' => 'pending',
        ]);
    }

    // ── initiate() ────────────────────────────────────────────────

    public function test_initiate_uses_gateway_and_persists_reference(): void
    {
        Sanctum::actingAs($this->applicant);
        $app = $this->approvedApp(150.00);

        $response = $this->postJson("/api/v1/applications/{$app->id}/initiate-payment", []);
        $response->assertStatus(201)
                 ->assertJsonStructure(['reference', 'redirect_url', 'amount', 'currency']);

        $app->refresh();
        $this->assertNotNull($app->payment_reference, 'payment_reference must be persisted from gateway response');
        $this->assertStringStartsWith('SIGN-app-', $app->payment_reference);
        $this->assertSame('pending', $app->payment_status);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Application::class,
            'auditable_id'   => $app->id,
            'action'         => 'application.payment_initiated',
        ]);
    }

    public function test_initiate_on_already_paid_application_is_idempotent(): void
    {
        Sanctum::actingAs($this->applicant);
        $app = $this->approvedApp();
        $app->update(['payment_status' => 'paid', 'payment_reference' => 'PREV-REF-1']);

        $response = $this->postJson("/api/v1/applications/{$app->id}/initiate-payment", []);
        $response->assertStatus(200)
                 ->assertJson(['payment_reference' => 'PREV-REF-1']);
    }

    // ── confirm() (admin-only manual reconciliation) ──────────────

    public function test_staff_cannot_manually_confirm_payment(): void
    {
        Sanctum::actingAs($this->staff);
        $app = $this->approvedApp();

        $response = $this->postJson("/api/v1/applications/{$app->id}/confirm-payment", [
            'payment_reference' => 'WIRE-REF-1',
            'manual_reason'     => 'gateway outage 2026-08-01 reconciled manually with bank',
        ]);
        // The role middleware blocks with 403.
        $response->assertStatus(403);
        $this->assertSame('pending', $app->fresh()->payment_status);
    }

    public function test_admin_manual_confirm_requires_manual_reason(): void
    {
        Sanctum::actingAs($this->admin);
        $app = $this->approvedApp();

        $response = $this->postJson("/api/v1/applications/{$app->id}/confirm-payment", [
            'payment_reference' => 'WIRE-REF-2',
        ]);
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['manual_reason']);
        $this->assertSame('pending', $app->fresh()->payment_status);
    }

    public function test_admin_manual_confirm_with_reason_writes_reconciliation_audit(): void
    {
        Sanctum::actingAs($this->admin);
        $app = $this->approvedApp();

        $response = $this->postJson("/api/v1/applications/{$app->id}/confirm-payment", [
            'payment_reference' => 'WIRE-REF-3',
            'manual_reason'     => 'gateway outage 2026-08-01; reconciled via SWIFT MT103 attached in ticket #442',
        ]);
        $response->assertStatus(200);
        $this->assertSame('paid', $app->fresh()->payment_status);

        $manualLog = AuditLog::where('auditable_type', Application::class)
            ->where('auditable_id', $app->id)
            ->where('action', 'application.payment_manual_reconciliation')
            ->firstOrFail();
        $this->assertSame(
            'gateway outage 2026-08-01; reconciled via SWIFT MT103 attached in ticket #442',
            $manualLog->extra['manual_reason']
        );
    }

    // ── boot-time safety ──────────────────────────────────────────

    public function test_container_resolves_gateway_for_payments_controller(): void
    {
        $this->assertInstanceOf(
            SignedTestPaymentGateway::class,
            $this->app->make(PaymentGateway::class),
            'CS-03 test binding must override the default MockPaymentGateway'
        );
    }
}
