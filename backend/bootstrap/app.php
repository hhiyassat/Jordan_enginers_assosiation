<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnforcePasswordPolicy;
use App\Http\Middleware\LogApiAccess;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TokenInactivityCheck;
use App\Http\Middleware\ValidateIntegrationKey;
use App\Http\Middleware\VerifyCaptcha;
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

        // Named middleware aliases (used in routes)
        $middleware->alias([
            'role'             => CheckRole::class,
            'token.inactivity' => TokenInactivityCheck::class,
            'password.policy'  => EnforcePasswordPolicy::class,
            'integration.key'  => ValidateIntegrationKey::class,
            // GSB: MODEE Annex 4.15 §4.5 rule 11 — IP whitelist for GSB routes
            'gsb.ip_whitelist' => \App\Http\Middleware\GsbIpWhitelist::class,
            // Text captcha for public forms (login, register, tracking)
            'captcha'          => VerifyCaptcha::class,
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
