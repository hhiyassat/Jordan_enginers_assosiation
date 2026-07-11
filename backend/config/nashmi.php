<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nashmi Integration Config
    |--------------------------------------------------------------------------
    |
    | Shared secret used to authenticate inbound webhooks from Nashmi
    | and outbound requests to the Nashmi AI Manager API.
    |
    | Set INTEGRATION_KEY and NASHMI_BASE_URL in your .env file.
    | Never commit actual keys to version control.
    |
    */

    'integration_key'   => env('INTEGRATION_KEY', ''),

    'base_url'          => env('NASHMI_BASE_URL', 'https://nashmi.manager.eqratech.com'),

    'organization_id'   => env('NASHMI_ORG_ID', '1'),

    // Timeout in seconds for outbound HTTP calls to Nashmi
    'timeout'           => (int) env('NASHMI_TIMEOUT', 30),

];
