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

        // Forward the request-id to the client for correlation
        $response->headers->set('X-Request-ID', $requestId);

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
