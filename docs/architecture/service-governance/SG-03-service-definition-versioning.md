# SG-03 · Service-Definition Versioning

**Program:** `ESP_V2_SERVICE_GOVERNANCE_VERSIONING_FOUNDATION`
**Phase:** SG-03
**Baseline HEAD:** `e7e9a77...` (post SG-02)

Introduces the immutable `service_definition_versions` table + `applications.service_definition_version_id` FK. Applications bind to the currently-published version at submit time. Legacy unversioned applications are classified explicitly — never silently attached.

Closes **RES-SG00-01** (snapshot scope) via `JDG-SG03-01`. Opens **RES-SG03-01** (extension-declaration snapshotting) for a post-SG-06 follow-up.

## Data model

Migration `2026_08_01_000020_create_service_definition_versions_table.php`:

* `service_definition_id` FK cascade delete
* `version_identifier` string (e.g. `v1.0.0-2026-08-01`) — unique per service
* `schema_snapshot` JSON — immutable copy of `service_definitions.schema` at publish time
* `schema_hash` char(64) — SHA-256 of canonical JSON (key-order-stable)
* `status` enum(DRAFT, PUBLISHED, SUPERSEDED, RETIRED) default DRAFT
* `effective_from`, `effective_to` timestamps nullable
* `created_by`, `approved_by`, `published_by` FK → users nullable
* `approval_reference`, `approval_notes` text nullable
* `published_at` timestamp nullable
* `supersedes_version_id` FK → self nullable

Migration `2026_08_01_000021_add_service_definition_version_id_to_applications.php`:

* `applications.service_definition_version_id` FK → `service_definition_versions.id` nullable, nullOnDelete

## Immutability enforcement

`ServiceDefinitionVersion` uses a `saving` observer that refuses updates to a published version's frozen columns. Only these columns may change after publication (lifecycle transitions):

* `status` (DRAFT → PUBLISHED → SUPERSEDED → RETIRED)
* `effective_to` (set when superseded)
* `supersedes_version_id` (linkage to newer version)
* `updated_at`

Attempts to modify `schema_snapshot`, `schema_hash`, `approval_reference`, etc. throw `RuntimeException`. Verified by `test_published_version_rejects_schema_snapshot_edit`.

## Publishing flow

`ServiceVersionPublisher::publishNewVersion(...)` in a single transaction:

1. Insert version with `status='DRAFT'`, snapshot of current schema.
2. If a prior published version exists, mark it `SUPERSEDED` and set its `effective_to`.
3. Transition the new version to `PUBLISHED`; stamp `published_at`, `published_by`, `effective_from`.

Publisher does NOT re-run SG-01's `ServicePublicationPolicy` — callers are responsible for policy compliance. This keeps concerns separated (policy = decision; publisher = persistence).

## Application binding

`ApplicationVersionBinder::bindOrClassifyLegacy($app)` in `WorkflowEngine::submit`:

* If already bound → returns `ALREADY_BOUND` (idempotent — never switches to a newer version).
* If service has a published version → assigns FK, returns `BOUND`.
* Otherwise → leaves FK null, returns `LEGACY_UNVERSIONED`.

`Application::legacyVersioningClassification()` returns `'BOUND'` or `'LEGACY_UNVERSIONED'` for reporting.

## Judgment decisions

* **JDG-SG03-01** (snapshot scope): schema only. Extension-declaration snapshotting deferred to RES-SG03-01.
* **JDG-SG03-02** (binding timing): bind at submit. Drafts remain unbound.
* **JDG-SG03-03** (legacy migration): no back-fill. Existing rows classified `LEGACY_UNVERSIONED`.

## Historical preservation

Per JDG-SG03-03:

* No UPDATE statement runs against existing `applications` rows during migration.
* Existing applications continue to resolve their schema from `service_definitions.schema` (current) — a documented interpretation of `LEGACY_UNVERSIONED`.
* Submitting an application after the versioning table exists but before any version is published still binds to `LEGACY_UNVERSIONED` — the FK is null.
* Historical reproducibility for legacy rows is not increased by SG-03 (that was never possible before) but is not decreased either.

## Files added

* `backend/modules/JeaServices/Database/Migrations/2026_08_01_000020_create_service_definition_versions_table.php`
* `backend/modules/JeaServices/Database/Migrations/2026_08_01_000021_add_service_definition_version_id_to_applications.php`
* `backend/modules/JeaServices/Models/ServiceDefinitionVersion.php`
* `backend/modules/JeaServices/Governance/ServiceVersionPublisher.php`
* `backend/modules/JeaServices/Governance/ApplicationVersionBinder.php`
* `backend/tests/Feature/Governance/ServiceDefinitionVersioningTest.php`
* `docs/architecture/service-governance/judgments/JDG-SG03-01-snapshot-scope.md`
* `docs/architecture/service-governance/judgments/JDG-SG03-02-binding-timing.md`
* `docs/architecture/service-governance/judgments/JDG-SG03-03-legacy-unversioned-policy.md`

## Files modified

* `backend/modules/JeaServices/Models/Application.php` — added `service_definition_version_id` fillable + `serviceDefinitionVersion()` relation + `legacyVersioningClassification()` accessor + property annotation.
* `backend/modules/JeaServices/Engine/WorkflowEngine.php` — imports + one-line call to `ApplicationVersionBinder::bindOrClassifyLegacy` inside the submit transaction.

## Gates

| Gate | Result |
|---|---|
| Focused versioning tests | PASS (9 / 9 / 20 assertions) |
| Regression sweep (Workflow/Submit/Application/ServiceDefinition/Governance/Catalog/Srv001) | PASS (226 / 227 / 1 skipped) |
| PHPStan (new files) | PASS (0 errors) |
| PHPStan (full baseline, `--memory-limit=1G`) | PASS (0 errors) |

## Concurrency and transaction boundaries

* Version creation runs in a single `DB::transaction`. The DRAFT → PUBLISHED transition is two saves inside that transaction so the immutability observer sees the previous status correctly.
* Simultaneous publish attempts against the same service will both succeed and both create rows; the LAST to commit becomes the currently-published (the other is immediately superseded). The `unique(service_definition_id, version_identifier)` constraint prevents duplicate identifiers.
* Application binding at submit runs inside `WorkflowEngine::submit`'s existing transaction — no new boundary introduced.

## Residuals

| RESIDUAL_ID | Owner | Status | Notes |
|---|---|---|---|
| RES-SG00-01 | JDG-SG03-01 | CLOSED | Snapshot scope decided: schema only |
| RES-SG03-01 | post-SG-06 | OPEN | Extension-declaration snapshotting once registry supports per-version binding |
| RES-SG03-02 | UX follow-up | OPEN | Optional "will be bound to version X at submit" hint on draft view |
| RES-SG03-03 | ops | OPEN | Manual per-application attachment procedure (with maker-checker) |

## Verdict

**PASS** — Immutable version entity + application binding + legacy classification in place. All existing applications remain classified `LEGACY_UNVERSIONED`; new submissions bind at submit time when a published version exists.
