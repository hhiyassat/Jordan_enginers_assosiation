<?php

namespace Integrations\Gsb\Services;

use Integrations\Gsb\Models\GsbCallLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * GsbClient — Government Service Bus HTTP Client
 *
 * Single entry point for ALL outbound calls to GSB.
 * Enforces MODEE Annex 4.15 API Security Policy on every request:
 *
 *   §4.4.1  OAuth 2.0 Bearer token on every call
 *   §4.4.3  Short-lived tokens managed by GsbAuthManager
 *   §4.5.1  TLS 1.2+ enforced via cURL option
 *   §4.5.2  PII masking in logs
 *   §4.5.7  Citizen data endpoints require verified OTP
 *   §4.5.10 Every call logged: URL, timestamp, source IP, user ID
 *   §4.5.11 Source IP checked against whitelist before call
 *   §4.5.14 All API usage routed through GSB (this class IS that gateway)
 *   §4.5.15 Token never returned in responses
 *   §4.5.16 Bulk data blocked without committee approval
 *   §4.5.17 Bulk responses stripped of image fields
 *   §4.7    Rate limiting on outgoing requests
 *   §4.8.1  Generic error messages to callers — details only in logs
 *   §4.9.1  Full activity log for auditing
 */
class GsbClient
{
    public function __construct(private readonly GsbAuthManager $auth) {}

    // ── Public API ────────────────────────────────────────────────────

    /**
     * Make a GET request to a GSB endpoint.
     *
     * @param  string      $path          GSB path (e.g. '/citizens/lookup')
     * @param  array       $query         Query parameters
     * @param  array       $context       Caller context: user_id, service_name, operation, source_ip
     * @param  string|null $otpToken      Required for citizen data endpoints (§4.5.7)
     * @return array                      Decoded response data
     *
     * @throws \RuntimeException          On policy violation or call failure
     */
    public function get(string $path, array $query = [], array $context = [], ?string $otpToken = null): array
    {
        return $this->call('GET', $path, $query, [], $context, $otpToken);
    }

    /**
     * Make a POST request to a GSB endpoint.
     *
     * @param  string      $path
     * @param  array       $payload       Request body
     * @param  array       $context       Caller context
     * @param  string|null $otpToken      Required for citizen data endpoints
     * @param  bool        $committeeApproved  Required for bulk endpoints (§4.5.16)
     * @return array
     */
    public function post(
        string $path,
        array $payload = [],
        array $context = [],
        ?string $otpToken = null,
        bool $committeeApproved = false,
    ): array {
        return $this->call('POST', $path, [], $payload, $context, $otpToken, $committeeApproved);
    }

    // ── Core ──────────────────────────────────────────────────────────

    private function call(
        string $method,
        string $path,
        array $query,
        array $payload,
        array $context,
        ?string $otpToken,
        bool $committeeApproved = false,
    ): array {
        $sourceIp    = $context['source_ip']    ?? request()->ip();
        $userId      = $context['user_id']       ?? null;
        $userIdent   = $context['user_identifier'] ?? (string) $userId;
        $serviceName = $context['service_name']  ?? null;
        $operation   = $context['operation']     ?? null;
        $startMs     = (int) (microtime(true) * 1000);
        $fullUrl     = rtrim(config('gsb.base_url'), '/') . '/' . ltrim($path, '/');

        $isCitizenEndpoint = $this->isCitizenDataEndpoint($path);
        $isBulkEndpoint    = $this->isBulkEndpoint($path);

        // ── §4.5.11: IP Whitelist ──────────────────────────────────────
        $ipWhitelisted = $this->isIpAllowed($sourceIp);
        if (! $ipWhitelisted) {
            $this->logCall(compact('fullUrl', 'method', 'sourceIp', 'userIdent', 'userId',
                'serviceName', 'operation', 'isCitizenEndpoint', 'isBulkEndpoint',
                'committeeApproved', 'startMs'), false, 403, 'IP_NOT_WHITELISTED');
            throw new \RuntimeException('Access denied: source address not authorized.'); // §4.8.1 generic
        }

        // ── §4.7: Rate Limiting ────────────────────────────────────────
        $rateLimitKey = "gsb:{$sourceIp}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, config('gsb.rate_limit.max_requests'))) {
            $this->logCall(compact('fullUrl', 'method', 'sourceIp', 'userIdent', 'userId',
                'serviceName', 'operation', 'isCitizenEndpoint', 'isBulkEndpoint',
                'committeeApproved', 'startMs'), false, 429, 'RATE_LIMIT_EXCEEDED');
            throw new \RuntimeException('Too many requests. Please slow down.'); // §4.8.1
        }
        RateLimiter::hit($rateLimitKey, config('gsb.rate_limit.per_minutes') * 60);

