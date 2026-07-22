<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Integrations\Gsb\Http\Controllers\GsbController;

/**
 * GSB adapter routes (Workstream 14).
 *
 * Loaded by GsbServiceProvider::boot(). Every route below used to
 * live in backend/routes/api.php inside the auth:sanctum block.
 * Removing 'gsb' from config/integrations.enabled cleanly removes:
 *   POST /api/v1/gsb/otp/request
 *   POST /api/v1/gsb/otp/verify
 *   GET  /api/v1/gsb/citizen
 *   GET  /api/v1/gsb/audit-logs
 *
 * gsb.ip_whitelist : MODEE §4.5 rule 11 — only authorized IPs may reach GSB routes
 * auth:sanctum     : §4.4.1 — authenticated session required
 * The IP-check middleware is applied INSIDE the auth:sanctum group so
 * unauthenticated requests get a 401 (not a leaky 403).
 */
Route::prefix('api/v1/gsb')
    ->middleware(['auth:sanctum', 'token.inactivity', 'password.policy', 'track.activity', 'gsb.ip_whitelist'])
    ->group(function () {
        // §4.5.7 — Step 1: request OTP for citizen-data access
        Route::post('otp/request', [GsbController::class, 'requestOtp']);

        // §4.5.7 — Step 2: verify OTP; returns short-lived otp_token
        Route::post('otp/verify',  [GsbController::class, 'verifyOtp']);

        // §4.5.7 — Citizen data lookup (requires otp_token from step 2)
        Route::get('citizen',      [GsbController::class, 'citizenLookup']);

        // §4.9 — GSB call audit log viewer (admin-only enforcement inside controller)
        Route::get('audit-logs',   [GsbController::class, 'auditLogs']);
    });
