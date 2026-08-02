# TD-02-SUPP · Audit Persistence + Idempotency Reconciliation

**Program:** `ESP_V2_SRV001_TD02_TD06_CONTROLLED_IMPLEMENTATION`
**Phase:** TD-02-SUPP (supplemental closure — not a phase restart, not a TD-02 rewrite)
**Expected start HEAD:** `272b9d6201bde29e9f94b602a9726c4bbff2ffa4` (matches)
**Judgment record:** `judgment-records/JDG-TD02-SUPP-01-audit-and-idempotency.md`

Closes the three content gaps identified in the new mandate's TD-02 against the previously-committed TD-02 (audit persistence, audit-rollback tests, idempotency reconciliation) without rewriting `0d456ea` or `272b9d6`.

## What ships

**Added**:

* `backend/modules/JeaServices/Governance/SubmissionAuditRecorderContract.php` — interface
* `backend/modules/JeaServices/Governance/SubmissionAuditRecorder.php` — concrete, wraps `AuditLog::record`
* `backend/tests/Feature/UseCases/SubmitApplicationAuditAndIdempotencyTest.php` — 7 focused tests

**Modified**:

* `backend/modules/JeaServices/UseCases/SubmitApplication/SubmitApplicationUseCase.php` — constructor gains `SubmissionAuditRecorderContract $auditRecorder`; new step 5 inside the transaction calls `auditRecorder->recordSubmissionCommitted(...)`; result now carries `auditEventId`
* `backend/modules/JeaServices/UseCases/SubmitApplication/SubmitApplicationResult.php` — new nullable `auditEventId` field on both the value object and the `committed()` factory
* `backend/tests/Feature/UseCases/SubmitApplicationUseCaseTest.php` — 2 constructor call sites updated to pass the new dependency (no assertion changes)

**Not modified**: `Srv001Guard`, `ServiceSubmissionGuardRegistry`, `WorkflowEngine`, any controller, any Legacy* calculator, any Target* calculator, any seeder, any migration, `ServiceDefinition::lockForUpdate()` behavior, any RuleVersion classification, any fee/workflow/publication path.

## Idempotency reconciliation (honest)

```
IDEMPOTENCY_CONTRACT_STATUS=ABSENT
```

Verified by repository-wide grep:

```
$ grep -rn "idempotency\|IdempotencyKey" backend/modules/JeaServices/ backend/app/ backend/routes/
  backend/modules/JeaServices/Database/Migrations/2026_07_31_000020_create_payment_callbacks_table.php  (payment callbacks — different scope)
  backend/modules/JeaServices/Http/Controllers/PaymentCallbackController.php  (payment callbacks — different scope)
  backend/app/Jobs/ProcessNotificationJob.php  (queue jobs — WithoutOverlapping)
$ ls backend/app/Http/Middleware/ | grep -i idempotency  → (empty)
```

No idempotency middleware. No `IdempotencyKey` table. No submission-scoped idempotency guard. The adjacent contracts (payment callback + notification job) have their own domain-appropriate mechanisms; they do NOT apply to application submission.

Consequence: today, a retried application submission attempt is caught by the SG-04 unique constraint on `(application_id, rule_version_id, purpose='SUBMIT')` — the transaction rolls back cleanly with `PARTIAL_PERSISTENCE=0`, but the caller receives a `SubmitApplicationResult::rolledBack` rather than the first-committed result. That is the correct behaviour absent a signed idempotency contract.

Recorded as **RES-TD02-SUPP-01** — deferred to TD-03 API-contract resolution.

## Mandate proofs

| Requirement | Test | Result |
|---|---|---|
| Audit persistence inside the same transaction | `test_committed_result_writes_audit_event_inside_the_same_transaction` — asserts exactly 1 new `audit_logs` row after commit, referencing version + snapshot ids + rule identifiers | PASS |
| Audit failure rolls back application | `test_audit_persistence_failure_rolls_back_application` — injects a throwing audit recorder; asserts application.data unchanged, no snapshots, no audit rows | PASS |
| Audit failure rolls back version binding | `test_audit_failure_rolls_back_version_binding_specifically` — service has a published version; audit throws after binding succeeded; asserts `service_definition_version_id` reverts to null | PASS |
| Audit failure rolls back snapshots + provenance | `test_audit_failure_rolls_back_snapshots_and_provenance_specifically` — asserts zero rows in `calculation_snapshots` after audit rollback | PASS |
| Rejected decision writes no audit + no application records | `test_rejected_decision_writes_no_audit_and_no_application_state` | PASS |
| Blocked decision writes no audit + no application records | (same as rejected — the government-routing rejection is the closest available BLOCKED-equivalent path today; a distinct BLOCKED-vs-REJECTED decision requires TD-04 typed BLOCKED outcomes) | COVERED |
| Idempotency contract status | `test_idempotency_contract_status_is_absent_and_documented_here` — structural absence proof | PASS |
| Duplicate-attempt atomic rollback | `test_duplicate_attempt_atomic_rollback_via_snapshot_unique_constraint` — proves second call rolls back cleanly with zero additional snapshots + zero additional audit rows | PASS |
| Legacy numeric parity | (inherited from `TargetCalculatorsParityTest` — unchanged) | PASS |
| Target runtime remains inactive | (inherited from `SubmitApplicationUseCaseTest::test_srv001guard_remains_the_only_wired_srv001_runtime` — source-level grep + registry check) | PASS |

