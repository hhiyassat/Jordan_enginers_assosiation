<?php

namespace App\Providers;

use App\Services\Gsb\GsbAuthManager;
use App\Services\Gsb\GsbClient;
use Illuminate\Support\ServiceProvider;

/**
 * AppServiceProvider
 *
 * Registers application services in the Laravel IoC container.
 * GSB services are bound as singletons so a single OAuth token
 * is shared across all GsbClient calls within a request lifecycle.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── GSB (Government Service Bus) services — MODEE Annex 4.15 ──────
        //
        // GsbAuthManager: singleton so the cached OAuth token is reused across
        // multiple GsbClient calls in the same request. Token is stored in the
        // Laravel cache (Redis/file), not in-memory, so it survives across
        // requests without re-fetching on every call.
        //
        // GsbClient: singleton so the same GsbAuthManager instance is injected
        // everywhere. Resolved lazily — only instantiated if a GSB route is hit.

        // GsbAuthManager reads config('gsb.oauth.*') internally — no constructor args.
        $this->app->singleton(GsbAuthManager::class);

        // GsbClient depends on GsbAuthManager — Laravel resolves it automatically
        // via the type-hinted constructor, but explicit binding makes it a singleton.
        $this->app->singleton(GsbClient::class, function ($app) {
            return new GsbClient(auth: $app->make(GsbAuthManager::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
