<?php

namespace Tests\Unit\Support;

use App\Services\Payment\MockPaymentGateway;
use App\Services\Payment\PaymentGateway;
use App\Support\ProductionSafety;
use Modules\JeaServices\Engine\FakeJeaMembershipVerifier;
use Modules\JeaServices\Engine\HttpJeaMembershipVerifier;
use Modules\JeaServices\Engine\JeaMembershipVerifier;
use Tests\TestCase;

/**
 * P0-E · ProductionSafety validator.
 *
 * Verifies every check individually by driving the collector with
 * carefully-set config values, without actually flipping app env
 * to 'production' (which would trigger the guard globally and
 * abort test boot).
 */
class ProductionSafetyTest extends TestCase
{
    public function test_enforce_is_a_noop_outside_production(): void
    {
        // In testing env with Mock bound, calling enforce() must NOT throw.
        $this->assertNull(ProductionSafety::enforce($this->app));
    }

    // ── C-02 ─────────────────────────────────────────────────────

    public function test_mock_payment_gateway_bound_in_production_is_a_violation(): void
    {
        $this->app->singleton(PaymentGateway::class, MockPaymentGateway::class);
        $violations = (new ProductionSafety($this->app))->collectViolations();
        $this->assertContainsSubstring('MockPaymentGateway is bound', $violations);
    }

    public function test_real_payment_gateway_bound_is_ok(): void
    {
        $realGateway = new class implements PaymentGateway {
            public function initiate(\Modules\JeaServices\Models\Application $app): \App\Services\Payment\PaymentInitiation {
                throw new \LogicException('stub');
            }
            public function verifyCallback(array $callbackPayload): \App\Services\Payment\PaymentReceipt {
                throw new \LogicException('stub');
            }
            public function refund(string $paymentReference, ?string $reason = null): bool {
                return false;
            }
        };
        $this->app->instance(PaymentGateway::class, $realGateway);
        $violations = (new ProductionSafety($this->app))->collectViolations();
        $this->assertNotContainsSubstring('MockPaymentGateway is bound', $violations);
    }

    // ── C-03 ─────────────────────────────────────────────────────

    public function test_fake_jea_verifier_bound_in_production_is_a_violation(): void
    {
        $this->app->bind(JeaMembershipVerifier::class, FakeJeaMembershipVerifier::class);
        $violations = (new ProductionSafety($this->app))->collectViolations();
        $this->assertContainsSubstring('FakeJeaMembershipVerifier is bound', $violations);
    }

    public function test_http_jea_verifier_bound_is_ok(): void
    {
        $this->app->bind(JeaMembershipVerifier::class, HttpJeaMembershipVerifier::class);
        $violations = (new ProductionSafety($this->app))->collectViolations();
        $this->assertNotContainsSubstring('FakeJeaMembershipVerifier is bound', $violations);
    }

    // ── Filesystem / queue / cache / session ─────────────────────

    public function test_filesystem_default_local_is_a_violation(): void
    {
        config(['filesystems.default' => 'local']);
        $violations = (new ProductionSafety($this->app))->collectViolations();
        $this->assertContainsSubstring("FILESYSTEM_DISK is 'local'", $violations);
    }

    public function test_filesystem_default_s3_is_ok(): void
    {
        config(['filesystems.default' => 's3']);
        $violations = (new ProductionSafety($this->app))->collectViolations();
        $this->assertNotContainsSubstring("FILESYSTEM_DISK is 's3'", $violations);
    }

    public function test_queue_sync_is_a_violation(): void
    {
        config(['queue.default' => 'sync']);
        $violations = (new ProductionSafety($this->app))->collectViolations();
        $this->assertContainsSubstring('QUEUE_CONNECTION=sync', $violations);
    }

    public function test_cache_file_is_a_violation(): void
    {
        config(['cache.default' => 'file']);
        $violations = (new ProductionSafety($this->app))->collectViolations();
        $this->assertContainsSubstring("CACHE_STORE is 'file'", $violations);
    }

    public function test_session_file_is_a_violation(): void
    {
        config(['session.driver' => 'file']);
        $violations = (new ProductionSafety($this->app))->collectViolations();
        $this->assertContainsSubstring("SESSION_DRIVER is 'file'", $violations);
    }

    public function test_app_debug_true_is_a_violation(): void
    {
        config(['app.debug' => true]);
        $violations = (new ProductionSafety($this->app))->collectViolations();
        $this->assertContainsSubstring('APP_DEBUG=true', $violations);
    }

    public function test_session_cookie_not_secure_is_a_violation(): void
    {
        config(['session.secure' => false]);
        $violations = (new ProductionSafety($this->app))->collectViolations();
        $this->assertContainsSubstring('SESSION_SECURE_COOKIE must be true', $violations);
    }

    public function test_sanctum_expiration_null_is_a_violation(): void
    {
        config(['sanctum.expiration' => null]);
        $violations = (new ProductionSafety($this->app))->collectViolations();
        $this->assertContainsSubstring('sanctum.expiration must be a positive integer', $violations);
    }

    public function test_sanctum_expiration_positive_is_ok(): void
    {
        config(['sanctum.expiration' => 480]);
        $violations = (new ProductionSafety($this->app))->collectViolations();
        $this->assertNotContainsSubstring('sanctum.expiration must be a positive integer', $violations);
    }

    public function test_empty_gsb_allowlist_is_a_violation(): void
    {
        config(['gsb.allowed_ips' => []]);
        $violations = (new ProductionSafety($this->app))->collectViolations();
        $this->assertContainsSubstring('GSB_ALLOWED_IPS is empty', $violations);
    }

    public function test_missing_nashmi_signing_secret_is_a_violation(): void
    {
        config(['integrations.nashmi.signing_secret' => '']);
        $violations = (new ProductionSafety($this->app))->collectViolations();
        $this->assertContainsSubstring('integrations.nashmi.signing_secret is empty', $violations);
    }

    public function test_captcha_disabled_is_a_violation(): void
    {
        config(['esp.captcha_enabled' => false]);
        $violations = (new ProductionSafety($this->app))->collectViolations();
        $this->assertContainsSubstring('CAPTCHA_ENABLED must be true', $violations);
    }

    // ── Enforce (production env, all violations) ──────────────────

    public function test_enforce_in_production_aborts_with_message(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Refusing to boot in production');
        ProductionSafety::enforce($this->app);
    }

    // ── helpers ───────────────────────────────────────────────────

    /**
     * @param  list<string>  $haystack
     */
    private function assertContainsSubstring(string $needle, array $haystack): void
    {
        foreach ($haystack as $item) {
            if (str_contains($item, $needle)) {
                $this->assertTrue(true);
                return;
            }
        }
        $this->fail(sprintf('Expected a violation containing "%s". Got: %s',
            $needle, json_encode($haystack, JSON_UNESCAPED_UNICODE)));
    }

    /**
     * @param  list<string>  $haystack
     */
    private function assertNotContainsSubstring(string $needle, array $haystack): void
    {
        foreach ($haystack as $item) {
            if (str_contains($item, $needle)) {
                $this->fail(sprintf('Unexpected violation containing "%s". Got: %s',
                    $needle, json_encode($haystack, JSON_UNESCAPED_UNICODE)));
            }
        }
        $this->assertTrue(true);
    }
}
