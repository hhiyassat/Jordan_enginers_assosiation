<?php

declare(strict_types=1);

namespace Plugins\Captcha\Providers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Plugins\Captcha\Http\Middleware\VerifyCaptcha;

/**
 * CaptchaServiceProvider — Workstream 13 (plugin extraction).
 *
 * Boots the captcha plugin. Removing 'captcha' from
 * config/plugins.enabled cleanly removes:
 *
 *   • GET /api/v1/captcha              (issue a challenge)
 *   • The 'captcha' middleware alias   (used by login + register)
 *
 * WARNING: routes that reference ->middleware('captcha') (currently
 * POST /api/v1/auth/login and POST /api/v1/auth/register in the
 * platform routes/api.php) will fail with UnknownMiddlewareException
 * if the plugin is disabled but those routes still spell 'captcha'.
 * That's intentional — the plugin is currently a hard dependency of
 * the auth surface. A future workstream can gate the alias behind a
 * feature flag so removing the plugin turns captcha into a no-op
 * instead of an error.
 *
 * Rate-limiter registration: throttle:captcha-issue stays in
 * AppServiceProvider::registerRateLimiters() because limiter names
 * are process-global.
 */
class CaptchaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(Router $router): void
    {
        $pluginRoot = dirname(__DIR__);

        $this->loadRoutesFrom($pluginRoot . '/routes.php');

        // Register the 'captcha' middleware alias here (was in
        // bootstrap/app.php pre-8C) so it disappears when the plugin
        // is disabled — no dangling alias pointing at a missing class.
        $router->aliasMiddleware('captcha', VerifyCaptcha::class);
    }
}
