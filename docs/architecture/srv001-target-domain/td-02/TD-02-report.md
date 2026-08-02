# TD-02 · Atomic Submission Use Case + Snapshot Writer + Version Binding

**Program:** `ESP_V2_SRV001_TARGET_DOMAIN_IMPLEMENTATION`
**Phase:** TD-02
**Baseline HEAD (start):** `0d456ea` (post TD-01B architecture-gate alignment)
**Judgment record:** `judgment-records/JDG-TD02-01-atomic-submission-use-case.md`

Implements JDG-TD00-02 authorised scope item #2: rule-version + snapshot writer end-to-end wiring, in the shape of an application-submission USE CASE that composes SG-03 (version binding), SG-04 (calculation snapshots), and SG-05 (typed decision) inside a single DB transaction. **Not wired to runtime.**

## What ships

### New

* `backend/modules/JeaServices/UseCases/SubmitApplication/SubmitApplicationUseCase.php` — the orchestrator.
* `backend/modules/JeaServices/UseCases/SubmitApplication/SubmitApplicationResult.php` — typed result (rejected / committed / rolledBack).
* `backend/modules/JeaServices/Governance/ApplicationVersionBinderContract.php` — small interface extracted from the concrete `final ApplicationVersionBinder` to enable test doubles.
* `backend/tests/Feature/UseCases/SubmitApplicationUseCaseTest.php` — 12 tests, 59 assertions.

### Modified

* `backend/modules/JeaServices/Governance/ApplicationVersionBinder.php` — one-line change: `implements ApplicationVersionBinderContract`. No behaviour change.

### NOT modified

* No controller (Application / Payments / Certificates / Workflow) imports the new use case.
* `Srv001Guard`, `ServiceSubmissionGuardRegistry`, `WorkflowEngine::submit` — all untouched.
* No fee, workflow, publication, or numeric-output change.
* No `RuleVersion` promoted; `Srv001RulesSeeder` classifications frozen.

## Design summary

`SubmitApplicationUseCase::execute(Application, ServiceSubmissionDecision, User)`:

1. If `decision->accepted === false` → return `SubmitApplicationResult::rejected($errors)` immediately, no DB touched.
2. Otherwise inside `DB::transaction(...)`:
   1. Merge `$decision->derivedValues` into `application->data`
   2. Call `versionBinder->bindOrClassifyLegacy($application)` — assigns FK if published version exists
   3. `$application->save()` — persists data + version_id together
   4. For each snapshot payload in `$decision->calculationSnapshots`, call `CalculationSnapshotWriter::writeForSubmit(...)` — inserts SUBMIT snapshot referencing the correct RuleVersion (SG-04 immutability + unique-constraint invariants apply)
3. On any `Throwable` inside the transaction closure → whole transaction rolls back, use case returns `SubmitApplicationResult::rolledBack($reason)`.

The use case is **service-code agnostic** — it never checks or branches on `service_code`. Any policy (Legacy or Target) can produce a ServiceSubmissionDecision that this use case commits.

## Mandate proofs

Each required proof + the test method that proves it:

| Claim | Test | Result |
|---|---|---|
| `SUBMISSION_TRANSACTION_ATOMIC=YES` | `test_committed_result_persists_data_binds_version_writes_snapshots_all_together` — verifies all three side effects reach DB together on success | PASS |
| `SNAPSHOT_FAILURE_ROLLS_BACK_APPLICATION=YES` | `test_snapshot_failure_rolls_back_application_data_and_version_binding` — pre-inserts a conflicting SUBMIT snapshot; invokes use case; asserts `application.data` unchanged, `service_definition_version_id` unchanged, no partial snapshots | PASS |
| `VERSION_BINDING_FAILURE_ROLLS_BACK_APPLICATION=YES` | `test_version_binding_failure_rolls_back_application` — injects an anonymous class implementing `ApplicationVersionBinderContract` that throws; asserts full rollback | PASS |
| `CALCULATION_SNAPSHOT_IMMUTABLE=YES` | `test_committed_snapshots_are_immutable` — after commit, attempts to mutate `outputs` of a SUBMIT snapshot; asserts `RuntimeException: Immutable after insert` (SG-04 observer) | PASS |
| `RULE_PROVENANCE_RECORDED=YES` | `test_snapshots_reference_the_three_srv001_rule_versions` — reads back the committed snapshots; asserts each references its correct `RuleDefinition::rule_identifier` (SRV001_EXPLORATION_MATRIX / SRV001_WELLS_COUNT / SRV001_NET_DEPTH) | PASS |
| `LEGACY_NUMERIC_OUTPUTS_UNCHANGED=YES` | `test_use_case_does_not_alter_legacy_calculator_outputs` — runs the legacy engine directly + runs the use case with a decision built by the target policy (delegating via bridge); asserts snapshot outputs match legacy outputs verbatim | PASS |
| `TARGET_RUNTIME_ACTIVATED=NO` | `test_srv001guard_remains_the_only_wired_srv001_runtime` — source-level grep of 4 controller files asserts none contain `SubmitApplicationUseCase` | PASS |
| `TARGET_RUNTIME_ACTIVATED=NO` (registry check) | `test_srv001guard_still_registered_in_the_submission_guard_registry` — asserts `ServiceSubmissionGuardRegistry::registeredCodes()` contains `'SRV-001'` | PASS |
| `PUBLICATION_STATUS=BLOCKED` | `test_service_publication_still_refuses_srv001_after_submission` — runs a submission via the use case, then asks `ServicePublicationPolicy::evaluate` for the same service; asserts blocked with a reason code intersecting `{PUB_BLOCKED_MISSING_UAT, PUB_BLOCKED_MISSING_UAT_REFERENCE, PUB_BLOCKED_MISSING_REASON}` | PASS |
| `RES_SG06_01_STATUS=OPEN` | Registry still binds `Srv001Guard`; no controller wires `SubmitApplicationUseCase`; documented in residual register + this report | (assertion) PASS |

