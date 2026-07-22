<?php

declare(strict_types=1);

namespace Integrations\Nashmi\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Integrations\Nashmi\Http\Middleware\ValidateIntegrationKey;

/**
 * NashmiServiceProvider — Workstream 14 (integration-adapter extraction).
 *
 * Boots the Nashmi adapter — the external system that receives job
 * requirements from ESP and returns compiled feedback + PDFs.
 * Removing 'nashmi' from config/integrations.enabled cleanly removes:
 *   • POST /api/integration/receive-requirements
 *   • POST /api/integration/receive-feedback
 *   • POST /api/integration/cycles/{id}/notify-done
 *   • GET  /api/integration/cycles
 *   • GET  /api/integration/cycles/{id}
 *   • GET  /api/integration/cycles/{id}/pdf
 *   • The 'integration.key' middleware alias
 *   • integration_cycles table migration
 *
 * These routes intentionally sit OUTSIDE the v1/ Sanctum surface —
 * they're validated by the X-Integration-Key header (see the
 * ValidateIntegrationKey middleware) not by a user session.
 */
class NashmiServiceProvider extends ServiceProvider
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
        $router->aliasMiddleware('integration.key', ValidateIntegrationKey::class);
    }
}
