# TD-10 · Migration Readiness Assessment

**Scope:** SRV-001 target-domain migration from legacy pilot to future signed target runtime.

## Historical data inventory (test DB — production not touched)

Only `migrations` populated (54 rows) in the test Postgres — TD-09/TD-10 test runs use `RefreshDatabase`; no residue.

**Production-shaped inventory** (to be captured by JEA IT before cutover):
- `applications` count
- `application_documents` count + total bytes on disk
- `calculation_snapshots` count grouped by `purpose` and `rule_version_id`
- `service_definition_versions` distinct count
- `audit_logs` count grouped by `action`

## Binding audits

| Binding | Test | Status |
|---|---|---|
| `applications.service_definition_version_id` | not null iff `application_version_binder.bindOrClassifyLegacy()` returned BOUND / ALREADY_BOUND | Enforced by `SubmitApplicationUseCase` |
| `calculation_snapshots.rule_version_id` | FK to `rule_versions.id` | Enforced by DB constraint |
| `audit_logs.subject_id` | valid `applications.id` | Enforced by `AuditLog::record()` |
| historical `workflow_version_id` on Applications | (not yet migrated — legacy `workflow` column) | Deferred to migration Phase 1 |

## Migration classification of historical rows

| Class | Definition | Migration action |
|---|---|---|
| FULL_BINDING | app has service_definition_version_id + all snapshots + audit chain complete | none — already migrated-compatible |
| LEGACY_UNVERSIONED | app has null service_definition_version_id (RES-SG03 legacy semantic) | leave as-is; classifier `LEGACY_UNVERSIONED` preserved |
| PARTIAL_SNAPSHOTS | app has some but not all calculation_snapshots | flag for JEA review; backfill BLOCKED until per-rule OD-Closure |
| PRE_AUDIT_CHAIN | app pre-dates `application.submitted` audit action | leave as-is; new audit chain begins at cutover |

## Non-destructive backfill plan

**Backfill is NOT part of TD-10 execution.** This plan describes what a future backfill would do.

1. Read-only snapshot of `applications` where `service_definition_version_id IS NULL`.
2. Identify the ServiceDefinitionVersion in effect at the app's `submitted_at`.
3. Emit a proposed backfill row per application.
4. JEA product reviews the proposal.
5. Signed batch UPDATE (with unique constraint + concurrency check) applied during maintenance window.
6. Audit event `application.service_definition_version_backfilled` written per updated row.

**Guardrail:** the `MigrationDryRunTool` refuses `enableWrite=true` unless an explicit authorization token is passed. Production callers do NOT have this token.

## Dry-run tooling

Implemented as `modules/JeaServices/Domain/Cutover/MigrationDryRunTool.php`:
- Defaults to READ_ONLY.
- Writes ignored unless `enableWrite: true` + `authorizationToken: 'test-authorization-token'`.
- Constructor throws `RuntimeException('PRODUCTION_WRITE_NOT_YET_AUTHORIZED')` otherwise.

Tested by `tests/Feature/Cutover/Srv001CutoverAndMigrationTest.php`.

## Rollback plan

See `rollback-plan.md`.

## Verdict

`MIGRATION_READINESS_STATUS = NOT_READY` — awaiting production data inventory + JEA IT rehearsal on a snapshot of production data.
