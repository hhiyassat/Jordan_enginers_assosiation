<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\JeaDues\Http\Controllers\MyDuesController;
use Modules\JeaDues\Http\Controllers\RecurringDuesController;

/**
 * jea-dues module routes (Workstream 7).
 *
 * Loaded by JeaDuesServiceProvider::boot(). Every route below used
 * to live in backend/routes/api.php; that file no longer registers
 * them, so removing this module from config/modules.enabled cleanly
 * removes:
 *
 *   GET  /api/v1/my/dues                        (applicant self-service)
 *   GET  /api/v1/admin/offices/{id}/dues        (admin view)
 *   POST /api/v1/admin/offices/{id}/dues/register (admin seed F-04)
 *   POST /api/v1/admin/dues/{obligationId}/pay  (admin mark paid)
 *
 * Middleware chains mirror the pre-split registration exactly.
 */
// Note: the platform's routes/api.php inherits `apiPrefix: 'api'`
// from bootstrap/app.php automatically. Module route files loaded
// via ServiceProvider::loadRoutesFrom() DON'T inherit that prefix,
// so we spell out `api/v1` explicitly. Same URL shape as before.
Route::prefix('api/v1')->middleware(['auth:sanctum', 'token.inactivity', 'password.policy', 'track.activity'])->group(function () {

    // Applicant self-service — the office sees their own dues.
    Route::middleware('role:applicant,staff,auditor,admin')->group(function () {
        Route::get('my/dues', [MyDuesController::class, 'index']);
    });

    // Admin surface — view + seed + pay.
    Route::middleware('role:admin,superuser')->group(function () {
        Route::get ('admin/offices/{id}/dues',           [RecurringDuesController::class, 'index']);
        Route::post('admin/offices/{id}/dues/register',  [RecurringDuesController::class, 'seedRegistration']);
        Route::post('admin/dues/{obligationId}/pay',     [RecurringDuesController::class, 'pay']);
    });
});
