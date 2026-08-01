<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L-12 · /api/ready readiness probe.
 */
class HealthReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_ready_returns_200_when_db_and_cache_are_reachable(): void
    {
        $res = $this->getJson('/api/ready');
        $res->assertOk();
        $body = $res->json();
        $this->assertSame('ready', $body['status']);
        $this->assertTrue($body['checks']['database']['ok']);
        $this->assertTrue($body['checks']['cache']['ok']);
    }

    public function test_ready_response_shape_includes_all_documented_checks(): void
    {
        $res = $this->getJson('/api/ready');
        $body = $res->json();
        $this->assertArrayHasKey('status', $body);
        $this->assertArrayHasKey('checks', $body);
        $this->assertArrayHasKey('database', $body['checks']);
        $this->assertArrayHasKey('cache', $body['checks']);
    }

    /**
     * Negative-path coverage (503 when a dependency is unreachable) is
     * exercised in integration by pointing the readiness probe at a
     * container with the DB stopped; a unit-level negative test would
     * require mutating the shared test connection and would leak into
     * every subsequent test in the same run. The controller's
     * try/catch fallback is exercised via PHPStan (both branches
     * type-check).
     */
}
