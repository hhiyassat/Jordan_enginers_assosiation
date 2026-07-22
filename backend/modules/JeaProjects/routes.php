<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\JeaProjects\Http\Controllers\EngineerController;
use Modules\JeaProjects\Http\Controllers\OfficeSettingsController;
use Modules\JeaProjects\Http\Controllers\ProjectController;

/**
 * jea-projects module routes (Workstream 8A).
 *
 * Loaded by JeaProjectsServiceProvider::boot(). Every route below
 * used to live in backend/routes/api.php. Removing 'jea-projects'
 * from config/modules.enabled cleanly removes:
 *
 *   Applicant surface (role: applicant/staff/auditor/admin):
 *     GET  /api/v1/projects
 *     GET  /api/v1/projects/quota
 *     POST /api/v1/projects
 *     GET  /api/v1/projects/{id}
 *     GET  /api/v1/engineers
 *     POST /api/v1/engineers
 *     GET  /api/v1/engineers/{id}
 *     GET  /api/v1/engineers/{id}/quota
 *
 *   Admin surface (role: admin/superuser):
 *     GET   /api/v1/admin/offices
 *     GET   /api/v1/admin/offices/{id}
 *     PATCH /api/v1/admin/offices/{id}
 *     PATCH /api/v1/admin/offices/{officeId}/engineers/{engineerId}
 *
 * The 'api/v1' prefix is spelled out because Laravel's `apiPrefix`
 * config doesn't reach ServiceProvider::loadRoutesFrom (same reason
 * as jea-dues).
 */
Route::prefix('api/v1')->middleware(['auth:sanctum', 'token.inactivity', 'password.policy', 'track.activity'])->group(function () {

    // Applicant-facing surface — projects + engineer roster (per-office).
    Route::middleware('role:applicant,staff,auditor,admin')->group(function () {
        Route::get('projects',       [ProjectController::class, 'index']);
        Route::get('projects/quota', [ProjectController::class, 'quota']);
        Route::post('projects',      [ProjectController::class, 'store']);
        Route::get('projects/{id}',  [ProjectController::class, 'show']);

        Route::get('engineers',              [EngineerController::class, 'index']);
        Route::post('engineers',             [EngineerController::class, 'store']);
        Route::get('engineers/{id}',         [EngineerController::class, 'show']);
        Route::get('engineers/{id}/quota',   [EngineerController::class, 'quota']);
    });

    // Admin-only surface — office boost flags + specialization-head.
    Route::middleware('role:admin,superuser')->group(function () {
        Route::get  ('admin/offices',                                     [OfficeSettingsController::class, 'index']);
        Route::get  ('admin/offices/{id}',                                [OfficeSettingsController::class, 'show']);
        Route::patch('admin/offices/{id}',                                [OfficeSettingsController::class, 'update']);
        Route::patch('admin/offices/{officeId}/engineers/{engineerId}',   [OfficeSettingsController::class, 'updateEngineer']);
    });
});
