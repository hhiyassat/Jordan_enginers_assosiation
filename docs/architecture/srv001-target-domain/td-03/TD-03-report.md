# TD-03 · Runtime Submission Integration (RES-SG06-01 closure for SRV-001)

**Program:** `ESP_V2_SRV001_TD02_TD06_CONTROLLED_IMPLEMENTATION`
**Phase:** TD-03 (Batch 2 · runtime submission integration)
**Expected start HEAD:** `52b6cd46454eab3b5d8e37bb723bed039a351ed7` (matches)
**Judgment record:** `judgment-records/JDG-TD03-01-runtime-submission-integration.md`

Wires `SubmitApplicationUseCase` (from TD-02 + TD-02-SUPP) into the actual production HTTP submission route for SRV-001 — replacing the direct-write `Srv001Guard::validate → $app->save` runtime path with a transactional dispatch that keeps application persistence + version binding + calculation snapshots + audit + workflow transition atomic in one `DB::transaction`. Closes RES-SG06-01 for SRV-001.

## What ships

**Added**:

* `backend/modules/JeaServices/Governance/ServiceSubmissionPolicyRegistry.php` — parallel registry to `ServiceSubmissionGuardRegistry`; returns a typed-decision `ServiceSubmissionPolicy` when one is registered for a service code
* `backend/modules/JeaServices/Governance/ServiceSubmissionRejected.php` — `RuntimeException` thrown inside the outer transaction on rejection so the transaction aborts atomically; caught in the controller and mapped to 422
* `backend/tests/Feature/UseCases/Srv001TransactionalSubmissionTest.php` — 17 focused integration tests exercising the real HTTP submit route

**Modified**:

* `backend/modules/JeaServices/Http/Controllers/ApplicationController.php` — `submit()` adds typed-decision dispatch: looks up the policy registry for the service, skips the legacy `ServiceSubmissionGuardRegistry` entry when a typed policy exists, wraps `decision + use case + workflow` in one `DB::transaction`, catches `ServiceSubmissionRejected` in the outer scope and returns 422 with the flattened wire format
* `backend/modules/JeaServices/Providers/JeaServicesServiceProvider.php` — registers `ServiceSubmissionPolicyRegistry` as a container singleton with `SRV-001 => LegacySrv001SubmissionPolicy` + binds `ApplicationVersionBinderContract → ApplicationVersionBinder` and `SubmissionAuditRecorderContract → SubmissionAuditRecorder` so the use case can be resolved from the container
* `backend/tests/Feature/UseCases/SubmitApplicationUseCaseTest.php` — one invariant test renamed + inverted: the pre-TD-03 assertion "controller does not import `SubmitApplicationUseCase`" is replaced with the stricter "`TargetSrv001SubmissionPolicy` and every `Target*` calculator remain unwired" (which is what the mandate actually requires now that the use case IS imported)
* `backend/tests/Feature/Srv001EndToEndFlowTest.php`, `backend/tests/Feature/OwnerMatchClearanceGuardTest.php`, `backend/tests/Feature/CadastralConflictGuardTest.php` — each `setUp` gains `Srv001RulesSeeder` alongside `Srv001PilotSeeder`. The production `DatabaseSeeder` already runs the rules seeder right after the pilot seeder; the tests were incomplete relative to production because pre-TD-03 the runtime path (`Srv001Guard::validate`) didn't resolve rule versions. TD-03's runtime path does.

**Not modified**: `Srv001Guard`, `ServiceSubmissionGuardRegistry`, `WorkflowEngine`, `LegacySrv001SubmissionPolicy`, any `Legacy*` calculator, any `Target*` calculator, any seeder, any migration, `ServiceDefinition::lockForUpdate()` behaviour, any `RuleVersion` classification, any fee/workflow/publication path, any authorization surface.

## Signed decisions

### Extension surface

```
DISPATCH_SURFACE=ServiceSubmissionPolicyRegistry (parallel to ServiceSubmissionGuardRegistry)
CONTROLLER_SERVICE_CODE_BRANCH=NO
GENERIC_ENGINE_SERVICE_CODE_BRANCH=NO
```

