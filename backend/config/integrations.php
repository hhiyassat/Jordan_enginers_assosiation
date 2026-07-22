<?php

declare(strict_types=1);

/**
 * Integrations configuration — Workstream 14.
 *
 * Each key is an integration ID (lower-kebab). The value is the
 * fully-qualified service-provider class that boots the adapter.
 * The platform's App\Providers\IntegrationsServiceProvider iterates
 * this map at register-time and registers each provider — so removing
 * a key here disables the whole adapter (routes, middleware aliases,
 * migrations, console commands are all gone).
 *
 * Integrations vs. plugins vs. modules:
 *   • Modules       — own domain data + business logic (JEA services).
 *   • Plugins       — install-time optional cross-domain capabilities
 *                     (captcha, AI schema generation, SSO).
 *   • Integrations  — adapters for EXTERNAL systems (GSB citizen
 *                     bureau, Nashmi contractor management). One
 *                     integration = one external system.
 *
 * An integration MUST NOT be listed here if its provider class
 * doesn't exist yet — Composer will throw at register-time.
 */

return [
    'enabled' => [
        'gsb'    => \Integrations\Gsb\Providers\GsbServiceProvider::class,
        'nashmi' => \Integrations\Nashmi\Providers\NashmiServiceProvider::class,
    ],
];