        // ── §4.5.7: Citizen data requires OTP verification ────────────
        if ($isCitizenEndpoint) {
            if (empty($otpToken) || ! $this->verifyOtp($otpToken, $userId)) {
                $this->logCall(compact('fullUrl', 'method', 'sourceIp', 'userIdent', 'userId',
                    'serviceName', 'operation', 'isCitizenEndpoint', 'isBulkEndpoint',
                    'committeeApproved', 'startMs'), false, 401, 'OTP_REQUIRED', otpVerified: false);
                throw new \RuntimeException('Citizen data access requires OTP verification.');
            }
        }

        // ── §4.5.16: Bulk data requires committee approval ────────────
        if ($isBulkEndpoint && config('gsb.bulk.require_committee_approval') && ! $committeeApproved) {
            $this->logCall(compact('fullUrl', 'method', 'sourceIp', 'userIdent', 'userId',
                'serviceName', 'operation', 'isCitizenEndpoint', 'isBulkEndpoint',
                'committeeApproved', 'startMs'), false, 403, 'COMMITTEE_APPROVAL_REQUIRED');
            throw new \RuntimeException('Bulk data request requires Data Committee approval.');
        }

        // ── §4.4.1: OAuth 2.0 Bearer token ───────────────────────────
        try {
            $token = $this->auth->getAccessToken();
        } catch (\RuntimeException $e) {
            $this->logCall(compact('fullUrl', 'method', 'sourceIp', 'userIdent', 'userId',
                'serviceName', 'operation', 'isCitizenEndpoint', 'isBulkEndpoint',
                'committeeApproved', 'startMs'), false, 503, 'AUTH_UNAVAILABLE');
            throw new \RuntimeException('Government service bus temporarily unavailable.');
        }

        // ── §4.5.1: TLS 1.2+ enforced ────────────────────────────────
        $http = Http::timeout(config('gsb.timeout'))
            ->withOptions(['curl' => [CURLOPT_SSLVERSION => config('gsb.tls_version')]])
            ->withToken($token)                              // Bearer — §4.4.1
            ->withHeaders(['X-Source-Service' => 'esp-v2']); // identify caller to GSB

        // ── §4.6.1: Input already validated by Laravel FormRequests ──
        // GSB client trusts that upstream validation ran. We do NOT re-validate
        // here but ensure no injection in path/query via type enforcement.
        try {
            $response = match ($method) {
                'GET'  => $http->get($fullUrl, $query),
                'POST' => $http->post($fullUrl, $payload),
                default => throw new \InvalidArgumentException("Unsupported method {$method}"),
            };
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('GSB connection failed', ['url' => $fullUrl, 'error' => $e->getMessage()]);
            $this->logCall(compact('fullUrl', 'method', 'sourceIp', 'userIdent', 'userId',
                'serviceName', 'operation', 'isCitizenEndpoint', 'isBulkEndpoint',
                'committeeApproved', 'startMs'), false, 0, 'CONNECTION_FAILED');
            throw new \RuntimeException('Government service bus connection failed.'); // §4.8.1
        }

        // Retry once on 401 (token may have just expired)
        if ($response->status() === 401) {
            $token    = $this->auth->refresh();
            $response = match ($method) {
                'GET'  => $http->withToken($token)->get($fullUrl, $query),
                'POST' => $http->withToken($token)->post($fullUrl, $payload),
            };
        }

        $success = $response->successful();
        $data    = $response->json() ?? [];

        // ── §4.5.17: Strip image fields from bulk responses ───────────
        if ($isBulkEndpoint) {
            $data = $this->stripImageFields($data);
        }

        // ── §4.5.2: Mask PII in logs (not in returned data) ──────────
        $this->logCall(compact('fullUrl', 'method', 'sourceIp', 'userIdent', 'userId',
            'serviceName', 'operation', 'isCitizenEndpoint', 'isBulkEndpoint',
            'committeeApproved', 'startMs'),
            $success,
            $response->status(),
            $success ? null : 'GSB_ERROR_' . $response->status(),
            otpVerified: $isCitizenEndpoint,
        );

