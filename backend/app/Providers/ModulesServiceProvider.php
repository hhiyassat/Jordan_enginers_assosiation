<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * ModulesServiceProvider — Workstream 7.
 *
 * The platform's single entry point into the modules subsystem.
 * Reads `config/modules.enabled` — a map of module-id → service-
 * provider class — and registers each provider with the container.
 *
 * A module is a self-contained subsystem living under
 * `backend/modules/<PascalName>/`. Its service provider owns the
 * module's routes, migrations, console commands, service bindings,
 * and any middleware it needs. The platform boots the provider and
 * from that point knows nothing about the module's internals.
 *
 * Disabling a module = removing its key from config/modules.enabled.
 * No file changes, no autoload rebuild — the provider isn't loaded
 * next boot and everything it registered (routes, commands,
 * migrations) simply doesn't appear.
 *
 * Failure mode: if a manifested provider class doesn't exist,
 * Composer's autoloader throws at register-time. That's intentional
 * — the app should refuse to boot with a broken module registration.
 */
class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (config('modules.enabled', []) as $moduleId => $providerClass) {
            $this->app->register($providerClass);
        }
    }
}
