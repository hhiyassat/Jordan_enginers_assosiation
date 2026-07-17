<?php

/**
 * ESP v2 — Platform Configuration
 *
 * §4.3-B Derivable rules: all thresholds here are Derivable (configurable without code change).
 * Fixed-by-Rule constants live in WorkflowEngine::ALLOWED_TRANSITIONS and Application status constants.
 */

return [

    // Session management (SEC-003)
    'session_timeout_minutes' => (int) env('SESSION_TIMEOUT_MINUTES', 30),

    // Password policy (SEC-004, SEC-012)
    'password_expiry_days' => (int) env('PASSWORD_EXPIRY_DAYS', 90),

    // SLA configuration (WF-008)
    'default_sla_hours' => (int) env('DEFAULT_SLA_HOURS', 48),

    // File upload limits (SEC-008)
    'max_upload_size_mb' => (int) env('MAX_UPLOAD_SIZE_MB', 10),

    // Rate limiting (SEC-009)
    'rate_limit_login'  => (int) env('RATE_LIMIT_LOGIN', 5),   // per minute
    'rate_limit_api'    => (int) env('RATE_LIMIT_API', 120),    // per minute

    // API read SLO (NFR-001) — LogApiAccess middleware emits `slow_request`
    // warnings when GET/HEAD requests exceed this (ms).
    'slow_request_ms' => (int) env('SLOW_REQUEST_MS', 500),

    // Audit log retention (NFR-006) — audit:prune command deletes rows
    // older than now()->subYears(this).
    'audit_retention_years' => (int) env('AUDIT_LOG_RETENTION_YEARS', 7),

    // Text captcha — see App\Services\CaptchaService and app/Http/Middleware/VerifyCaptcha.
    // Toggle CAPTCHA_ENABLED=false in dev/tests to bypass.
    'captcha_enabled'     => filter_var(env('CAPTCHA_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'captcha_ttl_minutes' => (int) env('CAPTCHA_TTL_MINUTES', 5),

];
