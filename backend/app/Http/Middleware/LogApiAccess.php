<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * LogApiAccess — structured JSON access log for every API request.
 *
 * Writes to the `api_access` log channel (storage/logs/api_access.log).
 * Each line is a JSON object consumable by ELK / Grafana Loki / etc.
 *
 * §10.2: "Sensitive fields shall be masked in logs and protected in transit and at rest."
 * Masked fields: password, password_confirmation, otp, otp_code, totp_secret,
 *                current_password, x-integration-key, authorization
 *
 * §11.3 DoD: observability — request tracing
 */
class LogApiAccess
{
    /** Request body fields to redact completely before logging */
    private const MASKED_FIELDS = [
        'password', 'password_confirmation', 'current_password',
        'otp', 'otp_code', 'totp_secret', 'code', 'recovery_code',
    ];

    /** Request headers to redact */
    private const MASKED_HEADERS = [
        'authorization', 'x-integration-key', 'cookie',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Assign / propagate a correlation ID
        $requestId = $request->header('X-Request-ID') ?: (string) \Illuminate\Support\Str::uuid();
        $request->headers->set('X-Request-ID', $requestId);

        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = round((microtime(true) - $startedAt) * 1000, 1);

        $user = $request->user();

        // §10.2: mask sensitive fields in request body before logging
        $safeBody = $this->maskSensitiveFields($request->except([]));

        Log::channel('api_access')->info('api_request', [
            'ts'          => now()->toIso8601String(),
            'request_id'  => $requestId,
            'method'      => $request->method(),
            'path'        => $request->path(),
            'status'      => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'ip'          => $request->ip(),
            'user_id'     => $user?->id,
            'role'        => $user?->role,
            'body_keys'   => array_keys($safeBody), // log field names only, not values of safe fields
        ]);

        // NFR-001: warn when read requests exceed the SLO (default 500ms).
        // Emitted on the same channel with a distinct event key so dashboards can
        // trigger alerts. Only reads (GET/HEAD) are held to this budget — writes
        // legitimately take longer due to validation, workflow, and audit writes.
        // config('esp.slow_request_ms') resolves the env var inside config/esp.php.
        $sloMs = (int) config('esp.slow_request_ms', 500);
        if ($sloMs > 0 && in_array($request->method(), ['GET', 'HEAD'], true) && $durationMs > $sloMs) {
            Log::channel('api_access')->warning('slow_request', [
                'ts'          => now()->toIso8601String(),
                'request_id'  => $requestId,
                'method'      => $request->method(),
                'path'        => $request->path(),
                'duration_ms' => $durationMs,
                'slo_ms'      => $sloMs,
                'user_id'     => $user?->id,
                'role'        => $user?->role,
            ]);
        }

        // Forward the request-id to the client for correlation, plus timing
        // header so tests, CI, and browser devtools can see the budget.
        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('Server-Timing', "app;dur={$durationMs}");

        return $response;
    }

    /** Replace sensitive field values with [REDACTED] */
    private function maskSensitiveFields(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), self::MASKED_FIELDS, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskSensitiveFields($value);
            }
        }
        return $data;
    }
}
