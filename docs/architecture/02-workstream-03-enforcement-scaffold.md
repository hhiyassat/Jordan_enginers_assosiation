# Workstream 3 — Architecture enforcement scaffold

Reference: `docs/architecture/01-refactoring-plan.md` §5 (workstream #3)
and §6 (enforcement design).

**Status:** ✅ complete (delivered on `refactor/architecture-baseline-and-plan`).

## Scope

Land the enforcement tooling BEFORE any file moves. Every rule ships
as a **hard assertion for invariants that already hold** in the current
codebase — nothing weakened, nothing broken. Rules that would only
make sense after Workstream 5..14 (cross-module import checks,
platform-cannot-import-service checks) are documented in the plan but
NOT added here — they arrive when their subject matter exists.

Two artifacts:

1. **Frontend** — `dependency-cruiser` scans TypeScript imports, exits
   non-zero on violations. Wired as `npm run arch:check`.
2. **Backend** — a `tests/Architecture/` PHPUnit suite that greps the
   `app/` source tree. Runs via `php artisan test --testsuite=Architecture`.

## Files added

```
frontend/.dependency-cruiser.cjs                     (new — 100 lines)
frontend/package.json                                (+2 scripts)
backend/tests/Architecture/BoundariesTest.php        (new — 155 lines)
backend/phpunit.xml                                  (+1 testsuite)
docs/architecture/02-workstream-03-enforcement-scaffold.md  (this file)
```

Zero source files touched. Zero behaviour changes. The only new
package pulled is `dependency-cruiser` as a devDependency.

## Rules — frontend

| rule | severity | catches |
|------|----------|---------|
| `no-circular` | warn | Cycles that break tree-shaking. |
| `utils-cannot-import-pages` | warn | `src/utils/**` reaching into a page. |
| `api-cannot-import-pages` | warn | Data layer knowing about a screen. |
| `components-ui-cannot-import-pages` | warn | Design system depending on a page. |
| `layout-cannot-import-pages` | warn | Shell reaching into a page (vs routing to one). |
| `auth-cannot-import-pages` | warn | Auth primitives depending on a page. |
| `i18n-cannot-import-pages` | warn | i18n framework depending on a page. |
| `engine-cannot-import-pages` | warn | DynamicForm etc. knowing about the page hosting it. |
| `types-cannot-import-runtime` | warn | Type module dragging runtime code. |
| `no-orphans` | warn | Dead modules; composition roots excluded. |

Severity is `warn` today (dep-cruiser exits 0). This lets us **land the
rules now** and promote to `error` per-rule as the module split
completes (Workstream 15). Every rule is a fact about today's baseline
— running `npm run arch:check` on the current tree produces zero
violations across 160 modules and 368 dependencies.

## Rules — backend

| test | invariant |
|------|-----------|
| `test_models_do_not_import_controllers` | Models are data + query scopes. |
| `test_middleware_does_not_import_controllers` | Middleware sits between request and controller. |
| `test_services_do_not_import_controllers` | Services / Engine are the application layer. |
| `test_console_commands_do_not_import_controllers` | Cron / artisan entry points call services, not controllers. |
| `test_form_requests_do_not_import_controllers` | FormRequests are pure validation. |
| `test_controller_size_health_check` | No controller > 500 lines; baseline captures the current 2 offenders (Application, Admin). Any NEW breach fails immediately. |

All 6 pass on the baseline commit (`bc8aaed`).

## How to run

```bash
# Frontend
cd frontend
npm run arch:check           # depcruise; exit 0 today, will bite once rules promote
npm run arch:graph           # optional: SVG of the dependency graph (needs graphviz)

# Backend (part of the regular test run)
cd backend
php artisan test --testsuite=Architecture
php artisan test             # includes Architecture + Feature + Unit
```

## Acceptance criteria — met

| criterion | result |
|-----------|--------|
| dep-cruiser + eslint-plugin-import rules land | ✅ dep-cruiser landed (chose one tool for one job — eslint-plugin-import is redundant for our purposes) |
| First `ArchitectureBoundariesTest` file exists (may be empty) | ✅ `BoundariesTest.php` with 6 passing checks |
| Rules start as warnings, not errors | ✅ dep-cruiser: warn; PHPUnit: assertions that pass today |
| No source file moved | ✅ zero source files touched |
| Existing suites still green | ✅ Backend 551 (+6) / 2043, Frontend 410 unchanged |

## Deferred to later workstreams

The plan doc's §6 sketches these harder rules — they need the
platform / modules / plugins folders to exist first:

- `platform-cannot-import-modules` — no path yet.
- `module-cannot-import-another-module` — no path yet.
- `service-cannot-reach-platform-internals` — no `internal/` marker yet.
- `no-cross-module-nav-import` — no NavRegistry yet.
- Backend `test_platform_does_not_reference_a_service_module` — no
  `platform/` folder yet.
- Backend `test_module_does_not_reference_another_module` — same.

These land incrementally in Workstreams 5, 7, 8, 10, 13. Workstream 15
finally promotes every rule to `error` and adds the last cross-module
checks.

## Risks

- **Low:** dep-cruiser is well-maintained and adds ~10 MB to
  `node_modules` (dev only). If the team dislikes it, swap for
  `eslint-plugin-import` — the rule shapes are near-identical.
- **None:** the PHPUnit boundary test greps files; it can't affect
  any runtime code path.

## Next workstream

Workstream 4 (mechanical file tagging) is the natural next step —
adds `@platform` / `@jea` / `@plugin` JSDoc + PHP docblock tags per
file. Also zero risk, high signal.

Or, if you want to jump to real code decomposition:
Workstream 5 (split `AdminController` + `ApplicationController`).
