<?php

namespace Integrations\Nashmi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * ValidateIntegrationKey
 *
 * H-04 Inbound Security for Nashmi Integration:
 * - IP allowlist (fails closed in production if unconfigured or IP non-matching).
 * - Integration Key check via hash_equals.
 * - Timestamp replay window check.
 * - Nonce store for replay prevention.
 * - HMAC-SHA256 signature verification over exact raw request body.
 */
class ValidateIntegrationKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $isProduction = app()->environment('production');

        // 1. IP Allowlist Enforcement
        $allowedIps = (array) config('nashmi.allowed_ips', []);
        if ($isProduction && empty($allowedIps)) {
            Log::channel('integration')->critical('Nashmi IP allowlist is empty in production', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
            return response()->json(['message' => 'Forbidden: Unconfigured IP allowlist.'], 403);
        }

        if (! empty($allowedIps) && ! in_array($request->ip(), $allowedIps, true)) {
            Log::channel('integration')->warning('Nashmi IP rejected', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
            return response()->json(['message' => 'Forbidden IP.'], 403);
        }

        // 2. Integration Key Check
        $providedKey = $request->header('X-Integration-Key') ?? $request->input('integration_key', '');
        $expectedKey = config('nashmi.integration_key', '');

        if (empty($expectedKey) || ! hash_equals($expectedKey, (string) $providedKey)) {
            Log::channel('integration')->warning('Integration key rejected', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        // 3. HMAC Signature & Replay Prevention
        $signingSecret = (string) config('nashmi.signing_secret', '');
        if (! empty($signingSecret) || $isProduction) {
            if (empty($signingSecret) && $isProduction) {
                Log::channel('integration')->critical('Nashmi signing secret missing in production');
                return response()->json(['message' => 'Server Configuration Error.'], 500);
            }

            // Timestamp check
            $timestampHeader = $request->header('X-Nashmi-Timestamp')
                ?? $request->header('X-Integration-Timestamp');

            if (! $timestampHeader || ! is_numeric($timestampHeader)) {
                Log::channel('integration')->warning('Missing or invalid Nashmi timestamp header', [
                    'ip' => $request->ip(),
                ]);
                return response()->json(['message' => 'Missing or invalid timestamp.'], 401);
            }

            $timestamp = (int) $timestampHeader;
            $replayWindow = (int) config('nashmi.replay_window_seconds', 300);
            if (abs(time() - $timestamp) > $replayWindow) {
                Log::channel('integration')->warning('Nashmi timestamp outside replay window', [
                    'ip' => $request->ip(),
                    'timestamp' => $timestamp,
                    'current_time' => time(),
                ]);
                return response()->json(['message' => 'Request timestamp outside allowed window.'], 401);
            }

            // Nonce deduplication
            $nonce = $request->header('X-Nashmi-Nonce')
                ?? $request->header('X-Integration-Nonce')
                ?? $request->header('X-Request-Id');

            if ($nonce) {
                $cacheKey = "nashmi:nonce:" . md5((string) $nonce);
                $nonceTtl = (int) config('nashmi.nonce_ttl_seconds', 600);

                if (Cache::has($cacheKey)) {
                    Log::channel('integration')->warning('Nashmi replayed nonce detected', [
                        'ip' => $request->ip(),
                        'nonce' => $nonce,
                    ]);
                    return response()->json(['message' => 'Replay attempt detected.'], 401);
                }

                Cache::put($cacheKey, true, $nonceTtl);
            }

            // HMAC Signature comparison over raw body
            $signatureHeader = $request->header('X-Nashmi-Signature')
                ?? $request->header('X-Integration-Signature');

            if (! $signatureHeader) {
                Log::channel('integration')->warning('Missing Nashmi signature header', ['ip' => $request->ip()]);
                return response()->json(['message' => 'Missing signature header.'], 401);
            }

            $rawContent = $request->getContent();
            $expectedSignature = hash_hmac('sha256', $rawContent, $signingSecret);

            if (! hash_equals($expectedSignature, (string) $signatureHeader)) {
                Log::channel('integration')->warning('Nashmi HMAC signature mismatch', [
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                ]);
                return response()->json(['message' => 'Invalid signature.'], 401);
            }
        }

        Log::channel('integration')->info('Nashmi request authenticated successfully', [
            'ip' => $request->ip(),
            'path' => $request->path(),
        ]);

        return $next($request);
    }
}
