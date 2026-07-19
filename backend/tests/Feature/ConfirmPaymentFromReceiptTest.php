<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Engine\Exceptions\InvalidStateException;
use App\Engine\WorkflowEngine;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\ServiceDefinition;
use App\Models\User;
use App\Services\Payment\PaymentReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 7: pin the receipt-based confirmation path.
 *
 * The webhook flow ends here — a real gateway would have verified its
 * signature via PaymentGateway::verifyCallback() and then handed us the
 * resulting PaymentReceipt. These tests lock the shape of that
 * hand-off so a future gateway swap can't silently mis-mark a
 * payment or drop audit fields.
 */
class ConfirmPaymentFromReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $actor;
    private ServiceDefinition $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org', 'slug' => 'org-pay-' . uniqid(),
            'is_active' => true,
        ]);
        $this->actor = User::create([
            'organization_id' => $this->org->id,
            'name' => 'staff', 'email' => 'staff-pay@t.esp',
            'password' => Hash::make('Secret123!'),
            'role' => 'staff', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'    => 'PAY-RECEIPT',
            'name_ar' => 'دفع', 'name_en' => 'Pay',
            'currency' => 'JOD',
            'status' => 'active', 'is_locked' => false,
            'schema' => ['workflow' => ['stages' => [
                ['id' => 's', 'role' => 'staff', 'label_ar' => '.', 'sla_hours' => 24, 'actions' => ['confirm_payment']],
            ]]],
        ]);
    }

    private function approvedApp(float $fee): Application
    {
        return Application::create([
            'reference_number' => 'A-RCPT-' . random_int(1000, 9999),
            'organization_id' => $this->org->id,
            'service_definition_id' => $this->service->id,
            'applicant_id' => $this->actor->id,
            'status' => Application::STATUS_APPROVED,
            'current_stage' => 's',
            'data' => [], 'fee_amount' => $fee,
            'payment_status' => 'pending',
        ]);
    }

    public function test_receipt_confirms_payment_and_writes_audit_with_gateway_meta(): void
    {
        $app = $this->approvedApp(120.50);
        $engine = new WorkflowEngine($this->service);
        $receipt = new PaymentReceipt(
            reference: 'MOCK-99-01H',
            amount:    120.50,
            currency:  'JOD',
            settledAt: '2026-07-19T10:00:00Z',
            meta:      ['gateway' => 'mock'],
        );

        $updated = $engine->confirmPaymentFromReceipt($app, $this->actor, $receipt);
        $this->assertSame('paid', $updated->payment_status);
        $this->assertSame('MOCK-99-01H', $updated->payment_reference);
        $this->assertNotNull($updated->payment_confirmed_at);

        // Audit log must carry the receipt shape — a real integration
        // would rely on this for reconciliation.
        $log = AuditLog::where('auditable_type', Application::class)
            ->where('auditable_id', $app->id)
            ->where('action', 'application.payment_confirmed')
            ->firstOrFail();
        $this->assertSame(120.50, $log->extra['amount']);
        $this->assertSame('JOD',   $log->extra['currency']);
        $this->assertSame('mock',  $log->extra['gateway_meta']['gateway']);
    }

    public function test_receipt_rejected_when_amount_does_not_match_application_fee(): void
    {
        // A tampered / mis-settled callback that says "200" for a "150" app
        // must NOT flip payment_status — the classic under-pay attack.
        $app = $this->approvedApp(150.00);
        $engine = new WorkflowEngine($this->service);
        $receipt = new PaymentReceipt(
            reference: 'MOCK-99-BAD', amount: 200.00,
            currency: 'JOD', settledAt: now()->toIso8601String(),
        );

        try {
            $engine->confirmPaymentFromReceipt($app, $this->actor, $receipt);
            $this->fail('Expected InvalidStateException on amount mismatch');
        } catch (InvalidStateException $e) {
            $this->assertStringContainsString('لا يطابق', $e->getMessage());
        }
        $this->assertSame('pending', $app->fresh()->payment_status);
    }

    public function test_receipt_rejected_when_application_is_not_approved(): void
    {
        // Same amount, but the application is still in draft — the
        // engine must refuse rather than silently flip a non-approved
        // application to paid.
        $app = $this->approvedApp(100.00);
        $app->update(['status' => Application::STATUS_DRAFT]);
        $receipt = new PaymentReceipt(
            reference: 'x', amount: 100.00, currency: 'JOD',
            settledAt: now()->toIso8601String(),
        );

        $this->expectException(InvalidStateException::class);
        (new WorkflowEngine($this->service))->confirmPaymentFromReceipt($app, $this->actor, $receipt);
    }

    public function test_legacy_string_reference_path_still_works(): void
    {
        // ConfirmPaymentRequest still POSTs a string reference; confirm
        // the shim keeps that call path alive.
        $app = $this->approvedApp(50.00);
        $updated = (new WorkflowEngine($this->service))
            ->confirmPayment($app, $this->actor, 'MANUAL-REF-1');
        $this->assertSame('paid', $updated->payment_status);
        $this->assertSame('MANUAL-REF-1', $updated->payment_reference);
    }
}
