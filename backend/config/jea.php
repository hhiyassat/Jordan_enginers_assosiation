<?php

/*
|--------------------------------------------------------------------------
| JEA integration configuration (C-03)
|--------------------------------------------------------------------------
|
| Settings for the real (production) HTTP driver of
| JeaMembershipVerifier. The concrete endpoint URL, auth scheme,
| request/response schema are BLOCKED_EXTERNAL_INPUT — they come
| from JEA — but the config surface is fixed here so ops can wire
| them in a single .env change once available.
|
*/

return [

    'membership_api' => [
        /*
         | Base URL of the JEA engineer-registry lookup endpoint. Empty
         | in dev / testing; production MUST set this or the
         | HttpJeaMembershipVerifier throws.
         */
        'base_url' => env('JEA_MEMBERSHIP_API_URL', ''),

        /*
         | Authentication scheme. One of: 'bearer' | 'basic' | 'header'.
         */
        'auth_scheme' => env('JEA_MEMBERSHIP_API_AUTH_SCHEME', 'bearer'),

        /*
         | For 'bearer' or 'header' schemes: the token/api-key value.
         */
        'auth_token' => env('JEA_MEMBERSHIP_API_AUTH_TOKEN', ''),

        /*
         | For 'header' scheme: the header name.
         */
        'auth_header' => env('JEA_MEMBERSHIP_API_AUTH_HEADER', 'X-Api-Key'),

        /*
         | For 'basic' scheme: username/password.
         */
        'basic_user'     => env('JEA_MEMBERSHIP_API_BASIC_USER', ''),
        'basic_password' => env('JEA_MEMBERSHIP_API_BASIC_PASSWORD', ''),

        /*
         | HTTP timeout in seconds and retry policy.
         */
        'timeout'        => (int) env('JEA_MEMBERSHIP_API_TIMEOUT', 5),
        'retries'        => (int) env('JEA_MEMBERSHIP_API_RETRIES', 2),
        'retry_delay_ms' => (int) env('JEA_MEMBERSHIP_API_RETRY_DELAY_MS', 200),
    ],

];
