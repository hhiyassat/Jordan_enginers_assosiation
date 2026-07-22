<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Integrations\Nashmi\Http\Controllers\IntegrationController;

/**
 * Nashmi adapter routes (Workstream 14).
 *
 * Loaded by NashmiServiceProvider::boot(). Every route below used to
 * live at the top of backend/routes/api.php (registered BEFORE the v1
 * group so Sanctum never intercepts them). Removing 'nashmi' from
 * config/integrations.enabled cleanly removes:
 *   POST /api/integration/receive-requirements
 *   POST /api/integration/receive-feedback
 *   POST /api/integration/cycles/{id}/notify-done
 *   GET  /api/integration/cycles
 *   GET  /api/integration/cycles/{id}
 *   GET  /api/integration/cycles/{id}/pdf
 *
 * NO Sanctum on this surface — validated by X-Integration-Key
 * (see ValidateIntegrationKey middleware, aliased as 'integration.key').
 */
Route::prefix('api/integration')
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
