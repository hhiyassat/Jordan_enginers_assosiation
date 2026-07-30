<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from these domains may receive stateful API cookie
    | authentication. Typically, these should include your local and
    | production domains that access your API via a frontend SPA.
    |
    */

    'stateful' => explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,localhost:5173,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes (M-22)
    |--------------------------------------------------------------------------
    |
    | Absolute upper bound on token lifetime. A token whose `created_at` is
    | older than this many minutes is rejected regardless of activity.
    | Previously the Sanctum config was not published, so `expiration` fell
    | back to Sanctum's built-in default of `null` — meaning tokens never
    | expired absolutely. Only `TokenInactivityCheck` was culling them
    | based on idle time; a user who touched the app every 29 minutes
    | kept the same token forever.
    |
    | 480 = 8 hours. Matches the ESP_SESSION_LIFETIME_MINUTES cookie cap
    | defined in AuthController::buildSessionCookie so cookie + token
    | expire together.
    |
    */

    'expiration' => (int) env('SANCTUM_EXPIRATION_MINUTES', 480),

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies'      => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token'  => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
