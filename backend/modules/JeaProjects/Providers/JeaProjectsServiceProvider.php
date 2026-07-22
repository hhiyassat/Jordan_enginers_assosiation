<?php

declare(strict_types=1);

namespace Modules\JeaProjects\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * JeaProjectsServiceProvider — Workstream 8A.
 *
 * Boots the jea-projects module. Only registered by the platform when
 * `jea-projects` is in `config/modules.enabled` — so removing that key
 * cleanly disables Projects + Engineers + Office quotas + boost flags.
 *
 * What this provider owns:
 *   • Routes            → modules/JeaProjects/routes.php
 *   • Migrations        → modules/JeaProjects/Database/Migrations
 *                         (12 migrations: projects, engineers,
 *                         office_ceilings, office_coalitions,
 *                         quota_consumptions, engineer_discipline_quotas,
 *                         plus the office-scoped quota + boost updates)
 *   • Engine primitives → CapacityGuard, Disciplines, QuotaLedger
 *                         (autoloaded by container's default resolution;
 *                         no explicit bindings needed today)
 *
 * Cross-module notes:
 *   • jea-services (Application, WorkflowEngine) reads QuotaLedger for
 *     overflow-surcharge computation and CapacityGuard during submit.
 *     That's a legitimate one-way SM→SM contract, not a violation.
 *   • Application FKs project_id + engineer_id. If jea-projects is
 *     disabled while jea-services stays on, those FKs dangle at
 *     runtime; migrate:fresh will fail (missing tables). Disable in
 *     opposite order.
 */
class JeaProjectsServiceProvider extends ServiceProvider
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
    }
}
