<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\JeaServices\Http\Controllers\ApplicationController;
use Modules\JeaServices\Http\Controllers\CertificatesController;
use Modules\JeaServices\Http\Controllers\PaymentsController;
use Modules\JeaServices\Http\Controllers\ReviewDashboardController;
use Modules\JeaServices\Http\Controllers\ReviewQueueController;
use Modules\JeaServices\Http\Controllers\ServiceCatalogController;
use Modules\JeaServices\Http\Controllers\ServiceFeesController;

/**
 * jea-services module routes (Workstream 8C).
 *
 * Loaded by JeaServicesServiceProvider::boot(). Every route below
 * used to live in backend/routes/api.php. Removing 'jea-services'
 * from config/modules.enabled cleanly removes:
 *
 *   Public (no auth):
 *     GET  /api/v1/certificates/verify/{certNumber}
 *     GET  /api/v1/certificates/{certNumber}/pdf
 *
 *   Applicant (role: applicant/staff/auditor/admin):
 *     GET  /api/v1/services
 *     GET  /api/v1/services/{code}
 *     GET  /api/v1/applications
 *     POST /api/v1/applications
 *     GET  /api/v1/applications/{id}
 *     PUT  /api/v1/applications/{id}
 *     POST /api/v1/applications/{id}/submit
 *     POST /api/v1/applications/{id}/documents
 *
 *   Reviewer (role: staff/auditor/admin):
 *     GET  /api/v1/review/dashboard
 *     GET  /api/v1/review/queue
 *     POST /api/v1/applications/{id}/claim
 *     POST /api/v1/applications/{id}/decide
 *
 *   Staff/Admin (role: staff/admin):
 *     POST /api/v1/applications/{id}/confirm-payment
 *     POST /api/v1/applications/{id}/issue-certificate
 *
 *   Admin (role: admin/superuser) — service catalog admin:
 *     GET   /api/v1/admin/services
 *     GET   /api/v1/admin/services/{id}
 *     POST  /api/v1/services
 *     PUT   /api/v1/services/{id}
 *     PATCH /api/v1/services/{id}/status
 *     GET   /api/v1/admin/service-fees
 *     PATCH /api/v1/admin/services/{id}/fee
 *     POST  /api/v1/admin/services/{id}/lock
 *     POST  /api/v1/admin/services/{id}/unlock
 */

// ── Public certificate verification (no auth) ─────────────────────
Route::prefix('api/v1')->group(function () {
    // FR-013: public certificate verification
    Route::get('certificates/verify/{certNumber}', [CertificatesController::class, 'verify']);

    // Signed-URL PDF — public but token-gated inside the controller
    // (applicants get the URL from the app-detail endpoint; third
    // parties get the token from the printed certificate QR).
    Route::get('certificates/{certNumber}/pdf', [CertificatesController::class, 'downloadPdf']);
});

// ── Authenticated surface ─────────────────────────────────────────
Route::prefix('api/v1')->middleware(['auth:sanctum', 'token.inactivity', 'password.policy', 'track.activity'])->group(function () {

    // Applicant surface — service catalog + applications CRUD.
    Route::middleware('role:applicant,staff,auditor,admin')->group(function () {
        // FR-001: service catalog
        Route::get('services',         [ServiceCatalogController::class, 'index']);
        Route::get('services/{code}',  [ServiceCatalogController::class, 'show']);

        // FR-002 to FR-007: application CRUD
        Route::get ('applications',                            [ApplicationController::class, 'index']);
        Route::post('applications',                            [ApplicationController::class, 'store']);
        Route::get ('applications/{id}',                       [ApplicationController::class, 'show']);
        Route::put ('applications/{id}',                       [ApplicationController::class, 'update']);
        Route::post('applications/{id}/submit',                [ApplicationController::class, 'submit']);
        Route::post('applications/{id}/documents',             [ApplicationController::class, 'uploadDocument'])
            ->middleware('throttle:document-upload');
    });

    // Reviewer surface (staff/auditor/admin).
    Route::middleware('role:staff,auditor,admin')->group(function () {
        // Workstream 5B: reviewer surface split from ApplicationController.
        Route::get ('review/dashboard',            [ReviewDashboardController::class, 'show']);
        Route::get ('review/queue',                [ReviewQueueController::class, 'index']);
        Route::post('applications/{id}/claim',     [ReviewQueueController::class, 'claim']);
        Route::post('applications/{id}/decide',    [ReviewQueueController::class, 'decide']);
    });

    // Staff+admin (payment confirmation + certificate issuance).
    Route::middleware('role:staff,admin')->group(function () {
        Route::post('applications/{id}/confirm-payment',    [PaymentsController::class, 'confirm']);
        Route::post('applications/{id}/issue-certificate',  [CertificatesController::class, 'issue']);
    });

    // Admin surface — service catalog admin, fee editor, lock/unlock.
    Route::middleware('role:admin,superuser')->group(function () {
        // FR-017: admin views all services regardless of status.
        Route::get  ('admin/services',                       [ServiceCatalogController::class, 'adminIndex']);
        Route::get  ('admin/services/{id}',                  [ServiceCatalogController::class, 'adminShow']);
        Route::post ('services',                             [ServiceCatalogController::class, 'store']);
        Route::put  ('services/{id}',                        [ServiceCatalogController::class, 'update']);
        Route::patch('services/{id}/status',                 [ServiceCatalogController::class, 'updateStatus']);

        // JORD-85: focused fee editor.
        Route::get  ('admin/service-fees',                   [ServiceFeesController::class, 'index']);
        Route::patch('admin/services/{id}/fee',              [ServiceFeesController::class, 'update']);

        // Lock/unlock — explicit action, separate from ordinary edits.
        Route::post ('admin/services/{id}/lock',             [ServiceCatalogController::class, 'lock']);
        Route::post ('admin/services/{id}/unlock',           [ServiceCatalogController::class, 'unlock']);
    });
});
