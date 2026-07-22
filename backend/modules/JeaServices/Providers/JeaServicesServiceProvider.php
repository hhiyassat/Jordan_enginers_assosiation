<?php

declare(strict_types=1);

namespace Modules\JeaServices\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * JeaServicesServiceProvider — Workstream 8C.
 *
 * Boots the jea-services module — the CENTRAL JEA service module.
 * Owns the application lifecycle (draft → submitted → reviewed →
 * approved → certificate issued), the service catalog, fees, and the
 * workflow engine that reads schema.workflow and drives state
 * transitions.
 *
 * What this provider owns:
 *   • Routes            → modules/JeaServices/routes.php
 *   • Migrations        → modules/JeaServices/Database/Migrations
 *                         (12 migrations: service_definitions +
 *                         hierarchy/phase/subcategory/lock columns,
 *                         applications + project_id + contract_no,
 *                         application_documents, application_reviews,
 *                         certificates + certificate_counters)
 *   • Engine primitives → WorkflowEngine, FeeCalculator, StageActions,
 *                         SchemaValidator, SchemaStructureValidator
 *                         (autoloaded; no explicit bindings today).
 *
 * Cross-module notes (SM→SM contracts — legitimate, one-way):
 *   • jea-projects reads Application::forOrganization() for quota
 *     accounting (QuotaLedger, CapacityGuard). No back-reference from
 *     jea-services.
 *   • jea-discipline reads Application for legal-fine + supervision-
 *     transfer FKs and for the RemindExpiries scan.
 *   • jea-dues has no application dependency.
 *
 * Disable order matters: if you remove 'jea-services' from
 * config/modules.enabled while any other jea-* module stays enabled,
 * those modules' Application-touching code will 500 at runtime.
 * Remove jea-projects + jea-discipline first (jea-dues is independent).
 *
 * Two files that read Application/ServiceDefinition still live in
 * app/ and are marked RED for future splits:
 *   • AdminDashboardController — needs to split into a platform shell
 *     + a jea-services widget (planned in a later workstream).
 *   • AiSchemaController — PLG; Workstream 13 lifts it under
 *     backend/plugins/ai-schema/ where the SM→PLG dep becomes a
 *     PLG→SM plugin contract instead.
 */
class JeaServicesServiceProvider extends ServiceProvider
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
