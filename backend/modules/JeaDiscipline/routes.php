<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\JeaDiscipline\Http\Controllers\ComplaintController;
use Modules\JeaDiscipline\Http\Controllers\LegalFineController;
use Modules\JeaDiscipline\Http\Controllers\MyDisciplineController;
use Modules\JeaDiscipline\Http\Controllers\SupervisionTransferController;

/**
 * jea-discipline module routes (Workstream 8B).
 *
 * Loaded by JeaDisciplineServiceProvider::boot(). Every route below
 * used to live in backend/routes/api.php. Removing 'jea-discipline'
 * from config/modules.enabled cleanly removes:
 *
 *   Any-role intake:
 *     POST /api/v1/complaints                             (JORD-81 intake)
 *
 *   Applicant self-service:
 *     GET  /api/v1/my/complaints
 *     GET  /api/v1/my/sanctions
 *
 *   Admin surface (role: admin/superuser):
 *     GET  /api/v1/admin/complaints
 *     POST /api/v1/admin/complaints/{id}/decide
 *     GET  /api/v1/admin/legal-fines
 *     POST /api/v1/admin/legal-fines
 *     POST /api/v1/admin/legal-fines/{id}/pay
 *     GET  /api/v1/admin/supervision-transfers
 *     POST /api/v1/admin/supervision-transfers/{id}/assign
 *     POST /api/v1/admin/supervision-transfers/{id}/accept-decline
 */
Route::prefix('api/v1')->middleware(['auth:sanctum', 'token.inactivity', 'password.policy', 'track.activity'])->group(function () {

    // JORD-81 intake — any authenticated user in the org can file.
    Route::middleware('role:applicant,staff,auditor,admin')->group(function () {
        Route::post('complaints',   [ComplaintController::class, 'store']);
        Route::get ('my/complaints', [MyDisciplineController::class, 'complaints']);
        Route::get ('my/sanctions',  [MyDisciplineController::class, 'sanctions']);
    });

    // Admin surface — decide + issue + assign + close.
    Route::middleware('role:admin,superuser')->group(function () {
        Route::get ('admin/complaints',                              [ComplaintController::class, 'index']);
        Route::post('admin/complaints/{id}/decide',                  [ComplaintController::class, 'decide']);

        Route::get ('admin/legal-fines',                             [LegalFineController::class, 'index']);
        Route::post('admin/legal-fines',                             [LegalFineController::class, 'store']);
        Route::post('admin/legal-fines/{id}/pay',                    [LegalFineController::class, 'pay']);

        Route::get ('admin/supervision-transfers',                       [SupervisionTransferController::class, 'index']);
        Route::post('admin/supervision-transfers/{id}/assign',           [SupervisionTransferController::class, 'assign']);
        Route::post('admin/supervision-transfers/{id}/accept-decline',   [SupervisionTransferController::class, 'acceptOrDecline']);
    });
});
