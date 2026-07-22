<?php

declare(strict_types=1);

namespace Integrations\Gsb\Providers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Integrations\Gsb\Console\Commands\GsbPruneLogs;
use Integrations\Gsb\Http\Middleware\GsbIpWhitelist;

/**
 * GsbServiceProvider — Workstream 14 (integration-adapter extraction).
 *
 * Boots the GSB adapter. GSB is Jordan's Government Service Bus
 * (MODEE Annex 4.15) — an external system this app talks to for
 * citizen-data lookups. Removing 'gsb' from config/integrations.enabled
 * cleanly removes:
 *   • POST /api/v1/gsb/otp/request
 *   • POST /api/v1/gsb/otp/verify
 *   • GET  /api/v1/gsb/citizen
 *   • GET  /api/v1/gsb/audit-logs
 *   • The 'gsb.ip_whitelist' middleware alias
 *   • gsb-call-logs migration + prune command
 *
 * Dependency direction:
 *   • EIA→PC: uses App\Http\Concerns\* + auth guards. Legitimate.
 *   • EIA→SM: none. GSB is domain-neutral.
 */
class GsbServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(Router $router): void
    {
        $adapterRoot = dirname(__DIR__);

        $this->loadRoutesFrom($adapterRoot . '/routes.php');
        $this->loadMigrationsFrom($adapterRoot . '/Database/Migrations');

        // Middleware alias registered here (was in bootstrap/app.php
        // pre-W14) so it disappears when the adapter is disabled.
        $router->aliasMiddleware('gsb.ip_whitelist', GsbIpWhitelist::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                GsbPruneLogs::class,
            ]);
        }
    }
}
