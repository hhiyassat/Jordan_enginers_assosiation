<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Payment\MockPaymentGateway;
use App\Services\Payment\PaymentGateway;
use Illuminate\Contracts\Foundation\Application;

/**
 * ProductionSafety — centralized boot-time invariants for production.
 *
 * P0-E remediation. Any of the following being true when
 * `app()->environment('production')` is intentionally a hard boot
 * failure. That is much better than a silent misconfiguration
 * that surfaces as: a mock payment gateway approving live
 * transactions, a fake JEA verifier trusting arbitrary input,
 * an object store being backed by the local disk, uploads that
 * silently vanish on the next container restart, tokens that
 * never expire, or ops learning about `APP_DEBUG=true` from a
 * screenshot of a stack trace.
 *
 * The class is invoked from AppServiceProvider::boot. Every
 * assertion is individually unit-testable by mocking the
 * relevant config/container binding — see ProductionSafetyTest.
 */
final class ProductionSafety
{
    /**
     * List of failure messages accumulated by `check()`. Empty means safe.
     *
     * @var list<string>
     */
    private array $violations = [];

    public function __construct(private readonly Application $app)
    {
    }

    /**
     * Run the full checklist and abort boot if any violation is found.
     * Non-production environments are exempt.
     */
    public static function enforce(Application $app): void
    {
        if (!$app->environment('production')) {
            return;
        }

        $violations = (new self($app))->collectViolations();

        if ($violations !== []) {
            $message = "Refusing to boot in production. Fix the following before deploying:\n"
                . implode("\n", array_map(fn ($v) => "  - {$v}", $violations));
            throw new \RuntimeException($message);
        }
    }

    /**
     * Public for tests. Returns the list of violations without aborting.
     *
     * @return list<string>
     */
    public function collectViolations(): array
    {
        $this->violations = [];

        $this->checkPaymentGatewayBinding();
        $this->checkJeaMembershipVerifierBinding();
        $this->checkFilesystemDisk();
        $this->checkQueueConnection();
        $this->checkCacheStore();
        $this->checkSessionDriver();
        $this->checkAppDebug();
        $this->checkSessionCookieSecurity();
        $this->checkSanctumAbsoluteExpiration();
        $this->checkGsbIpAllowlist();
        $this->checkNashmiSigningSecret();
        $this->checkCaptchaEnabled();
        $this->checkPasswordCompromisedGate();

        return $this->violations;
    }

    /**
     * C-02: MockPaymentGateway must not be the production PaymentGateway.
     */
    private function checkPaymentGatewayBinding(): void
    {
        try {
            $gateway = $this->app->make(PaymentGateway::class);
        } catch (\Throwable) {
            $this->violations[] = 'PaymentGateway is not bound; production requires a real gateway.';
            return;
        }
        if (get_class($gateway) === MockPaymentGateway::class) {
            $this->violations[] = 'MockPaymentGateway is bound to PaymentGateway. Bind a real signed-callback gateway before deploying.';
        }
    }

    /**
     * C-03: FakeJeaMembershipVerifier must not be the production verifier.
     */
    private function checkJeaMembershipVerifierBinding(): void
    {
        $verifierInterface = 'Modules\\JeaServices\\Engine\\JeaMembershipVerifier';
        $fakeClass         = 'Modules\\JeaServices\\Engine\\FakeJeaMembershipVerifier';
        if (!interface_exists($verifierInterface)) {
            // Module disabled — nothing to check.
            return;
        }
        try {
            $verifier = $this->app->make($verifierInterface);
        } catch (\Throwable) {
            $this->violations[] = 'JeaMembershipVerifier is not bound; production requires a real HTTP verifier.';
            return;
        }
        if (class_exists($fakeClass) && get_class($verifier) === $fakeClass) {
            $this->violations[] = 'FakeJeaMembershipVerifier is bound to JeaMembershipVerifier. Bind HttpJeaMembershipVerifier before deploying.';
        }
    }

    private function checkFilesystemDisk(): void
    {
        $disk = (string) config('filesystems.default');
        if ($disk === 'local' || $disk === 'public') {
            $this->violations[] = "FILESYSTEM_DISK is '{$disk}'; production must use object storage (e.g. s3).";
        }
    }

    private function checkQueueConnection(): void
    {
        $q = (string) config('queue.default');
        if ($q === 'sync') {
            $this->violations[] = 'QUEUE_CONNECTION=sync in production hides queue failures and blocks HTTP threads.';
        }
    }

    private function checkCacheStore(): void
    {
        $store = (string) config('cache.default');
        if ($store === 'file' || $store === 'array') {
            $this->violations[] = "CACHE_STORE is '{$store}'; production must use a shared store (redis/database).";
        }
    }

    private function checkSessionDriver(): void
    {
        $driver = (string) config('session.driver');
        if ($driver === 'file' || $driver === 'array' || $driver === 'cookie') {
            $this->violations[] = "SESSION_DRIVER is '{$driver}'; production must use a shared driver (redis/database).";
        }
    }

    private function checkAppDebug(): void
    {
        if ((bool) config('app.debug') === true) {
            $this->violations[] = 'APP_DEBUG=true in production leaks stack traces to callers.';
        }
    }

    private function checkSessionCookieSecurity(): void
    {
        if ((bool) config('session.secure') !== true) {
            $this->violations[] = 'SESSION_SECURE_COOKIE must be true in production so the session cookie is only sent over TLS.';
        }
        if ((bool) config('session.http_only') !== true) {
            $this->violations[] = 'SESSION_HTTP_ONLY must be true in production so the session cookie cannot be read from JS.';
        }
    }

    private function checkSanctumAbsoluteExpiration(): void
    {
        $exp = config('sanctum.expiration');
        if ($exp === null || (int) $exp <= 0) {
            $this->violations[] = 'sanctum.expiration must be a positive integer (minutes) — an unbounded token lifetime is forbidden in production.';
        }
    }

    private function checkGsbIpAllowlist(): void
    {
        $allow = config('gsb.allowed_ips');
        if ($allow === null || (is_array($allow) && $allow === [])) {
            $this->violations[] = 'GSB_ALLOWED_IPS is empty; production must enumerate the callers permitted to reach GSB endpoints.';
        }
    }

    private function checkNashmiSigningSecret(): void
    {
        // Nashmi lives at backend/config/nashmi.php; there is no
        // `integrations.nashmi.*` sub-tree — `integrations.php` only
        // holds the provider-class registry. Reading the wrong key
        // always resolved to empty, causing every prod boot to abort
        // even when NASHMI_SIGNING_SECRET was set (see CS-01).
        $secret = (string) config('nashmi.signing_secret', '');
        if ($secret === '') {
            $this->violations[] = 'nashmi.signing_secret is empty; production must set NASHMI_SIGNING_SECRET before accepting webhook traffic.';
        }
    }

    private function checkCaptchaEnabled(): void
    {
        if ((bool) config('esp.captcha_enabled', false) !== true) {
            $this->violations[] = 'CAPTCHA_ENABLED must be true in production so login/register/office-registration are protected.';
        }
    }

    private function checkPasswordCompromisedGate(): void
    {
        if ((bool) config('esp.password_check_compromised', false) !== true) {
            $this->violations[] = 'PASSWORD_CHECK_COMPROMISED must be true in production so leaked passwords are rejected via the HIBP k-anonymity API.';
        }
    }
}
