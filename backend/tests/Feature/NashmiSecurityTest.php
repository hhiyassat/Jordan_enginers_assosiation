<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Integrations\Nashmi\Http\Middleware\ValidateIntegrationKey;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class NashmiSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'nashmi.integration_key' => 'test-key-123',
            'nashmi.signing_secret' => 'test-secret-456',
            'nashmi.allowed_ips' => [],
            'nashmi.replay_window_seconds' => 300,
            'nashmi.nonce_ttl_seconds' => 600,
        ]);
    }

    private function handleMiddleware(Request $request): Response
    {
        $middleware = new ValidateIntegrationKey();
        return $middleware->handle($request, function ($req) {
            return response()->json(['success' => true], 200);
        });
    }

    public function test_key_rejection(): void
    {
        $request = Request::create('/api/integration/cycles', 'POST', [], [], [], [], '{"test":1}');
        $request->headers->set('X-Integration-Key', 'wrong-key');

        $response = $this->handleMiddleware($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_successful_hmac_authentication(): void
    {
        $rawBody = '{"project_id": 100, "status": "active"}';
        $timestamp = (string) time();
        $secret = 'test-secret-456';
        $signature = hash_hmac('sha256', $rawBody, $secret);

        $request = Request::create('/api/integration/cycles', 'POST', [], [], [], [], $rawBody);
        $request->headers->set('X-Integration-Key', 'test-key-123');
        $request->headers->set('X-Nashmi-Timestamp', $timestamp);
        $request->headers->set('X-Nashmi-Nonce', 'nonce-111');
        $request->headers->set('X-Nashmi-Signature', $signature);

        $response = $this->handleMiddleware($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_replay_nonce_rejection(): void
    {
        $rawBody = '{"project_id": 100}';
        $timestamp = (string) time();
        $secret = 'test-secret-456';
        $signature = hash_hmac('sha256', $rawBody, $secret);

        $request = Request::create('/api/integration/cycles', 'POST', [], [], [], [], $rawBody);
        $request->headers->set('X-Integration-Key', 'test-key-123');
        $request->headers->set('X-Nashmi-Timestamp', $timestamp);
        $request->headers->set('X-Nashmi-Nonce', 'duplicate-nonce');
        $request->headers->set('X-Nashmi-Signature', $signature);

        $res1 = $this->handleMiddleware($request);
        $this->assertEquals(200, $res1->getStatusCode());

        // Second request with same nonce
        $res2 = $this->handleMiddleware($request);
        $this->assertEquals(401, $res2->getStatusCode());
    }

    public function test_outdated_timestamp_rejection(): void
    {
        $rawBody = '{"project_id": 100}';
        $timestamp = (string) (time() - 400); // 400s old (> 300s window)
        $secret = 'test-secret-456';
        $signature = hash_hmac('sha256', $rawBody, $secret);

        $request = Request::create('/api/integration/cycles', 'POST', [], [], [], [], $rawBody);
        $request->headers->set('X-Integration-Key', 'test-key-123');
        $request->headers->set('X-Nashmi-Timestamp', $timestamp);
        $request->headers->set('X-Nashmi-Signature', $signature);

        $response = $this->handleMiddleware($request);
        $this->assertEquals(401, $response->getStatusCode());
    }
}
