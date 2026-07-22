# Adding a service module

Workstream 16 walkthrough. This is what W7 / W8A / W8B / W8C each did
in commit form; repeat these steps for a new module.

## 1. Choose an id and a namespace

- id: lower-kebab-case (e.g. `jea-payments`)
- namespace: PascalCase directory (`Modules\JeaPayments\`)
- Folder: `backend/modules/JeaPayments/`
- Frontend folder (if the module has pages): `frontend/src/modules/JeaPayments/`

The pattern name is `jea-<domain>` for JEA modules. A future
non-JEA tenant would use a different prefix.

## 2. Scaffold the directory tree

```bash
mkdir -p backend/modules/JeaPayments/{Console/Commands,Database/{Migrations,Seeders},Engine,Http/{Controllers,Requests},Models,Providers,Services}
```

Not every subdirectory needs to exist — only what the module has.

## 3. Write the service provider

`backend/modules/JeaPayments/Providers/JeaPaymentsServiceProvider.php`:

```php
<?php
declare(strict_types=1);
namespace Modules\JeaPayments\Providers;
use Illuminate\Support\ServiceProvider;
use Modules\JeaPayments\Console\Commands\ChargeRecurring;

class JeaPaymentsServiceProvider extends ServiceProvider
{
    public function register(): void { /* container bindings */ }

    public function boot(): void
    {
        $moduleRoot = dirname(__DIR__);
        $this->loadRoutesFrom($moduleRoot . '/routes.php');
        $this->loadMigrationsFrom($moduleRoot . '/Database/Migrations');
        if ($this->app->runningInConsole()) {
            $this->commands([ChargeRecurring::class]);
        }
    }
}
```

## 4. Write `routes.php`

`backend/modules/JeaPayments/routes.php`:

```php
<?php
declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use Modules\JeaPayments\Http\Controllers\PaymentsController;

// IMPORTANT: spell out 'api/v1' — apiPrefix from bootstrap/app.php
// only applies to routes/api.php, NOT to modules' loadRoutesFrom.
Route::prefix('api/v1')
    ->middleware(['auth:sanctum', 'token.inactivity', 'password.policy', 'track.activity'])
    ->group(function () {
        Route::middleware('role:applicant,staff,auditor,admin')->group(function () {
            Route::get('payments', [PaymentsController::class, 'index']);
        });
        Route::middleware('role:admin,superuser')->group(function () {
            Route::post('admin/payments/{id}/refund', [PaymentsController::class, 'refund']);
        });
    });
```

## 5. Add the module to the registry

`backend/config/modules.php`:

```php
return [
    'enabled' => [
        // ... existing entries ...
        'jea-payments' => \Modules\JeaPayments\Providers\JeaPaymentsServiceProvider::class,
    ],
];
```

Order matters when modules depend on each other's bindings.
`jea-services` is registered first so downstream modules that need
`Application` see a fully-booted service module.

## 6. Regenerate composer autoload

```bash
composer dump-autoload -o
```

## 7. Watch for the same-namespace short-ref trap

If your module models call `belongsTo(User::class)` unqualified, PHP
resolves the short reference in the file's own namespace. Under
`App\Models\Foo` that used to resolve to `App\Models\User` — after
moving to `Modules\JeaPayments\Models\Foo` it resolves to
`Modules\JeaPayments\Models\User` which doesn't exist.

Fix: always add explicit imports for classes from other namespaces:

```php
use App\Models\User;
use Modules\JeaServices\Models\Application;
```

Every workstream from 8A onward hit this trap. It's the single most
common source of "class not found" errors during a module extraction.

## 8. Verify the disable-acceptance

Once tests pass, verify the module can actually be disabled:

```bash
# Baseline: count routes
php artisan route:list | grep -cE "payments"     # → N

# Disable
sed -i '' "s|'jea-payments'.*=> .*|/* disabled */|" backend/config/modules.php
php artisan route:list | grep -cE "payments"     # → 0

# Re-enable
git checkout backend/config/modules.php
```

Every workstream commit message documents its disable-acceptance count.

## 9. Move / write frontend pages (if applicable)

`frontend/src/modules/JeaPayments/pages/`:

- Each page is a named `.tsx` export.
- Register the page in the top-level composition (`src/routes.tsx`)
  via `React.lazy`.
- Imports from the platform: use `frontend/src/platform/*` paths
  (design system, utils, hooks).

## 10. Update classification manifest

Add every new file to `docs/architecture/03-file-classification.md`
with tag `SM` and short rationale.

## 11. Add tests

Every route + service must have a PHPUnit feature test AND (if the
module has UI) a Vitest test. The user's standing rule is:

> Always write PHPUnit + Vitest tests for every feature.

Not just app code — every feature.

## Common gotchas

- `apiPrefix` from `bootstrap/app.php` only applies to
  `routes/api.php`. Module routes need explicit `Route::prefix('api/v1')`.
- After deleting or renaming files, run `composer dump-autoload -o`
  before running tests. The stale classmap will bite you otherwise.
- `bootstrap/cache/services.php` is Laravel's runtime cache. Revert it
  before every commit: `git checkout HEAD -- backend/bootstrap/cache/services.php`.
- Inline FQN references (`\App\Models\Foo::class`) don't get touched
  by a `use` statement sweep. Grep for them explicitly.
