<?php

declare(strict_types=1);

namespace Modules\JeaDues\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\JeaDues\Console\Commands\OpenAnnualDues;

/**
 * JeaDuesServiceProvider — Workstream 7.
 *
 * Boots the jea-dues module. Only registered by the platform when
 * `jea-dues` is in `config/modules.enabled` — so removing that key
 * cleanly disables the whole subsystem (routes disappear, artisan
 * commands are gone, migrations are no longer discovered).
 *
 * What this provider owns:
 *   • Routes            → modules/JeaDues/routes.php
 *   • Migrations        → modules/JeaDues/Database/Migrations
 *   • Console commands  → OpenAnnualDues (dues:open-annual)
 *
 * Deliberately keeps register() empty — no service bindings are
 * needed (RecurringDuesService is autoloaded by the container's
 * default resolution). Add bindings here only when the module needs
 * a contract that the platform's IoC has to resolve.
 */
class JeaDuesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $moduleRoot = dirname(__DIR__);

        $this->loadRoutesFrom($moduleRoot . '/routes.php');
        $this->loadMigrationsFrom($moduleRoot . '/Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                OpenAnnualDues::class,
            ]);
        }
    }
}
