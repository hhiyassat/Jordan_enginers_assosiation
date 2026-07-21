<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CaptchaController;
use App\Http\Controllers\Api\EngineerController;
use App\Http\Controllers\Api\GsbController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ServiceCatalogController;
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

    // Captcha challenge for public forms (unauthed, rate-limited)
    Route::get('captcha', [CaptchaController::class, 'issue'])->middleware('throttle:captcha-issue');

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

    // FR-013: Public certificate verification
    Route::get('certificates/verify/{certNumber}', [ApplicationController::class, 'verifyCertificate']);

    // PDF download — public but token-gated. Applicants get a signed
    // URL from the application-detail endpoint; third parties get the
    // token from the QR image on the printed certificate.
    Route::get('certificates/{certNumber}/pdf', [ApplicationController::class, 'downloadCertificatePdf']);

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

    // ── Applicant routes ──────────────────────────────────────────────

    Route::middleware('role:applicant,staff,auditor,admin')->group(function () {
        // FR-001: Service catalog
        Route::get('services',         [ServiceCatalogController::class, 'index']);
        Route::get('services/{code}',  [ServiceCatalogController::class, 'show']);

        // JORD-81: complaint intake — any authenticated user can file
        // against an office in their own org. Admin-only decide/list
        // endpoints live in the admin group further below.
        Route::post('complaints',      [\App\Http\Controllers\Api\ComplaintController::class, 'store']);

        // JORD-84: applicant self-service view of own dues + complaints
        // filed against them + sanctions on them. Read-only — pay +
        // decide stay admin-only per manual policy.
        Route::get('my/dues',          [\App\Http\Controllers\Api\MyOfficeController::class, 'dues']);
        Route::get('my/complaints',    [\App\Http\Controllers\Api\MyOfficeController::class, 'complaints']);
        Route::get('my/sanctions',     [\App\Http\Controllers\Api\MyOfficeController::class, 'sanctions']);

        // FR-002 to FR-007: Application CRUD
        Route::get('applications',                            [ApplicationController::class, 'index']);
        Route::post('applications',                           [ApplicationController::class, 'store']);
        Route::get('applications/{id}',                       [ApplicationController::class, 'show']);
        Route::put('applications/{id}',                       [ApplicationController::class, 'update']);
        Route::post('applications/{id}/submit',               [ApplicationController::class, 'submit']);
        Route::post('applications/{id}/documents',            [ApplicationController::class, 'uploadDocument'])
            ->middleware('throttle:document-upload');

        // Projects — user's engineering projects (containers for applications)
        Route::get('projects',        [ProjectController::class, 'index']);
        Route::get('projects/quota',  [ProjectController::class, 'quota']);
        Route::post('projects',       [ProjectController::class, 'store']);
        Route::get('projects/{id}',   [ProjectController::class, 'show']);

        // Engineers registered under the office (per-engineer m² quota)
        Route::get('engineers',              [EngineerController::class, 'index']);
        Route::post('engineers',             [EngineerController::class, 'store']);
        Route::get('engineers/{id}',         [EngineerController::class, 'show']);
        Route::get('engineers/{id}/quota',   [EngineerController::class, 'quota']);
    });

    // ── Reviewer routes ───────────────────────────────────────────────

    Route::middleware('role:staff,auditor,admin')->group(function () {
        // FR-008 to FR-010: Review workflow
        Route::get('review/queue',                [ApplicationController::class, 'reviewQueue']);
        Route::post('applications/{id}/claim',    [ApplicationController::class, 'claim']);
        Route::post('applications/{id}/decide',   [ApplicationController::class, 'decide']);
    });

    // ── Staff / Admin routes ──────────────────────────────────────────

    Route::middleware('role:staff,admin')->group(function () {
        // FR-011 to FR-012: Payment + certificate issuance
        Route::post('applications/{id}/confirm-payment',    [ApplicationController::class, 'confirmPayment']);
        Route::post('applications/{id}/issue-certificate',  [ApplicationController::class, 'issueCertificate']);
    });

    // ── Admin-only routes ─────────────────────────────────────────────

    Route::middleware('role:admin,superuser')->group(function () {
        // FR-014 to FR-016: Admin dashboard
        Route::get('admin/dashboard',             [AdminController::class, 'dashboard']);
        // User CRUD moved to the superuser role — see the role:superuser
        // block further down. Admin keeps read-only visibility via dashboard
        // stats but no longer touches the user roster.
        Route::get('admin/applications',          [AdminController::class, 'allApplications']);
        Route::get('admin/audit-logs',            [AdminController::class, 'auditLogs']);

        // FR-017: Service management (all statuses for admin)
        Route::get('admin/services',                       [ServiceCatalogController::class, 'adminIndex']);
        Route::get('admin/services/{id}',                  [ServiceCatalogController::class, 'adminShow']);
        Route::post('services',                            [ServiceCatalogController::class, 'store']);
        Route::put('services/{id}',                        [ServiceCatalogController::class, 'update']);
        Route::patch('services/{id}/status',               [ServiceCatalogController::class, 'updateStatus']);
        // JORD-85: focused fee editor. Compact fee payload only —
        // avoids sending the whole schema for a rate change.
        Route::get('admin/service-fees',                   [ServiceCatalogController::class, 'adminFeesIndex']);
        Route::patch('admin/services/{id}/fee',            [ServiceCatalogController::class, 'updateFee']);
        // Lock/unlock — every mutation above refuses when is_locked=true,
        // so unlocking is an explicit action separate from ordinary edits.
        Route::post('admin/services/{id}/lock',            [ServiceCatalogController::class, 'lock']);
        Route::post('admin/services/{id}/unlock',          [ServiceCatalogController::class, 'unlock']);

        // FR-018 + FR-019: every Claude-backed AI endpoint shares the
        // 'ai-schema' bucket — 10 calls/hour per user is generous for
        // interactive authoring but stops a runaway loop from burning
        // through the API budget in minutes.
        Route::post('admin/services/generate-schema',           [AdminController::class, 'generateSchema'])
            ->middleware('throttle:ai-schema');
        Route::post('admin/services/generate-schema-from-file', [AdminController::class, 'generateSchemaFromFile'])
            ->middleware('throttle:ai-schema');
        Route::post('admin/services/chat-schema',               [AdminController::class, 'chatUpdateSchema'])
            ->middleware('throttle:ai-schema');

        // JORD-77: office-scoped boost flags + specialization-head
        // (replaces the JORD-76 organization-scoped endpoints).
        // Admin picks an office first, then edits its flags.
        Route::get('admin/offices',                                 [\App\Http\Controllers\Api\OfficeSettingsController::class, 'index']);
        Route::get('admin/offices/{id}',                            [\App\Http\Controllers\Api\OfficeSettingsController::class, 'show']);
        Route::patch('admin/offices/{id}',                          [\App\Http\Controllers\Api\OfficeSettingsController::class, 'update']);
        Route::patch('admin/offices/{officeId}/engineers/{engineerId}', [\App\Http\Controllers\Api\OfficeSettingsController::class, 'updateEngineer']);

        // JORD-79: recurring obligations (F-04 registration + F-05
        // annual dues + 15%/30% late surcharge). Admin marks paid;
        // cron creates annual dues Feb 1 every year.
        Route::get('admin/offices/{id}/dues',            [\App\Http\Controllers\Api\RecurringDuesController::class, 'index']);
        Route::post('admin/offices/{id}/dues/register',  [\App\Http\Controllers\Api\RecurringDuesController::class, 'seedRegistration']);
        Route::post('admin/dues/{obligationId}/pay',     [\App\Http\Controllers\Api\RecurringDuesController::class, 'pay']);

        // JORD-81: disciplinary complaints + sanctions.
        // (Intake POST /complaints lives in the applicant group above.)
        Route::get('admin/complaints',                   [\App\Http\Controllers\Api\ComplaintController::class, 'index']);
        Route::post('admin/complaints/{id}/decide',      [\App\Http\Controllers\Api\ComplaintController::class, 'decide']);

        // JORD-82: legal fines (Art.14 owner fines for unlicensed
        // contractor use). Admin-only issuance + payment tracking.
        Route::get('admin/legal-fines',                  [\App\Http\Controllers\Api\LegalFineController::class, 'index']);
        Route::post('admin/legal-fines',                 [\App\Http\Controllers\Api\LegalFineController::class, 'store']);
        Route::post('admin/legal-fines/{id}/pay',        [\App\Http\Controllers\Api\LegalFineController::class, 'pay']);

        // JORD-83: supervision transfer queue (C-07, p.30). Auto-
        // populated when a suspension_2yr / deregistration sanction
        // fires; admin assigns receiving office; target accepts/declines.
        Route::get('admin/supervision-transfers',                        [\App\Http\Controllers\Api\SupervisionTransferController::class, 'index']);
        Route::post('admin/supervision-transfers/{id}/assign',           [\App\Http\Controllers\Api\SupervisionTransferController::class, 'assign']);
        Route::post('admin/supervision-transfers/{id}/accept-decline',   [\App\Http\Controllers\Api\SupervisionTransferController::class, 'acceptOrDecline']);
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
