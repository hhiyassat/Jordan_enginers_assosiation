# Adding a plugin (or an integration)

Workstream 16 walkthrough. Follows the same pattern as modules
([`06-adding-a-service-module.md`](06-adding-a-service-module.md))
with three differences: different folder, different registry, different
dep-direction rules.

## Plugin vs module vs integration recap

- **Module** — domain-owning subsystem (see the modules doc).
- **Plugin** — install-time optional cross-domain capability.
- **Integration** — adapter for exactly one external system.

## Adding a plugin

### 1. Choose an id and namespace

- id: lower-kebab (`sso-google`)
- namespace: PascalCase (`Plugins\SsoGoogle`)
- Folder: `backend/plugins/SsoGoogle/`

### 2. Scaffold

```bash
mkdir -p backend/plugins/SsoGoogle/{Http/{Controllers,Middleware},Services,Providers,Database/Migrations}
```

### 3. Service provider

`backend/plugins/SsoGoogle/Providers/SsoGoogleServiceProvider.php`:

```php
<?php
declare(strict_types=1);
namespace Plugins\SsoGoogle\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Plugins\SsoGoogle\Http\Middleware\GoogleTokenGuard;

class SsoGoogleServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $pluginRoot = dirname(__DIR__);
        $this->loadRoutesFrom($pluginRoot . '/routes.php');
        $this->loadMigrationsFrom($pluginRoot . '/Database/Migrations');

        // If the plugin owns a middleware alias, register it here
        // so it disappears when the plugin is disabled.
        $router->aliasMiddleware('sso.google', GoogleTokenGuard::class);
    }
}
```

### 4. Routes file

`backend/plugins/SsoGoogle/routes.php`. Same pattern as module routes —
explicit `Route::prefix('api/v1')`, group by middleware, group by role.

### 5. Add to the registry

`backend/config/plugins.php`:

```php
return [
    'enabled' => [
        'ai-schema' => \Plugins\AiSchema\Providers\AiSchemaServiceProvider::class,
        'captcha'   => \Plugins\Captcha\Providers\CaptchaServiceProvider::class,
        'sso-google' => \Plugins\SsoGoogle\Providers\SsoGoogleServiceProvider::class,
    ],
];
```

### 6. Regenerate autoload, run tests, verify disable

```bash
cd backend && composer dump-autoload -o
php artisan test
# Baseline
php artisan route:list | grep -c "sso/google"     # → N
# Disable
sed -i '' "s|'sso-google'.*=> .*|/* disabled */|" backend/config/plugins.php
php artisan route:list | grep -c "sso/google"     # → 0
git checkout backend/config/plugins.php
```

## Adding an integration

Only the differences from a plugin:

1. Folder: `backend/integrations/<Name>/`
2. Namespace: `Integrations\<Name>\...`
3. Registry: `backend/config/integrations.php`

Integrations get their own top-level provider (`IntegrationsServiceProvider`)
that boots AFTER `PluginsServiceProvider` — order in
`bootstrap/providers.php`:

```
ModulesServiceProvider    ← first (services need bindings)
PluginsServiceProvider    ← plugins depend on modules
IntegrationsServiceProvider ← adapters can read plugin state
```

Integration middleware aliases follow the same pattern as plugins —
registered inside the adapter's `boot()` so disabling the adapter
drops the alias too.

Existing integrations to copy from:

- `backend/integrations/Gsb/` — auth:sanctum + IP whitelist + audit-logged
- `backend/integrations/Nashmi/` — OUTSIDE Sanctum, header-key validated

## Dep-direction rules (enforced in CI)

- `PLG → PC`: OK
- `PLG → SM`: OK
- `PLG → PLG`: OK but rare
- `PLG → EIA`: OK but rare
- `EIA → PC`: OK
- `EIA → SM`: OK but rare (Nashmi doesn't; a webhook that acks an
  Application ID could)
- `EIA → EIA`: OK but rare
- `SM → PLG`: FORBIDDEN
- `SM → EIA`: FORBIDDEN
- `PC → PLG`: FORBIDDEN
- `PC → EIA`: FORBIDDEN with one documented allowlist entry
  (composition root binds Gsb services)
- `PC → SM`: FORBIDDEN with seven documented allowlist entries — see
  `tests/Architecture/BoundariesTest::PC_ALLOWLIST`

## When to CI-verify

Every plugin/integration commit should:

1. Run `php artisan test` — check `test_platform_does_not_import_service_modules` passes
2. Run `npx depcruise --config .dependency-cruiser.cjs src` from `frontend/` — check no violations

Both are wired into the test job in CI.