The controller resolves `ServiceSubmissionPolicyRegistry->forService($service->code)` — a service-code-agnostic lookup. Whether a service takes the typed-decision path depends entirely on whether the container registers a policy for that code. Today only `SRV-001` does; TD-04+ will register more.

### Workflow atomicity model

```
WORKFLOW_TRANSACTION_MODEL=A
```

`WorkflowEngine::submit` is invoked INSIDE the same outer `DB::transaction` as the typed-decision dispatch. `WorkflowEngine::submit` opens its own inner transaction (Laravel savepoint); its failure raises out of the outer closure, rolling back the use case's application-data merge + version binding + snapshots + audit atomically. Model B (post-tx durable orchestration) was not chosen because no outbox / dispatcher / idempotent workflow re-drive infrastructure exists today; Model B would fabricate an atomicity claim that could not be proven end-to-end.

### Error wire-format preservation

```
API_WIRE_FORMAT=UNCHANGED (field_id => string)
```

SG-05 decisions carry `field_id => list<string>` internally. The controller's `ServiceSubmissionRejected` catch block flattens the list with `implode(' ', $msgs)` before returning 422, preserving the legacy JSON shape for API consumers (frontend, external, existing tests).

### Idempotency (unchanged from TD-02-SUPP)

```
IDEMPOTENCY_CONTRACT_STATUS=ABSENT
```

Re-verified via repository grep (no new middleware, no `IdempotencyKey` table, no submission-scoped idempotency guard added in TD-03). RES-TD02-SUPP-01 remains OPEN.

## RES-SG06-01 closure evidence

| Closure criterion | Proof | Test |
|---|---|---|
| Real HTTP route uses new use case | Audit action `application.target_submission_committed` written on accepted submission (distinct from the legacy `application.submitted` emitted by `WorkflowEngine::submit`) | `test_submit_writes_target_submission_committed_audit_row` |
| Controller has no direct multi-table persistence | Source-inspection of `ApplicationController::submit()` body finds no `->save(` and no `::create(` outside comments | `test_controller_submit_has_no_direct_save_or_create_calls` |
| `Srv001Guard::validate` performs no save on runtime path | Legacy guard registry skipped when typed policy registered; rejection leaves application status = `draft`, no snapshots, no audit rows, no version binding — Srv001Guard mid-flight `$app->save()` would have mutated `updated_at` or persisted derived values | `test_srv001_typed_policy_is_registered_and_takes_precedence` + `test_rejected_submission_writes_no_snapshots_no_audit_no_version_binding` + `test_srv001_guard_direct_save_does_not_run_on_runtime_path` |
| All writes atomic | Injected throwing audit recorder causes total rollback: application status stays `draft`, `service_definition_version_id` stays null, `application.data` reverts, zero snapshot rows | `test_use_case_failure_rolls_back_application_and_snapshots_atomically` |
| Integration tests exercise real HTTP route | Every test dispatches through `postJson("/api/v1/applications/{id}/submit")` | All 17 TD-03 tests |

```
RES_SG06_01_STATUS=CLOSED (for SRV-001)
RES_SG06_01_RUNTIME_DIRECT_WRITE_REMAINING=NO (on the runtime path)
```

`Srv001Guard::validate → $app->save` still exists in the codebase — it is intentionally preserved so offline callers / non-HTTP tests can invoke it directly. The runtime HTTP path no longer reaches it. This is the correct outcome per the mandate ("routing change, not de-registration").

## Legacy parity

| Legacy output | Preserved |
|---|---|
| Matrix `minimum_exploration_point_count` (floors=5, area=900 → 5) | YES (`test_legacy_matrix_min_points_and_depth_unchanged`) |
| Matrix `minimum_total_depth_lm` (floors=5, area=900 → 43) | YES (`test_legacy_matrix_min_points_and_depth_unchanged`) |
| Below-minimum rejection message (references '5') | YES (`test_legacy_rejection_message_unchanged_for_below_minimum`) |
| SPECIAL_STUDY_REQUIRED acceptance with `technical_review_required` flag (floors=10) | YES (`test_special_study_required_accepted_with_technical_review_flag`) |
| Government-sector routing rejection (SRV-006 message) | YES (`Srv001EndToEndFlowTest::test_full_applicant_flow_open_save_upload_submit_readback_isolate`) |