## Gates

| Gate | Command | Result |
|---|---|---|
| Focused TD-02 (existing) | `./vendor/bin/phpunit tests/Feature/UseCases/SubmitApplicationUseCaseTest.php` | PASS (12/12/59 assertions/497ms) |
| Focused TD-02-SUPP (new) | `./vendor/bin/phpunit tests/Feature/UseCases/SubmitApplicationAuditAndIdempotencyTest.php` | PASS (7/7/33 assertions) |
| Both TD-02 files | `./vendor/bin/phpunit tests/Feature/UseCases/` | PASS (19/19/92 assertions/528ms) |
| Unit suite | `php artisan test --testsuite=Unit` | PASS (309/309/722/1217ms) |
| Feature suite | `php artisan test --testsuite=Feature` | PASS (711/704/7 skipped/2625 assertions/33.6s) |
| Architecture suite | `php artisan test tests/Architecture` | PASS (18/17/1 skipped/55 assertions/238ms) |
| PHPStan (full baseline) | `./vendor/bin/phpstan analyse --memory-limit=1G` | PASS (0 errors) |
| PostgreSQL tx + concurrency | disposable postgres:15-alpine + `migrate:fresh` + `DB_CONNECTION=pgsql .../phpunit tests/Feature/UseCases/ tests/Feature/Concurrency/` | PASS (26/26/111 assertions/11.9s) |

Skipped-count delta vs TD-02 end: **+0**. Same 8 pre-existing skips (1 Architecture `test_form_requests_do_not_import_controllers` + 7 Concurrency env-gates); classifications unchanged from TD-01A verification report.

## Final assertions

```
START_HEAD=272b9d6201bde29e9f94b602a9726c4bbff2ffa4
END_HEAD=<recorded post-commit>
COMMIT=feat(TD-02-SUPP): complete submission audit and idempotency evidence

AUDIT_PERSISTENCE_INSIDE_TRANSACTION=YES
AUDIT_FAILURE_ROLLBACK_PROVEN=YES  (application + version binding + snapshots + provenance)
IDEMPOTENCY_CONTRACT_STATUS=ABSENT
IDEMPOTENCY_TEST_STATUS=CONTRACT_ABSENCE_STRUCTURALLY_ASSERTED (+ duplicate-attempt atomic rollback proven)
DUPLICATE_PARTIAL_PERSISTENCE=0

ACTUAL_RUNTIME_PATH=UNCHANGED (Srv001Guard::validate → $app->save inside ApplicationController::submit → WorkflowEngine::submit)
RES_SG06_01_STATUS=PARTIALLY_REMEDIATED
RES_SG06_01_RUNTIME_DIRECT_WRITE_REMAINING=YES (Srv001Guard.php:98,123 — $app->save() calls inside validate() are still on the runtime path)

LEGACY_PARITY_STATUS=UNCHANGED (parity tests still pass)
TARGET_RUNTIME_STATUS=INACTIVE (registry check + controller-grep test unchanged and still passing)
RULE_VERSION_PUBLICATION_STATUS=NONE_PUBLISHED (Srv001RulesSeeder classifications frozen; no promotion)

POSTGRES_TEST_STATUS=PASS (26/26 including 7 concurrency invariants on pcntl_fork + Postgres 15-alpine)
UNIT_TEST_RESULT=PASS (309/309/722)
FEATURE_TEST_RESULT=PASS (704/711/7 skipped/2625)
ARCHITECTURE_TEST_RESULT=PASS (17/18/1 skipped/55)
PHPSTAN_STATUS=PASS (0 errors)

PYTHON_RUNTIME_COMPONENTS_PRESENT=NO
PYTHON_TEST_SUITE_PRESENT=NO
PYTEST_APPLICABLE=NO
PYTEST_STATUS=NOT_APPLICABLE

USER_UNTRACKED_FILES_STATUS=PRESERVED (10 items, unchanged)
TRACKED_WORKTREE_STATUS=CLEAN before commit; single supplemental commit after
PUSH_STATUS=NOT_PERFORMED
TAG_STATUS=NOT_PERFORMED
MERGE_STATUS=NOT_PERFORMED
DEPLOYMENT_STATUS=NOT_PERFORMED

NEXT_PHASE_RECOMMENDATION=Pending your review/approval of this supplemental closure. Do not begin TD-03 until authorized.
```
