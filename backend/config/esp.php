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

];