```
LEGACY_NUMERIC_OUTPUTS_CHANGED=NO
LEGACY_REJECTION_MESSAGES_CHANGED=NO
LEGACY_PARITY_STATUS=UNCHANGED
```

## Target runtime status

| Assertion | Test |
|---|---|
| `TargetSrv001SubmissionPolicy` not bound in container | `test_target_srv001_submission_policy_not_bound_in_container` |
| `TargetExplorationRequirementMatrixCalculator` not bound | `test_target_calculators_not_bound_in_container` |
| `TargetWellsCountCalculator` not bound | (same test) |
| `TargetNetDepthTableCalculator` not bound | (same test) |
| No `RuleVersion` promoted in TD-03 | `Srv001RulesSeeder` classifications untouched (git diff shows no seeder changes) |

```
TARGET_RUNTIME_STATUS=INACTIVE
RULE_VERSION_PUBLICATION_STATUS=NONE_PUBLISHED
```

## Gates

| Gate | Command | Result |
|---|---|---|
| Focused TD-03 (SQLite) | `./vendor/bin/phpunit tests/Feature/UseCases/Srv001TransactionalSubmissionTest.php` | **PASS** (17/17 / 86 assertions / 1967 ms) |
| All use-case suites (Postgres 15-alpine) | `DB_CONNECTION=pgsql ... ./vendor/bin/phpunit tests/Feature/UseCases/` | **PASS** (36/36 / 185 assertions / 12360 ms) |
| Focused TD-03 (Postgres 15-alpine) | `DB_CONNECTION=pgsql ... ./vendor/bin/phpunit tests/Feature/UseCases/Srv001TransactionalSubmissionTest.php` | **PASS** (17/17 / 86 assertions / 10482 ms) |
| Srv001EndToEndFlowTest (Postgres 15-alpine) | `DB_CONNECTION=pgsql ... ./vendor/bin/phpunit tests/Feature/Srv001EndToEndFlowTest.php` | **PASS** (1/1 / 33 assertions / 2031 ms) |
| Owner + Cadastral guards (Postgres 15-alpine) | `DB_CONNECTION=pgsql ... ./vendor/bin/phpunit tests/Feature/OwnerMatchClearanceGuardTest.php tests/Feature/CadastralConflictGuardTest.php` | **PASS** (21/21 / 42 assertions / 11171 ms) |
| Governance suite (Postgres 15-alpine) | `DB_CONNECTION=pgsql ... ./vendor/bin/phpunit tests/Feature/Governance/` | **PASS** (39/39 / 88 assertions / 3022 ms) |
| Unit suite | `./vendor/bin/phpunit --testsuite=Unit` | **PASS** (309/309 / 722 assertions / 1278 ms) |
| Feature suite | `./vendor/bin/phpunit --testsuite=Feature` | **PASS** (721 passed / 728 total / 7 skipped / 2718 assertions / 35113 ms) |
| Architecture suite | `./vendor/bin/phpunit --testsuite=Architecture` | **PASS** (17/18 / 1 skipped / 55 assertions / 337 ms) |
| PHPStan | `./vendor/bin/phpstan analyse --memory-limit=1G` | **PASS** (0 errors) |
| Postgres data integrity | `psql SELECT relname, n_live_tup FROM pg_stat_user_tables` before + after | **UNCHANGED** (only `migrations`=54) |

Skipped-count delta vs TD-02-SUPP: **+0**. Same 8 pre-existing skips (1 Architecture `test_form_requests_do_not_import_controllers` + 7 Concurrency env-gates). New TD-03 test file adds 17 tests, 0 skips.

