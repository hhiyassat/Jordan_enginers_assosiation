<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * IntegrationsServiceProvider — Workstream 14.
 *
 * The platform's single entry point into the integrations subsystem.
 * Reads `config/integrations.enabled` — a map of integration-id →
 * service-provider class — and registers each provider with the
 * container.
 *
 * An integration is an ADAPTER FOR AN EXTERNAL SYSTEM living under
 * `backend/integrations/<PascalName>/`. Its service provider owns
 * the adapter's routes, middleware aliases, migrations, console
 * commands, and container bindings. The platform boots the provider
 * and from that point knows nothing about the adapter's internals.
 *
 * Mirrors ModulesServiceProvider + PluginsServiceProvider intentionally;
 * keeping three separate files signals the different semantic:
 *   modules      — domain data + business logic
 *   plugins      — install-time optional capabilities
 *   integrations — adapters for external systems
 */
class IntegrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (config('integrations.enabled', []) as $integrationId => $providerClass) {
            $this->app->register($providerClass);
        }
    }
}
