<?php

namespace Integrations\Nashmi\Http\Middleware;

use App\Support\SecurityEvents;
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
            SecurityEvents::integrationSignatureFailure($request, 'integration_key_mismatch');
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
                SecurityEvents::integrationSignatureFailure($request, 'missing_or_invalid_timestamp');
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
                SecurityEvents::integrationSignatureFailure($request, 'timestamp_outside_window', [
                    'skew_seconds' => abs(time() - $timestamp),
                    'window'       => $replayWindow,
                ]);
                return response()->json(['message' => 'Request timestamp outside allowed window.'], 401);
            }

            // Nonce enforcement + deduplication.
            //
            // CS-07 (2026-07-31): nonce is REQUIRED in production. Missing
            // nonce in prod → 401. Non-prod still tolerates omission so
            // local dev + the existing test fixtures keep working. Storage
            // uses Cache::add() (atomic put-if-missing) so two concurrent
            // requests carrying the same nonce cannot both slip past —
            // exactly one wins and the loser gets the replay 401.
            $nonce = $request->header('X-Nashmi-Nonce')
                ?? $request->header('X-Integration-Nonce')
                ?? $request->header('X-Request-Id');

            if ($isProduction && ($nonce === null || $nonce === '')) {
                Log::channel('integration')->warning('Nashmi missing nonce in production', [
                    'ip'   => $request->ip(),
                    'path' => $request->path(),
                ]);
                SecurityEvents::integrationSignatureFailure($request, 'missing_nonce_in_production');
                return response()->json(['message' => 'Missing nonce header (required in production).'], 401);
            }

            if ($nonce !== null && $nonce !== '') {
                // Namespace the cache key by signing-secret fingerprint so
                // rotating the secret invalidates the old nonce set — a
                // replay under the previous secret cannot survive a key
                // rotation.
                $secretFingerprint = substr(hash('sha256', $signingSecret), 0, 12);
                $cacheKey = "nashmi:nonce:{$secretFingerprint}:" . hash('sha256', (string) $nonce);
                $nonceTtl = (int) config('nashmi.nonce_ttl_seconds', 600);

                // Atomic put-if-missing: returns true if the key was newly
                // set, false if a concurrent request already claimed it.
                if (! Cache::add($cacheKey, true, $nonceTtl)) {
                    Log::channel('integration')->warning('Nashmi replayed nonce detected', [
                        'ip'    => $request->ip(),
                        'nonce' => (string) $nonce,
                    ]);
                    SecurityEvents::integrationSignatureFailure($request, 'nonce_replay');
                    return response()->json(['message' => 'Replay attempt detected.'], 401);
                }
            }

            // HMAC Signature comparison over raw body
            $signatureHeader = $request->header('X-Nashmi-Signature')
                ?? $request->header('X-Integration-Signature');

            if (! $signatureHeader) {
                Log::channel('integration')->warning('Missing Nashmi signature header', ['ip' => $request->ip()]);
                SecurityEvents::integrationSignatureFailure($request, 'missing_signature_header');
                return response()->json(['message' => 'Missing signature header.'], 401);
            }

            $rawContent = $request->getContent();
            $expectedSignature = hash_hmac('sha256', $rawContent, $signingSecret);

            if (! hash_equals($expectedSignature, (string) $signatureHeader)) {
                Log::channel('integration')->warning('Nashmi HMAC signature mismatch', [
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                ]);
                SecurityEvents::integrationSignatureFailure($request, 'hmac_mismatch');
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
