# Modules — how service modules work in ESP v2

Workstream 16 · post-W7/W8/W12/W15

## What is a module?

A **module** is a self-contained subsystem that owns a slice of domain
data and business logic. Every JEA-specific capability lives in its
own module under `backend/modules/<PascalName>/` and (post-W10)
`frontend/src/modules/<PascalName>/`.

The platform composes modules; modules never compose the platform.
Removing a module's key from `backend/config/modules.php` disables it
cleanly — routes vanish, migrations aren't discovered, artisan commands
disappear, container bindings are gone.

## Current modules

| id | backend | frontend | owns |
|----|---------|----------|------|
| `jea-services` | `backend/modules/JeaServices/` | `frontend/src/modules/JeaServices/` | Application lifecycle, service catalog, workflow engine, fees, certificates, reviewer surface |
| `jea-projects` | `backend/modules/JeaProjects/` | `frontend/src/modules/JeaProjects/` | Projects, engineers, office quotas, boost flags |
| `jea-discipline` | `backend/modules/JeaDiscipline/` | `frontend/src/modules/JeaDiscipline/` | Complaints, sanctions, legal fines (Art.14), supervision transfers |
| `jea-dues` | `backend/modules/JeaDues/` | `frontend/src/modules/JeaDues/` | Recurring dues (F-04 registration, F-05 annual), late surcharges, expiry reminders |

## What a module owns

Backend (`backend/modules/<Name>/`):

```
Console/Commands/         — module cron / artisan commands
Database/Migrations/      — module tables + column alterations
Database/Seeders/         — module domain data
Engine/                   — module business logic primitives
Http/
  Controllers/            — module HTTP handlers
  Requests/               — module FormRequests
Models/                   — module Eloquent models
Providers/                — the module's ServiceProvider (one file)
Services/                 — module application services
routes.php                — module route file (loaded by provider)
```

Frontend (`frontend/src/modules/<Name>/`):

```
pages/                    — module React pages (lazy-loaded)
  reviewer/               — nested role-scoped subdirs OK
components/               — module-specific components (if any)
```

Everything the module reads about itself (its own models, its own
schema, its own workflow rules) lives IN the module. Everything the
module reads about the platform (User, Organization, AuditLog,
Notification) lives in `backend/app/` and is imported via
`use App\Models\User;`.

## Cross-module reads

Sometimes a module legitimately needs to read another module's data.
Example: `jea-projects` reads `Modules\JeaServices\Models\Application`
for quota accounting. The rule is:

- **SM → SM reads are allowed** and don't need any special ceremony —
  just `use Modules\OtherModule\Models\...;`
- **SM → PC reads are always allowed** (that's the direction the
  architecture allows).
- **PC → SM reads are FORBIDDEN** and enforced by
  `tests/Architecture/BoundariesTest::test_platform_does_not_import_service_modules`.
  Known legacy exceptions live in a documented allowlist.

Documented SM → SM contracts today:

| reads | reader | why |
|-------|--------|-----|
| `Modules\JeaServices\Models\Application` | `jea-projects` (QuotaLedger, CapacityGuard) | quota accounting |
| `Modules\JeaServices\Models\Application` | `jea-discipline` (LegalFine, SupervisionTransfer, RemindExpiries) | FK targets + expiry scan |
| `Modules\JeaProjects\Engine\{QuotaLedger,CapacityGuard}` | `jea-services` (WorkflowEngine) | submit-time gate |
| `Modules\JeaDiscipline\Engine\SanctionGuard` | `jea-services` (WorkflowEngine) | submit-time gate |
| `Modules\JeaProjects\Models\Project` | `jea-services` (Application::project()) | belongsTo relation |

None of these are cycles — the graph is a DAG:

```
jea-services ← jea-projects ← jea-discipline
                            ↖
jea-services ← jea-discipline
```

## Disable order

If you turn off multiple modules simultaneously, order matters. A
module that reads Application still runs its bindings during boot even
if that Application table doesn't exist. Safe disable order (top-to-
bottom = disable this LAST):

1. `jea-services` — depended on by everyone else
2. `jea-projects` — depended on by `jea-discipline` (SupervisionTransfer)
3. `jea-discipline`
4. `jea-dues` — no dependencies on other modules

## Adding a new module

See [`06-adding-a-service-module.md`](06-adding-a-service-module.md).

## Related docs

- [`00-baseline.md`](00-baseline.md) — frozen pre-refactor state
- [`01-refactoring-plan.md`](01-refactoring-plan.md) — the full 16-workstream plan
- [`03-file-classification.md`](03-file-classification.md) — per-file architecture tags
- [`05-plugins-and-integrations.md`](05-plugins-and-integrations.md) — plugins & integrations vs modules
- [`07-adding-a-plugin.md`](07-adding-a-plugin.md) — adding a plugin
