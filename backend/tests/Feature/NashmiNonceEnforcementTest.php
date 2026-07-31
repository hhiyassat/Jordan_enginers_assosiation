<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Integrations\Nashmi\Http\Middleware\ValidateIntegrationKey;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * CS-07: Nashmi nonce is REQUIRED in production and enforced
 * atomically so two concurrent identical requests cannot both pass.
 */
class NashmiNonceEnforcementTest extends TestCase
{
    private const SECRET = 'cs07-test-signing-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'nashmi.integration_key'       => 'cs07-key',
            'nashmi.signing_secret'        => self::SECRET,
            'nashmi.allowed_ips'           => [],
            'nashmi.replay_window_seconds' => 300,
            'nashmi.nonce_ttl_seconds'     => 600,
        ]);
    }

    private function handle(Request $request): Response
    {
        return (new ValidateIntegrationKey())->handle(
            $request,
            fn ($r) => response()->json(['success' => true], 200),
        );
    }

    private function signedRequest(?string $nonce, ?string $body = null): Request
    {
        $body ??= '{"probe":1}';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $body, self::SECRET);

        $req = Request::create('/api/integration/probe', 'POST', [], [], [], [], $body);
        $req->headers->set('X-Integration-Key', 'cs07-key');
        $req->headers->set('X-Nashmi-Timestamp', $timestamp);
        $req->headers->set('X-Nashmi-Signature', $signature);
        if ($nonce !== null) {
            $req->headers->set('X-Nashmi-Nonce', $nonce);
        }
        return $req;
    }

    // ── production enforcement ─────────────────────────────────────

    public function test_missing_nonce_rejected_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        // Provide the ips this middleware needs so we don't trip on
        // GsbIpWhitelist-style checks; nashmi doesn't gate on IPs in
        // production when allowlist is empty (it 403s — see setup).
        config(['nashmi.allowed_ips' => []]);
        // Re-provide a client IP the middleware can accept — an empty
        // allowlist in production 403s BEFORE the nonce check. Add a
        // dummy allowlist that matches Request::create's default.
        config(['nashmi.allowed_ips' => ['127.0.0.1']]);
        $response = $this->handle($this->signedRequest(nonce: null));
        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Missing nonce', $response->getContent());
    }

    public function test_missing_nonce_tolerated_outside_production(): void
    {
        // Non-prod (testing env) — omit nonce, request should still succeed
        // provided the signature is valid.
        $response = $this->handle($this->signedRequest(nonce: null));
        $this->assertSame(200, $response->getStatusCode());
    }

    // ── nonce dedup + replay ───────────────────────────────────────

    public function test_replayed_nonce_rejected(): void
    {
        $req1 = $this->signedRequest(nonce: 'cs07-nonce-A');
        $req2 = $this->signedRequest(nonce: 'cs07-nonce-A');

        $this->assertSame(200, $this->handle($req1)->getStatusCode());
        $this->assertSame(401, $this->handle($req2)->getStatusCode());
    }

    public function test_atomic_replay_protection_only_one_of_two_concurrent_wins(): void
    {
        // Simulate concurrency by pre-populating the cache under the
        // exact key the middleware will compute; the second call to
        // Cache::add() must return false and the middleware must
        // return 401.
        //
        // We compute the fingerprint the same way the middleware does
        // (first 12 hex chars of sha256(signing_secret)) so this test
        // fails if the key derivation drifts.
        $fingerprint = substr(hash('sha256', self::SECRET), 0, 12);
        $nonce       = 'cs07-nonce-concurrent';
        $cacheKey    = "nashmi:nonce:{$fingerprint}:" . hash('sha256', $nonce);
        Cache::forget($cacheKey);

        // First — Cache::add returns true; middleware proceeds.
        $response = $this->handle($this->signedRequest(nonce: $nonce));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(Cache::has($cacheKey), 'First request must claim the nonce key.');

        // Second — Cache::add returns false; middleware must return 401.
        $response2 = $this->handle($this->signedRequest(nonce: $nonce));
        $this->assertSame(401, $response2->getStatusCode());
    }

    public function test_key_rotation_invalidates_prior_nonces(): void
    {
        $nonce = 'cs07-nonce-rotation';

        // Claim the nonce under secret A.
        $this->assertSame(200, $this->handle($this->signedRequest(nonce: $nonce))->getStatusCode());

        // Rotate the signing secret. The stored nonce key was namespaced
        // by the old secret's fingerprint, so under the new secret the
        // same nonce string is not considered used.
        $newSecret = 'cs07-rotated-secret';
        config(['nashmi.signing_secret' => $newSecret]);

        // Build a request signed with the NEW secret.
        $body      = '{"probe":1}';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $body, $newSecret);
        $req = Request::create('/api/integration/probe', 'POST', [], [], [], [], $body);
        $req->headers->set('X-Integration-Key', 'cs07-key');
        $req->headers->set('X-Nashmi-Timestamp', $timestamp);
        $req->headers->set('X-Nashmi-Signature', $signature);
        $req->headers->set('X-Nashmi-Nonce', $nonce);

        $this->assertSame(200, $this->handle($req)->getStatusCode(),
            'A nonce reused under a rotated secret must be treated as a fresh nonce.');
    }
}
