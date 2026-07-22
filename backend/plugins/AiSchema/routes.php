<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Plugins\AiSchema\Http\Controllers\AiSchemaController;

/**
 * ai-schema plugin routes (Workstream 13).
 *
 * Loaded by AiSchemaServiceProvider::boot(). Removing 'ai-schema'
 * from config/plugins.enabled cleanly removes:
 *   POST /api/v1/admin/services/generate-schema
 *   POST /api/v1/admin/services/generate-schema-from-file
 *   POST /api/v1/admin/services/chat-schema
 *
 * All three share the 'ai-schema' rate-limit bucket — 10 calls/hour
 * per user is generous for interactive authoring but stops a
 * runaway loop from burning through the API budget in minutes.
 * The named limiter itself lives in AppServiceProvider (limiter
 * names are process-global) — this file only references it.
 */
Route::prefix('api/v1')->middleware(['auth:sanctum', 'token.inactivity', 'password.policy', 'track.activity'])->group(function () {
    Route::middleware('role:admin,superuser')->group(function () {
        Route::post('admin/services/generate-schema',           [AiSchemaController::class, 'generateSchema'])
            ->middleware('throttle:ai-schema');
        Route::post('admin/services/generate-schema-from-file', [AiSchemaController::class, 'generateSchemaFromFile'])
            ->middleware('throttle:ai-schema');
        Route::post('admin/services/chat-schema',               [AiSchemaController::class, 'chatUpdateSchema'])
            ->middleware('throttle:ai-schema');
    });
});
