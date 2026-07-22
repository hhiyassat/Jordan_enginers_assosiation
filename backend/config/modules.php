<?php

declare(strict_types=1);

/**
 * Modules configuration — Workstream 7.
 *
 * Each key is a module ID (lower-kebab). The value is the fully-
 * qualified service-provider class that boots the module. The
 * platform's App\Providers\ModulesServiceProvider iterates this
 * map at register-time and registers each provider — so removing a
 * key here disables the whole module (routes disappear, artisan
 * commands are gone, migrations are no longer discovered).
 *
 * A module MUST NOT be listed here if its provider class doesn't
 * exist yet — Composer will throw at register-time and the whole
 * app will fail to boot. Add + remove atomically.
 *
 * The proof-of-concept module (jea-dues) is enabled by default so
 * every existing dues test keeps passing. Workstream 8 adds the
 * remaining JEA modules; Workstreams 13/14 add plugins + adapters
 * to the same config file under separate sections.
 */

return [
    'enabled' => [
        'jea-dues' => \Modules\JeaDues\Providers\JeaDuesServiceProvider::class,
    ],
];
