<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Plugins\Captcha\Http\Controllers\CaptchaController;

/**
 * captcha plugin routes (Workstream 13).
 *
 * Loaded by CaptchaServiceProvider::boot(). Removing 'captcha' from
 * config/plugins.enabled drops:
 *   GET /api/v1/captcha  (public — no auth, throttled)
 *
 * The throttle:captcha-issue named limiter stays in
 * AppServiceProvider::registerRateLimiters() — limiter names are
 * process-global and cost nothing when unused.
 */
Route::prefix('api/v1')->group(function () {
    Route::get('captcha', [CaptchaController::class, 'issue'])
        ->middleware('throttle:captcha-issue');
});