Additional coverage:

* `test_rejected_decision_returns_early_without_touching_db` — rejected decision does not create any snapshot or update `application.data`
* `test_legacy_unversioned_classification_when_no_published_version` — LEGACY_UNVERSIONED path commits snapshots even when no version exists
* `test_second_committed_call_for_same_application_violates_unique_and_rolls_back` — repeated call for same app is prevented by SG-04 unique constraint; second call rolls back cleanly

## Gates

| Gate | Result |
|---|---|
| TD-02 focused tests | PASS (12 / 12 / 59 assertions / 480 ms) |
| Full combined suite (Unit + Feature + Architecture) | PASS (1031 / 1023 / 8 skipped / 3369 assertions / 34.6s) |
| Skipped-test delta vs TD-01B | +0 (still 8 pre-existing environmental/architectural skips — see TD-01A verification report §5) |
| PHPStan (full, `--memory-limit=1G`) | PASS (0 errors) |

Count reconciliation vs TD-01B:

| Suite | TD-01B end | TD-02 end | Delta |
|---|---|---|---|
| Unit | 309 | 309 | 0 |
| Feature | 692 | 704 | **+12** (TD-02 test file) |
| Architecture | 18 | 18 | 0 |
| **Combined** | **1019** | **1031** | **+12** |
| Skipped (total) | 8 | 8 | 0 |

## Preservation invariants (verified)

| Invariant | Status |
|---|---|
| SRV-001 numeric outputs unchanged | ✓ (parity tests + legacy-output test) |
| Workflow behaviour unchanged | ✓ (WorkflowEngine untouched) |
| Fee behaviour unchanged | ✓ (no fee code touched) |
| Publication status BLOCKED | ✓ (ServicePublicationPolicy assertion) |
| Runtime activation of Target domain | ✓ NO — grep of 4 controllers + registry check |
| ServiceDefinition::lockForUpdate concurrency (RC-03) | ✓ unchanged — use case does NOT call ServiceVersionPublisher during submission |
| RuleVersion classifications | ✓ frozen — Srv001RulesSeeder unchanged |
| Generic-engine SRV-001 branching | ✓ zero — use case is service-code-agnostic |
| User-owned untracked files | ✓ preserved |

## Residuals

| ID | Owner | Status | Notes |
|---|---|---|---|
| **RES-SG06-01** | post-target-canonical | **OPEN — per user directive: "Do not close unless the actual runtime direct-write path is replaced"** | The use case is built and tested but not wired. `Srv001Guard::validate` remains the runtime path with its `$app->save()` call intact. |
| **RES-TD02-01** | TD-03+ | OPEN | Runtime consumer of SubmitApplicationUseCase — either a new submission controller or a WorkflowEngine::submit refactor — will close RES-SG06-01 when it lands. |
| **RES-TD02-02** | TD-03+ (or provider-refactor phase) | OPEN | Container binding `ApplicationVersionBinderContract → ApplicationVersionBinder` in `JeaServicesServiceProvider` (deferred — tests currently DI directly, no runtime consumer yet). |

## Final assertions

```
SUBMISSION_TRANSACTION_ATOMIC=YES
SNAPSHOT_FAILURE_ROLLS_BACK_APPLICATION=YES
VERSION_BINDING_FAILURE_ROLLS_BACK_APPLICATION=YES
CALCULATION_SNAPSHOT_IMMUTABLE=YES
RULE_PROVENANCE_RECORDED=YES
LEGACY_NUMERIC_OUTPUTS_UNCHANGED=YES
TARGET_RUNTIME_ACTIVATED=NO
PUBLICATION_STATUS=BLOCKED
RES_SG06_01_STATUS=OPEN (per user directive — runtime direct-write path unchanged)

START_HEAD=0d456ea (TD-01B)
END_HEAD=<recorded post-commit>
FOCUSED_TESTS=12/12/59
COMBINED_SUITE=1031/1023/8 skipped/3369 assertions
PHPSTAN=PASS 0 errors
PUSH_STATUS=NOT_PERFORMED
```
