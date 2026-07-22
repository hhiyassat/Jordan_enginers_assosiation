<?php

namespace Integrations\Gsb\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GSB OAuth 2.0 Token Manager
 * MODEE Annex 4.15 §4.4.1 — OAuth 2.0 authentication
 * MODEE Annex 4.15 §4.4.3 — short-lived tokens, securely stored
 *
 * Manages client-credentials token lifecycle for GSB API calls.
 * Tokens are cached in Laravel Cache (not in DB or client) and never
 * logged or returned to end-users.
 */
class GsbAuthManager
{
    private const CACHE_KEY = 'gsb_oauth_access_token';

    /**
     * Return a valid Bearer token for GSB API calls.
     * Fetches a new token when the cached one is missing or near expiry.
     */
    public function getAccessToken(): string
    {
        // §4.4.3: securely stored — Laravel cache (Redis/file), never in code or DB
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached) {
            return $cached;
        }

        return $this->fetchNewToken();
    }

    /**
     * Force refresh — call after a 401 from GSB.
     */
    public function refresh(): string
    {
        Cache::forget(self::CACHE_KEY);
        return $this->fetchNewToken();
    }

    // ── Private ────────────────────────────────────────────────────────

    private function fetchNewToken(): string
    {
        $cfg = config('gsb.oauth');

        $response = Http::timeout(10)
            ->asForm()
            ->post($cfg['token_url'], [
                'grant_type'    => 'client_credentials',
                'client_id'     => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'scope'         => $cfg['scope'],
            ]);

        if ($response->failed()) {
            // §4.8.1: log detail internally, never expose to caller
            Log::error('GSB OAuth token fetch failed', [
                'status' => $response->status(),
                'error'  => $response->json('error'),
            ]);
            throw new \RuntimeException('GSB authentication unavailable.');
        }

        $token = $response->json('access_token');
        $ttl   = $cfg['token_cache_ttl']; // §4.4.3: short expiry

        // §4.15: encryption keys must never travel with encrypted data —
        // token is stored in cache only, not returned in API responses.
        Cache::put(self::CACHE_KEY, $token, $ttl);

        return $token;
    }
}
