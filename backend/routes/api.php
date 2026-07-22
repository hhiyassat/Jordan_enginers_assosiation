<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AuthController;
// Workstream 8A: EngineerController + ProjectController + OfficeSettingsController
// moved to Modules\JeaProjects.
// Workstream 8C: ApplicationController + ReviewDashboardController +
// ReviewQueueController + PaymentsController + CertificatesController +
// ServiceCatalogController + ServiceFeesController moved to Modules\JeaServices.
// Workstream 13: AiSchemaController + CaptchaController moved to
// Plugins\AiSchema and Plugins\Captcha. GET /captcha now lives in the
// captcha plugin's routes.php; the 'captcha' middleware alias is
// registered by the captcha plugin's service provider.
use App\Http\Controllers\Api\GsbController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\UserManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ESP v2 API Routes
|--------------------------------------------------------------------------
|
| SEC-009: Rate limiting applied at route level.
| SEC-005: CheckRole middleware enforced on all protected routes.
| BUILD CONTRACT: Middleware stack wired before all routes.
|
| All routes versioned under /v1/ (§10.2 API versioning).
|
*/

// ── Nashmi Integration routes (NO Sanctum — validated by X-Integration-Key) ─
// IMPORTANT: registered BEFORE the v1 group so Sanctum never intercepts them.

Route::prefix('integration')
    ->middleware(['integration.key', 'throttle:integration'])
    ->group(function () {
        // Inbound from Nashmi
        Route::post('receive-requirements',             [IntegrationController::class, 'receiveRequirements']);
        Route::post('receive-feedback',                 [IntegrationController::class, 'receiveFeedback']);

        // Outbound trigger (admin calls this after code is done)
        Route::post('cycles/{id}/notify-done',          [IntegrationController::class, 'notifyCodeDone']);

        // Cycle management (read-only from Nashmi side)
        Route::get('cycles',                            [IntegrationController::class, 'cycles']);
        Route::get('cycles/{id}',                       [IntegrationController::class, 'cycle']);
        Route::get('cycles/{id}/pdf',                   [IntegrationController::class, 'downloadPdf']);
    });

// ── Public routes (no auth) ─────────────────────────────────────────

Route::prefix('v1')->group(function () {

    // Workstream 13: GET /captcha moved to Plugins\Captcha\routes.php.
    // Removing 'captcha' from config/plugins.enabled drops the route
    // AND the 'captcha' middleware alias below.

    // SEC-009: named limiters registered in AppServiceProvider::registerRateLimiters().
    // Each named limiter has a custom response callback that logs the trip
    // to the security channel and returns an Arabic 429 body the SPA can render.
    Route::post('auth/login',    [AuthController::class, 'login'])->middleware(['throttle:login', 'captcha']);
    Route::post('auth/register', [AuthController::class, 'register'])->middleware(['throttle:register', 'captcha']);

    // JORD-84 (PM): identity probe. Returns {user: null} when there's
    // no valid session cookie/token, so a blind first-load call
    // doesn't paint a red 401 in the browser console. The controller
    // resolves the user through Sanctum's guard manually since this
    // route intentionally sits outside auth:sanctum.
    Route::get('auth/me',                          [AuthController::class, 'me']);

    // Workstream 8C: public certificate verification + PDF download
    // moved to the jea-services module (backend/modules/JeaServices/
    // routes.php — top-level public group).

});

// ── Authenticated routes ────────────────────────────────────────────

