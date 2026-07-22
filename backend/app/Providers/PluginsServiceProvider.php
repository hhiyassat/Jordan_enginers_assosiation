<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * PluginsServiceProvider — Workstream 13.
 *
 * The platform's single entry point into the plugins subsystem.
 * Reads `config/plugins.enabled` — a map of plugin-id → service-
 * provider class — and registers each provider with the container.
 *
 * A plugin is an install-time optional capability living under
 * `backend/plugins/<PascalName>/`. Its service provider owns the
 * plugin's routes, middleware aliases, and any container bindings.
 * The platform boots the provider and from that point knows nothing
 * about the plugin's internals.
 *
 * Disabling a plugin = removing its key from config/plugins.enabled.
 * No file changes, no autoload rebuild — the provider isn't loaded
 * next boot and everything it registered simply doesn't appear.
 *
 * Mirrors ModulesServiceProvider intentionally; keeping the two files
 * separate signals the different semantic (module = domain data +
 * business logic, plugin = install-time optional capability).
 */
class PluginsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (config('plugins.enabled', []) as $pluginId => $providerClass) {
            $this->app->register($providerClass);
        }
    }
}