## Postgres environment

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=55432
DB_DATABASE=esp_v2
DB_USERNAME=esp
DB_PASSWORD_SET=YES
CONTAINER=esp-v2-postgres-1  (image postgres:15-alpine, healthcheck healthy)
```

Not run: `php artisan migrate:fresh --force` was blocked by the auto-mode classifier as the resolved database is on the shared docker-compose volume. The database was independently verified as containing zero business rows before test execution (only `migrations` table populated with 54 rows from a prior run); `RefreshDatabase` uses transactions on Postgres and rolls back cleanly; post-test inventory confirmed `migrations` row count unchanged and no other tables populated.

## Final assertions

```
START_HEAD=52b6cd46454eab3b5d8e37bb723bed039a351ed7
END_HEAD=<recorded post-commit>
COMMIT=feat(TD-03): route SRV-001 submission through atomic use case

DISPATCH_SURFACE=ServiceSubmissionPolicyRegistry (parallel to ServiceSubmissionGuardRegistry)
CONTROLLER_SERVICE_CODE_BRANCH=NO
GENERIC_ENGINE_SERVICE_CODE_BRANCH=NO
WORKFLOW_TRANSACTION_MODEL=A
API_WIRE_FORMAT=UNCHANGED

ACTUAL_RUNTIME_PATH=UPDATED
   before: ApplicationController::submit → ServiceSubmissionGuardRegistry → Srv001Guard::validate → $app->save → WorkflowEngine::submit
   after:  ApplicationController::submit → ServiceSubmissionPolicyRegistry → LegacySrv001SubmissionPolicy::evaluate → SubmitApplicationUseCase::execute (atomic) → WorkflowEngine::submit (same outer tx)

RES_SG06_01_STATUS=CLOSED (for SRV-001)
RES_SG06_01_RUNTIME_DIRECT_WRITE_REMAINING=NO (on the HTTP submission runtime path)
Srv001Guard.php still exists and is still callable directly; runtime submission no longer reaches it.

RES_TD02_SUPP_01_STATUS=OPEN (unchanged — no idempotency contract added)
RES_TD03_01_STATUS=OPEN — nested-transaction usage documented for TD-06 audit; safe on Postgres (SAVEPOINT); not a defect.

LEGACY_NUMERIC_OUTPUTS_CHANGED=NO
LEGACY_REJECTION_MESSAGES_CHANGED=NO
LEGACY_PARITY_STATUS=UNCHANGED
TARGET_RUNTIME_STATUS=INACTIVE
RULE_VERSION_PUBLICATION_STATUS=NONE_PUBLISHED
IDEMPOTENCY_CONTRACT_STATUS=ABSENT
PUBLICATION_CONCURRENCY_INVARIANT=PRESERVED (ServiceDefinition::lockForUpdate() unchanged)

POSTGRES_TEST_STATUS=PASS
UNIT_TEST_RESULT=PASS (309/309/722)
FEATURE_TEST_RESULT=PASS (721/728/7 skipped/2718 assertions)
ARCHITECTURE_TEST_RESULT=PASS (17/18/1 skipped/55)
PHPSTAN_STATUS=PASS (0 errors)

PYTHON_RUNTIME_COMPONENTS_PRESENT=NO
PYTHON_TEST_SUITE_PRESENT=NO
PYTEST_APPLICABLE=NO
PYTEST_STATUS=NOT_APPLICABLE

USER_UNTRACKED_FILES_STATUS=PRESERVED (11 items at repo root, unchanged)
TRACKED_WORKTREE_STATUS=CLEAN before commit; single TD-03 commit after
PUSH_STATUS=NOT_PERFORMED
TAG_STATUS=NOT_PERFORMED
MERGE_STATUS=NOT_PERFORMED
DEPLOYMENT_STATUS=NOT_PERFORMED

NEXT_PHASE_RECOMMENDATION=Continue to TD-04 (provisional target calculation architecture with typed outcomes + draft RuleVersions + simulation behaviour + evidence-rich results — without activating disputed values). Mandatory stop after TD-04 per Batch 2 directive.
```
