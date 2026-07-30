<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nashmi Integration Config
    |--------------------------------------------------------------------------
    |
    | Shared secret used to authenticate inbound webhooks from Nashmi
    | and outbound requests to the Nashmi AI Manager API.
    |
    | Set INTEGRATION_KEY and NASHMI_BASE_URL in your .env file.
    | Never commit actual keys to version control.
    |
    */

    'integration_key'   => env('INTEGRATION_KEY', ''),

    /*
     |--------------------------------------------------------------------------
     | HMAC signing (H-04)
     |--------------------------------------------------------------------------
     |
     | Shared secret used to compute + verify an HMAC-SHA256 signature over
     | the exact RAW request body. Prevents body tampering by any party
     | that only possesses the integration key.
     |
     | Production MUST set NASHMI_SIGNING_SECRET; ProductionSafety aborts
     | boot otherwise. In non-production, if signing_secret is empty the
     | middleware behaves as before (key-only) so local dev is not
     | locked out.
     |
     */
    'signing_secret' => env('NASHMI_SIGNING_SECRET', ''),

    /*
     | Replay window in seconds. A request whose X-Integration-Timestamp
     | header is older than this (or in the future) is rejected as a
     | possible replay. 300 = 5 minutes.
     */
    'replay_window_seconds' => (int) env('NASHMI_REPLAY_WINDOW_SECONDS', 300),

    /*
     | Nonce TTL in seconds. Every valid request's X-Integration-Nonce is
     | stored in the cache for this many seconds; a repeated nonce is
     | rejected as a replay. Should be >= replay_window_seconds.
     */
    'nonce_ttl_seconds' => (int) env('NASHMI_NONCE_TTL_SECONDS', 600),

    /*
     | IP allowlist. Empty = permit all in non-production; empty in
     | production is rejected by the middleware (fail-closed).
     | Accepts a comma-separated env var of dotted-quads or CIDR.
     */
    'allowed_ips' => array_values(array_filter(array_map('trim',
        explode(',', (string) env('NASHMI_ALLOWED_IPS', ''))))),

    'base_url'          => env('NASHMI_BASE_URL', 'https://nashmi.manager.eqratech.com'),

    'organization_id'   => env('NASHMI_ORG_ID', '1'),

    // Timeout in seconds for outbound HTTP calls to Nashmi
    'timeout'           => (int) env('NASHMI_TIMEOUT', 30),

];
