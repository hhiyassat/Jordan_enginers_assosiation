<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use Modules\JeaServices\Models\Application;
use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use App\Models\User;
use App\Services\Payment\MockPaymentGateway;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentInitiation;
use App\Services\Payment\PaymentReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Phase 7: pins the PaymentGateway contract shape via the Mock
 * implementation. Swapping in a real gateway later has to satisfy
 * these exact assertions — reference format, receipt shape, refund
 * bool — or the switchover breaks visibly.
 */
class MockPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_container_resolves_the_mock_gateway_by_default(): void
    {
        $gw = $this->app->make(PaymentGateway::class);
        $this->assertInstanceOf(MockPaymentGateway::class, $gw,
            'AppServiceProvider must bind the mock by default so tests + local dev work');
    }

    public function test_initiate_returns_a_reference_amount_currency_and_redirect(): void
    {
        $app = $this->makeApplication(fee: 150.75, currency: 'JOD');

        $init = (new MockPaymentGateway())->initiate($app);
        $this->assertInstanceOf(PaymentInitiation::class, $init);
        $this->assertStringStartsWith('MOCK-' . $app->id . '-', $init->reference,
            'Reference should carry the application id for easy DB correlation');
        $this->assertSame(150.75, $init->amount);
        $this->assertSame('JOD', $init->currency);
        // Redirect URL exists and echoes the reference so a caller can
        // deep-link the applicant to the mock payment page.
        $this->assertStringContainsString($init->reference, $init->redirectUrl);
    }

    public function test_verify_callback_returns_a_receipt_on_valid_payload(): void
    {
        $receipt = (new MockPaymentGateway())->verifyCallback([
            'reference' => 'MOCK-42-01H',
            'amount'    => 200.00,
            'currency'  => 'JOD',
        ]);
        $this->assertInstanceOf(PaymentReceipt::class, $receipt);
        $this->assertSame('MOCK-42-01H', $receipt->reference);
        $this->assertSame(200.00, $receipt->amount);
        // settledAt defaults to now() when the payload omits it.
        $this->assertNotEmpty($receipt->settledAt);
    }

    public function test_verify_callback_throws_when_reference_is_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/reference/');
        (new MockPaymentGateway())->verifyCallback([
            'amount' => 100, 'currency' => 'JOD',
        ]);
    }

    public function test_verify_callback_throws_when_amount_is_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/amount/');
        (new MockPaymentGateway())->verifyCallback([
            'reference' => 'x', 'currency' => 'JOD',
        ]);
    }

    public function test_refund_returns_true_and_logs_to_security_channel(): void
    {
        // We're not asserting log-line content (that's implementation
        // detail), just that refund reports success on a well-formed
        // reference.
        $this->assertTrue(
            (new MockPaymentGateway())->refund('MOCK-42-01H', 'demo refund')
        );
    }

    // No model factories exist in this project — mirror the direct
    // ::create() setup used elsewhere in tests/Feature/*.
    private function makeApplication(float $fee, string $currency): Application
    {
        $org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org', 'slug' => 'org-' . uniqid(),
            'is_active' => true,
        ]);
        $applicant = User::create([
            'organization_id' => $org->id,
            'name' => 'app', 'email' => 'app' . uniqid() . '@t.esp',
            'password' => Hash::make('Secret123!'),
            'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $svc = ServiceDefinition::create([
            'organization_id' => $org->id,
            'code'    => 'PAY-' . strtoupper(substr(uniqid(), -5)),
            'name_ar' => 'دفع', 'name_en' => 'Payment',
            'currency' => $currency,
            'status' => 'active', 'is_locked' => false,
            'schema' => ['workflow' => ['stages' => [
                ['id' => 'submit', 'role' => 'applicant', 'label_ar' => '.', 'sla_hours' => 24, 'actions' => ['submit']],
            ]]],
        ]);
        return Application::create([
            'reference_number' => 'A-PAY-' . random_int(1000, 9999),
            'organization_id' => $org->id,
            'service_definition_id' => $svc->id,
            'applicant_id' => $applicant->id,
            'status' => Application::STATUS_APPROVED,
            'current_stage' => 'submit',
            'data' => [],
            'fee_amount' => $fee,
            'payment_status' => 'pending',
        ]);
    }
}
