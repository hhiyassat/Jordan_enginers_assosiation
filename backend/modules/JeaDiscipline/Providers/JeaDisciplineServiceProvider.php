<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\JeaDiscipline\Console\Commands\RemindExpiries;

/**
 * JeaDisciplineServiceProvider — Workstream 8B.
 *
 * Boots the jea-discipline module. Removing 'jea-discipline' from
 * config/modules.enabled cleanly disables:
 *   • Complaints intake + admin decide
 *   • Legal fines (Art.14) — issue + pay + list
 *   • Supervision transfers — assign + accept/decline queue
 *   • Applicant self-service: /my/complaints + /my/sanctions
 *   • Cert / supervision expiry reminders (cron)
 *
 * What this provider owns:
 *   • Routes            → modules/JeaDiscipline/routes.php
 *   • Migrations        → modules/JeaDiscipline/Database/Migrations
 *                         (3 migrations: complaints+sanctions,
 *                         legal_fines, supervision_transfers)
 *   • Engine primitive  → SanctionGuard (autoloaded; no bindings)
 *   • Console commands  → RemindExpiries (schedule:expiries hook)
 *
 * Cross-module notes:
 *   • WorkflowEngine (still in app/Engine/) uses SanctionGuard for
 *     the "sanctioned office can't submit" gate. Legitimate SM→SM.
 *   • SupervisionTransfer.application_id FKs into jea-services'
 *     applications table; disabling jea-services would dangle that
 *     FK at runtime. Disable in opposite order.
 */
class JeaDisciplineServiceProvider extends ServiceProvider
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
                RemindExpiries::class,
            ]);
        }
    }
}