Route::prefix('v1')->middleware(['auth:sanctum', 'token.inactivity', 'password.policy', 'track.activity'])->group(function () {

    // Auth
    // NOTE: GET /auth/me lives OUTSIDE the auth:sanctum group (see the
    // public routes block above) so an unauthenticated first-load probe
    // returns 200 + {user: null} instead of a 401 that surfaces as a
    // red row in the browser console (JORD-84 PM). PATCH /auth/me
    // stays protected — profile updates require an active session.
    Route::post('auth/logout',             [AuthController::class, 'logout']);
    Route::post('auth/password/change',    [AuthController::class, 'changePassword']);
    // JORD-10: user updates their own profile (name + phone).
    Route::patch('auth/me',                [AuthController::class, 'updateProfile']);

    // JORD-9: notification inbox (per-authenticated-user scoped).
    Route::get ('notifications',                    [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::get ('notifications/unread-count',       [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
    Route::post('notifications/{id}/read',          [\App\Http\Controllers\Api\NotificationController::class, 'markRead'])
        ->whereNumber('id');
    Route::post('notifications/read-all',           [\App\Http\Controllers\Api\NotificationController::class, 'markAllRead']);

    // Workstream 8C: applicant service catalog + application CRUD +
    // reviewer surface (dashboard, queue, claim, decide) + staff/admin
    // payment-confirm + certificate-issue moved to the jea-services
    // module (backend/modules/JeaServices/routes.php). Removing
    // 'jea-services' from config/modules.enabled drops the entire
    // application flow.

    // ── Admin-only routes ─────────────────────────────────────────────

    Route::middleware('role:admin,superuser')->group(function () {
        // FR-014 to FR-016: Admin dashboard
        // Workstream 5C: dashboard + applications + audit-logs extracted
        // from AdminController.
        Route::get('admin/dashboard',             [AdminDashboardController::class, 'dashboard']);
        // User CRUD moved to the superuser role — see the role:superuser
        // block further down. Admin keeps read-only visibility via dashboard
        // stats but no longer touches the user roster.
        Route::get('admin/applications',          [AdminDashboardController::class, 'allApplications']);
        Route::get('admin/audit-logs',            [AdminDashboardController::class, 'auditLogs']);

        // Workstream 8C: admin service catalog + fee editor + lock/unlock
        // routes moved to the jea-services module.

        // Workstream 13: FR-018/019 Claude AI schema endpoints moved to
        // the ai-schema plugin (backend/plugins/AiSchema/routes.php).
        // Removing 'ai-schema' from config/plugins.enabled drops all
        // three /admin/services/*-schema routes.

        // Workstream 8A: office-scoped boost flags + specialization-head
        // routes moved to the jea-projects module.

        // JORD-79: recurring obligations (F-04 registration + F-05
        // annual dues + 15%/30% late surcharge).
        // Workstream 7: all three admin dues routes moved to the
        // jea-dues module (backend/modules/JeaDues/routes.php).
        // Removing 'jea-dues' from config/modules.enabled makes them
        // disappear entirely.

        // Workstream 8B: JORD-81/82/83 admin discipline surface
        // (complaints decide, legal fines, supervision transfers) moved
        // to the jea-discipline module (backend/modules/JeaDiscipline/
        // routes.php). Removing 'jea-discipline' from
        // config/modules.enabled drops all three admin blocks entirely.
    });

    // ── User management ─────────────────────────────────────────────────
    // Admin can add/edit/delete applicant, staff, and auditor accounts.
    // Superuser can additionally manage admin and other superuser accounts.
    // The tier boundary is enforced inside the controller via
    // User::canManageRole() so a request that leaked past this middleware
    // still can't cross it.
    Route::middleware('role:admin,superuser')->group(function () {
        Route::get('admin/users',            [UserManagementController::class, 'index']);
        Route::post('admin/users',           [UserManagementController::class, 'store']);
        Route::put('admin/users/{id}',       [UserManagementController::class, 'update']);
        Route::delete('admin/users/{id}',    [UserManagementController::class, 'destroy']);
    });

    // ── GSB (Government Service Bus) routes — MODEE Annex 4.15 ──────────
    //
    // gsb.ip_whitelist : §4.5 rule 11 — only authorized IPs may reach GSB routes
    // auth:sanctum     : §4.4.1 — authenticated session required
    // throttle         : §4.7  — rate limiting (100 req/min per IP)
    //
    // Note: gsb.ip_whitelist is applied INSIDE the outer auth:sanctum group so that
    // the Sanctum middleware still runs first; unauthenticated requests never reach
    // the IP check and get the standard 401 response instead of the GSB 403.

    Route::prefix('v1/gsb')
        ->middleware('gsb.ip_whitelist')
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

});
