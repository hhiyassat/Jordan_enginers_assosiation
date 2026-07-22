<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnforcePasswordPolicy;
use App\Http\Middleware\LogApiAccess;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TokenInactivityCheck;
use App\Http\Middleware\ValidateIntegrationKey;
// Workstream 13: VerifyCaptcha moved to Plugins\Captcha. The 'captcha'
// middleware alias is registered by CaptchaServiceProvider so disabling
// the plugin (config/plugins.enabled) drops the alias with it.
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/*
|--------------------------------------------------------------------------
| ESP v2 — Application Bootstrap
|--------------------------------------------------------------------------
|
| BUILD CONTRACT §4: Middleware stack wired BEFORE any routes.
| Order: SecurityHeaders → LogApiAccess (global) → then per-route auth layers.
|
| SEC-001: SecurityHeaders on every response.
| SEC-002: LogApiAccess on every API request.
|
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api:      __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        health:   '/up',
        commands: __DIR__ . '/../routes/console.php', // schedule & artisan commands
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Global middleware — applied to every request
        $middleware->append(SecurityHeaders::class);
        $middleware->append(LogApiAccess::class);
        // JORD-30: read the Sanctum token from the httpOnly session
        // cookie and promote it to the Authorization header before the
        // Sanctum guard runs. Global (not per-route) so every /api/v1
        // endpoint that uses auth:sanctum picks it up. Non-authenticated
        // routes (login, captcha) are a no-op.
        $middleware->append(\App\Http\Middleware\ReadTokenFromCookie::class);

        // Named middleware aliases (used in routes)
        $middleware->alias([
            'role'             => CheckRole::class,
            'token.inactivity' => TokenInactivityCheck::class,
            'password.policy'  => EnforcePasswordPolicy::class,
            'integration.key'  => ValidateIntegrationKey::class,
            // GSB: MODEE Annex 4.15 §4.5 rule 11 — IP whitelist for GSB routes
            'gsb.ip_whitelist' => \App\Http\Middleware\GsbIpWhitelist::class,
            // Workstream 13: 'captcha' alias is registered by
            // Plugins\Captcha\Providers\CaptchaServiceProvider — routes
            // that use ->middleware('captcha') break if the plugin is
            // removed from config/plugins.enabled while those routes
            // still reference it (login/register/etc.).
            // JORD-24: bump users.last_seen_at (coalesced to 1 write/min)
            'track.activity'   => \App\Http\Middleware\TrackUserActivity::class,
        ]);

        // NOTE: statefulApi() (Sanctum SPA cookie auth) is intentionally REMOVED.
        // This application uses Bearer token auth only. statefulApi() injects
        // EnsureFrontendRequestsAreStateful which can override $request->user()
        // resolution for requests from localhost, causing CheckRole to see a null user.

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