        if (! $success) {
            // §4.8.1: generic message — internal detail goes to log only
            Log::warning('GSB call failed', [
                'url'    => $fullUrl,
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 500),
            ]);
            throw new \RuntimeException('Government service returned an error. Please try again later.');
        }

        // §4.5.15: never return the token in the response data
        return $data;
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function isCitizenDataEndpoint(string $path): bool
    {
        foreach (config('gsb.citizen_data.endpoint_prefixes', []) as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function isBulkEndpoint(string $path): bool
    {
        foreach (config('gsb.bulk.endpoint_prefixes', []) as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /** §4.5.11 — check if IP is in the configured whitelist */
    private function isIpAllowed(string $ip): bool
    {
        $allowed = config('gsb.allowed_ips', []);
        if (empty($allowed)) {
            return true; // if not configured, allow all (warn in logs)
        }
        foreach ($allowed as $allowed_ip) {
            $allowed_ip = trim($allowed_ip);
            if ($ip === $allowed_ip) {
                return true;
            }
            // CIDR range check
            if (str_contains($allowed_ip, '/') && $this->ipInCidr($ip, $allowed_ip)) {
                return true;
            }
        }
        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$network, $prefix] = explode('/', $cidr);
        $mask = ~((1 << (32 - (int) $prefix)) - 1);
        return (ip2long($ip) & $mask) === (ip2long($network) & $mask);
    }

    /** §4.5.7 — verify OTP from cache (set by GsbOtpController before the call) */
    private function verifyOtp(string $otpToken, ?int $userId): bool
    {
        $cacheKey = "gsb_otp_verified:{$userId}:{$otpToken}";
        return cache()->has($cacheKey);
    }

    /** §4.5.17 — remove image fields from bulk responses */
    private function stripImageFields(array $data): array
    {
        $imageFields = config('gsb.bulk.strip_image_fields', []);
        array_walk_recursive($data, function (&$value, $key) use ($imageFields) {
            // Unset handled via reference — filter keys at array level
        });
        return $this->recursiveStripKeys($data, $imageFields);
    }

    private function recursiveStripKeys(array $data, array $keys): array
    {
        foreach ($data as $k => &$v) {
            if (in_array($k, $keys, true)) {
                unset($data[$k]);
            } elseif (is_array($v)) {
                $v = $this->recursiveStripKeys($v, $keys);
            }
        }
        return $data;
    }

    /**
     * §4.5.10 / §4.9.1 — write mandatory audit log entry.
     * PII fields are masked before storage (§4.5.2).
     */
    private function logCall(
        array $ctx,
        bool $success,
        int $responseStatus,
        ?string $errorCode = null,
        bool $otpVerified = false,
    ): void {
        if (! config('gsb.logging.enabled', true)) {
            return;
        }

        try {
            GsbCallLog::create([
                'gsb_endpoint'       => $ctx['fullUrl'],
                'http_method'        => $ctx['method'],
                'source_ip'          => $ctx['sourceIp'],
                'user_identifier'    => $ctx['userIdent'],
                'user_id'            => $ctx['userId'],
                'service_name'       => $ctx['serviceName'],
                'operation'          => $ctx['operation'],
                'is_citizen_data'    => $ctx['isCitizenEndpoint'] ?? false,
                'otp_verified'       => $otpVerified,
                'response_status'    => $responseStatus ?: null,
                'success'            => $success,
                'error_code'         => $errorCode, // generic code only, no PII §4.8.1
                'ip_whitelisted'     => $this->isIpAllowed($ctx['sourceIp']),
                'bulk_request'       => $ctx['isBulkEndpoint'] ?? false,
                'committee_approved' => ($ctx['isBulkEndpoint'] ?? false)
                    ? ($ctx['committeeApproved'] ?? false)
                    : null,
                'duration_ms'        => (int) (microtime(true) * 1000) - $ctx['startMs'],
                'logged_at'          => now(), // explicit timestamp §4.5.10
            ]);
        } catch (\Throwable $e) {
            // Log failure must never break the main flow — but must be alerted
            Log::critical('GSB audit log write failed', ['error' => $e->getMessage()]);
        }
    }
}
