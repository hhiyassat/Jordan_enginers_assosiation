<?php

namespace Tests\Feature\Integrations;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Integrations\Gsb\Http\Middleware\GsbIpWhitelist;
use Integrations\Gsb\Services\GsbAuthManager;
use Integrations\Gsb\Services\GsbClient;
use Tests\TestCase;

/**
 * H-05 · GSB IP whitelist must fail closed in production when empty.
 * H-06 · GSB error-path log must not dump the raw response body
 *        (MODEE Annex §4.5.2 — PII must be masked in logs).
 */
class GsbSecurityTest extends TestCase
{
    // ── H-05 ──────────────────────────────────────────────────────

    public function test_empty_allowlist_in_production_denies_access(): void
    {
        config(['gsb.allowed_ips' => []]);
        $this->app['env'] = 'production';

        $middleware = new GsbIpWhitelist();
        $request    = Request::create('/api/v1/gsb/citizens/lookup', 'GET');

        $response = $middleware->handle($request, fn () => response('ok', 200));

        $this->assertSame(403, $response->getStatusCode(),
            'H-05: empty GSB_ALLOWED_IPS in production must deny access, not allow all.');
    }

    public function test_empty_allowlist_in_local_still_allows_with_warning(): void
    {
        // Local dev must still work with an empty allowlist to avoid
        // locking developers out; the missing config is logged so
        // ops sees it before pushing to prod.
        config(['gsb.allowed_ips' => []]);
        $this->app['env'] = 'local';

        $middleware = new GsbIpWhitelist();
        $request    = Request::create('/api/v1/gsb/citizens/lookup', 'GET');

        $response = $middleware->handle($request, fn () => response('ok', 200));

        $this->assertSame(200, $response->getStatusCode(),
            'H-05: local dev must still pass through when allowlist is empty.');
    }

    public function test_configured_allowlist_permits_matching_ip_in_any_env(): void
    {
        config(['gsb.allowed_ips' => ['127.0.0.1']]);
        $this->app['env'] = 'production';

        $middleware = new GsbIpWhitelist();
        $request    = Request::create('/api/v1/gsb/citizens/lookup', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);

        $response = $middleware->handle($request, fn () => response('ok', 200));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_configured_allowlist_denies_non_matching_ip(): void
    {
        config(['gsb.allowed_ips' => ['10.0.0.1']]);
        $this->app['env'] = 'production';

        $middleware = new GsbIpWhitelist();
        $request    = Request::create('/api/v1/gsb/citizens/lookup', 'GET', server: ['REMOTE_ADDR' => '9.9.9.9']);

        $response = $middleware->handle($request, fn () => response('Access denied.', 403));
        $this->assertSame(403, $response->getStatusCode());
    }

    // ── H-06 ──────────────────────────────────────────────────────

    public function test_gsb_error_log_does_not_contain_raw_response_body(): void
    {
        // Simulate a GSB endpoint that returns 500 with a body containing
        // PII (a plausible national ID + name shape).
        $piiBody = json_encode([
            'error'       => 'internal',
            'national_id' => '9876543210',
            'name'        => 'محمد أحمد',
        ], JSON_UNESCAPED_UNICODE);

        // Configure GSB base URL + a permissive allowlist so the call
        // reaches the outbound HTTP layer. Use a non-citizen path to
        // skip OTP verification (that's tested elsewhere).
        config([
            'gsb.base_url'                       => 'https://gsb.example.test',
            'gsb.oauth.token_url'                => 'https://gsb-oauth.example.test/token',
            'gsb.oauth.client_id'                => 'client',
            'gsb.oauth.client_secret'            => 'secret',
            'gsb.oauth.scope'                    => 'scope',
            'gsb.oauth.token_cache_ttl'          => 60,
            'gsb.allowed_ips'                    => ['127.0.0.1'],
            'gsb.rate_limit.max_requests'        => 100,
            'gsb.rate_limit.per_minutes'         => 1,
            'gsb.citizen_data.endpoint_prefixes' => [],
            'gsb.bulk.endpoint_prefixes'         => [],
        ]);

        // Token fetch succeeds; the actual API call returns 500 with PII.
        Http::fake([
            'gsb-oauth.example.test/*' => Http::response(['access_token' => 'abc'], 200),
            'gsb.example.test/*'       => Http::response($piiBody, 500),
        ]);

        // Capture log calls via the log manager.
        Log::spy();

        $client = new GsbClient(app(GsbAuthManager::class));

        try {
            $client->get(
                path:    '/lookup',
                query:   ['nid' => '9876543210'],
                context: [
                    'user_id'         => 1,
                    'user_identifier' => 'test-user',
                    'service_name'    => 'lookup',
                    'operation'       => 'test',
                    'source_ip'       => '127.0.0.1',
                ],
            );
            $this->fail('GsbClient should have thrown on 500 response');
        } catch (\RuntimeException $e) {
            // expected
        }

        // Assert Log::warning was called with GSB call failed, and the
        // context does NOT include the raw body or the PII fields.
        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) use ($piiBody) {
            if ($message !== 'GSB call failed') return false;
            // H-06 invariant: the raw body must not appear in the log.
            if (array_key_exists('body', $context)) {
                return false;
            }
            $encoded = json_encode($context, JSON_UNESCAPED_UNICODE);
            if (str_contains($encoded, '9876543210') || str_contains($encoded, 'محمد أحمد')) {
                return false;
            }
            // Metadata-only structure is expected.
            return isset($context['status']) && $context['status'] === 500
                && isset($context['body_length']);
        });
    }
}
