<?php

/**
 * GSB (Government Service Bus) Configuration
 * MODEE Annex 4.15 API Security Policy
 *
 * Set these values in .env — never hardcode credentials here.
 */
return [

    // ── GSB Connection ────────────────────────────────────────────────
    'base_url'    => env('GSB_BASE_URL', 'https://gsb.gov.jo/api'),
    'timeout'     => (int) env('GSB_TIMEOUT', 30),

    // ── Authentication §4.4.1 ─────────────────────────────────────────
    // GSB uses OAuth 2.0 client credentials flow
    'oauth' => [
        'token_url'     => env('GSB_TOKEN_URL', 'https://gsb.gov.jo/oauth/token'),
        'client_id'     => env('GSB_CLIENT_ID'),
        'client_secret' => env('GSB_CLIENT_SECRET'),
        'scope'         => env('GSB_SCOPE', 'gsb.read gsb.write'),
        // Short-lived tokens §4.4.3 — cache TTL slightly less than actual expiry
        'token_cache_ttl' => (int) env('GSB_TOKEN_TTL', 840), // 14 min (token expires at 15)
    ],

    // ── Citizen Data §4.5 rule 7 ──────────────────────────────────────
    // Endpoints that return citizen PII require 3-factor auth
    'citizen_data' => [
        // Path prefixes that are considered "citizen data" endpoints
        'endpoint_prefixes' => [
            '/citizens',
            '/national-id',
            '/civil-registry',
            '/population',
        ],
        // OTP TTL in seconds
        'otp_ttl' => (int) env('GSB_OTP_TTL', 300), // 5 minutes
    ],

    // ── IP Whitelist §4.5 rule 11 ─────────────────────────────────────
    // Only these IPs may call GSB-dependent endpoints.
    // Add all authorized source IPs/CIDR ranges in .env as comma-separated list.
    'allowed_ips' => array_filter(
        explode(',', env('GSB_ALLOWED_IPS', '127.0.0.1,::1')),
        fn ($ip) => trim($ip) !== ''
    ),

    // ── Rate Limiting §4.7 ────────────────────────────────────────────
    'rate_limit' => [
        'max_requests'  => (int) env('GSB_RATE_LIMIT_MAX', 100),
        'per_minutes'   => (int) env('GSB_RATE_LIMIT_WINDOW', 1),
    ],

    // ── Logging §4.5 rule 10 / §4.9 ──────────────────────────────────
    'logging' => [
        'enabled'             => (bool) env('GSB_LOGGING_ENABLED', true),
        'retention_days'      => (int) env('GSB_LOG_RETENTION_DAYS', 180), // §4.9.3
        // Fields that must NEVER appear in logs (PII masking §4.5 rule 2)
        'masked_fields'       => ['national_id', 'password', 'otp', 'token', 'dob', 'mother_name'],
    ],

    // ── Bulk Data §4.5 rules 16-17 ───────────────────────────────────
    'bulk' => [
        // Endpoint path prefixes treated as bulk data requests
        'endpoint_prefixes'  => ['/bulk', '/export', '/batch'],
        // Bulk requests are blocked unless committee_approved is true in the request
        'require_committee_approval' => true,
        // Bulk responses must not include image fields
        'strip_image_fields' => ['photo', 'image', 'picture', 'scan', 'face'],
    ],

    // ── TLS §4.5 rule 1 ──────────────────────────────────────────────
    // Minimum TLS version enforced by the GsbClient — Laravel Http facade
    // uses PHP's OpenSSL which inherits system settings, but we set CURLOPT_SSLVERSION.
    'tls_version' => env('GSB_TLS_VERSION', CURL_SSLVERSION_TLSv1_2),
];
