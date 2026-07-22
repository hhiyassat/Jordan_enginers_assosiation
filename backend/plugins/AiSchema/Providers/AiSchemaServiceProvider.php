<?php

declare(strict_types=1);

namespace Plugins\AiSchema\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * AiSchemaServiceProvider — Workstream 13 (plugin extraction).
 *
 * Boots the ai-schema plugin. Owns the three Claude-backed AI
 * endpoints that admin uses to author + iterate on ServiceDefinition
 * schemas. Removing 'ai-schema' from config/plugins.enabled cleanly
 * removes:
 *   POST /api/v1/admin/services/generate-schema
 *   POST /api/v1/admin/services/generate-schema-from-file
 *   POST /api/v1/admin/services/chat-schema
 *
 * Dependency direction:
 *   • PLG→SM: reads Modules\JeaServices\Models\ServiceDefinition to
 *     write generated schemas back. Legitimate — plugins may depend
 *     on service modules; the reverse (SM→PLG) is forbidden.
 *   • PLG→PC: uses App\Http\Concerns\RequiresAdminTier + auth guards.
 *
 * Rate-limiter registration: throttle:ai-schema stays in
 * App\Providers\AppServiceProvider::registerRateLimiters() because
 * limiter names are process-global. If the plugin is disabled the
 * limiter is never hit — no cleanup needed.
 */
class AiSchemaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $pluginRoot = dirname(__DIR__);

        $this->loadRoutesFrom($pluginRoot . '/routes.php');
    }
}
