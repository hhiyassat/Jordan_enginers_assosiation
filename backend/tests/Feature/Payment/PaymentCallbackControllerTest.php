<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Models\Organization;
use Modules\JeaServices\Models\PaymentCallback;
use App\Models\User;
use App\Services\Payment\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\Support\SignedTestPaymentGateway;
use Tests\TestCase;

/**
 * CS-03: end-to-end tests for the callback controller behind the
 * signed test gateway. Covers unsigned / invalid-signature / tampered
 * / expired / duplicate / valid paths.
 */
class PaymentCallbackControllerTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'shhh-cs03-test-secret';

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PaymentGateway::class, new SignedTestPaymentGateway(self::SECRET));

        $org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org', 'slug' => 'org-cb-' . uniqid(),
            'is_active' => true,
        ]);
        $applicant = User::create([
            'organization_id' => $org->id,
            'name' => 'App', 'email' => 'callback-app@t.esp',
            'password' => Hash::make('Secret-Password-1234!'),
            'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $service = ServiceDefinition::create([
            'organization_id' => $org->id,
            'code'    => 'CB-01',
            'name_ar' => 'دفع', 'name_en' => 'Pay',
            'currency' => 'JOD',
            'status' => 'active', 'is_locked' => false,
            'schema' => ['workflow' => ['stages' => [
                ['id' => 's', 'role' => 'staff', 'label_ar' => '.', 'sla_hours' => 24, 'actions' => ['confirm_payment']],
            ]]],
        ]);

        $this->application = Application::create([
            'reference_number' => 'A-CB-' . random_int(1000, 9999),
            'organization_id' => $org->id,
            'service_definition_id' => $service->id,
            'applicant_id' => $applicant->id,
            'status' => Application::STATUS_APPROVED,
            'current_stage' => 's',
            'data' => [], 'fee_amount' => 100.00,
            'payment_status' => 'pending',
            'payment_reference' => 'SIGN-app-1-abc',
        ]);
    }

    private function signedPayload(array $overrides = []): array
    {
        $payload = array_merge([
            'reference'  => 'SIGN-app-1-abc',
            'amount'     => '100.00',
            'currency'   => 'JOD',
            'settled_at' => now()->toIso8601String(),
        ], $overrides);

        $payload['signature'] = SignedTestPaymentGateway::computeSignature(
            self::SECRET,
            $payload['reference'],
            $payload['amount'],
            $payload['currency'],
            $payload['settled_at'],
        );

        return $payload;
    }

    public function test_unsigned_callback_rejected(): void
    {
        $response = $this->postJson('/api/payment/callback', [
            'reference' => 'SIGN-app-1-abc',
            'amount'    => '100.00',
            'currency'  => 'JOD',
            'settled_at' => now()->toIso8601String(),
            // no signature
        ]);

        $response->assertStatus(401);
        $this->assertSame('pending', $this->application->fresh()->payment_status);
        $this->assertSame(0, PaymentCallback::count());
    }

    public function test_invalid_signature_rejected(): void
    {
        $payload = $this->signedPayload();
        $payload['signature'] = 'not-a-real-signature';

        $response = $this->postJson('/api/payment/callback', $payload);
        $response->assertStatus(401);
        $this->assertSame('pending', $this->application->fresh()->payment_status);
    }

    public function test_tampered_payload_rejected(): void
    {
        $payload = $this->signedPayload();
        $payload['amount'] = '9999.00'; // signature still on original amount
        $response = $this->postJson('/api/payment/callback', $payload);
        $response->assertStatus(401);
        $this->assertSame('pending', $this->application->fresh()->payment_status);
    }

    public function test_expired_callback_rejected(): void
    {
        $payload = $this->signedPayload([
            'settled_at' => now()->subHours(2)->toIso8601String(),
        ]);
        $response = $this->postJson('/api/payment/callback', $payload);
        $response->assertStatus(401);
        $this->assertSame('pending', $this->application->fresh()->payment_status);
    }

    public function test_valid_callback_confirms_payment(): void
    {
        $payload = $this->signedPayload();
        $response = $this->postJson('/api/payment/callback', $payload);

        $response->assertStatus(200)
                 ->assertJson([
                     'payment_status' => 'paid',
                     'idempotent'     => false,
                 ]);
        $this->assertSame('paid', $this->application->fresh()->payment_status);
        $this->assertSame(1, PaymentCallback::count());
    }

    public function test_duplicate_callback_is_idempotent(): void
    {
        $payload = $this->signedPayload();
        $first = $this->postJson('/api/payment/callback', $payload);
        $first->assertStatus(200)->assertJson(['idempotent' => false]);

        // Replay with the exact same signed payload — must not
        // double-confirm, and the response must be idempotent success.
        $second = $this->postJson('/api/payment/callback', $payload);
        $second->assertStatus(200)->assertJson(['idempotent' => true]);

        $this->assertSame(1, PaymentCallback::count(),
            'Only one payment_callbacks row should exist across a replay');
        $this->assertSame('paid', $this->application->fresh()->payment_status);
    }

    public function test_unknown_reference_returns_404(): void
    {
        $payload = $this->signedPayload([
            'reference' => 'SIGN-nonexistent-ref-xyz',
        ]);
        $response = $this->postJson('/api/payment/callback', $payload);
        $response->assertStatus(404);
    }
}
