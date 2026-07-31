<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * HealthController — liveness/readiness endpoints.
 *
 * `/up` (liveness) is a Laravel default registered in bootstrap/app.php:
 *   "the process is up and running". No dependency check. Used by load
 *   balancers to know the container is alive.
 *
 * `/ready` (readiness) verifies the app can actually serve traffic:
 *   database reachable, cache reachable, config sane. Ops points the
 *   orchestrator's readiness probe at this so a container that lost
 *   its DB is removed from the LB rotation until it recovers.
 *
 * L-12 remediation. Deliberately kept lightweight — one round-trip to
 * each dependency, no expensive scans. Never returns error detail
 * to callers (§4.8.1 generic errors) but structured detail lives in
 * the response so a k8s / docker probe log captures the failure mode.
 */
final class HealthController extends Controller
{
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
        ];

        $overall = collect($checks)->every(fn ($c) => $c['ok'] === true);

        return response()->json([
            'status' => $overall ? 'ready' : 'not_ready',
            'checks' => $checks,
        ], $overall ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->select('select 1 as up');
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => 'unreachable'];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health:probe';
            Cache::put($key, '1', 5);
            $back = Cache::get($key);
            return ['ok' => $back === '1'];
        } catch (\Throwable) {
            return ['ok' => false, 'reason' => 'unreachable'];
        }
    }
}
