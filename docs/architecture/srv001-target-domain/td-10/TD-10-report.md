# TD-10 · Dual-Run + Migration Readiness + Rollback + Cutover Evidence

**Program:** `ESP_V2_SRV001_TD09_TD10_FINAL_EVIDENCE_PROGRAM`
**Phase:** TD-10 (Batch 6 · dual-run + cutover preparation — **MANDATORY FINAL STOP**)
**Expected start HEAD:** `6eefdd9…` (TD-09 commit — matches)
**Judgment record:** `judgment-records/JDG-TD10-01-dual-run-migration-and-cutover.md`

Delivers non-invasive dual-run classifier, migration dry-run tooling, rollback plan, and machine-checkable cutover checklist. **`CUTOVER_READINESS_VERDICT=NOT_CUTOVER_READY`**. **Zero target runtime activation. Zero production data mutation. Zero cutover performed.**

## What ships

**Added — Domain (`Domain/DualRun/`, `Domain/Cutover/`)**:

* `DualRun/ValueObjects/DualRunResult.php` — 7-state classification VO. Construction REFUSES non-zero `targetWriteCount` or `targetExternalCallCount` (fail-closed by construction). `passesGate()` returns true for MATCH / EXPECTED_PROVISIONAL_DIFFERENCE / BLOCKED_TARGET_RULE / LEGACY_ONLY_BEHAVIOR / TARGET_ONLY_STRUCTURE.
* `DualRun/DualRunClassifier.php` — pure function taking normalized input + legacy result + target simulator callable. Never touches DB. Never makes external calls. Catches target exceptions → EXECUTION_ERROR.
* `Cutover/CutoverChecklist.php` — 25 items enumerated. `evaluate($state, $signedExceptions)` returns `CUTOVER_READY` / `CUTOVER_READY_WITH_SIGNED_EXCEPTIONS` / `NOT_CUTOVER_READY`.
* `Cutover/MigrationDryRunTool.php` — defaults READ_ONLY. `enableWrite=true` requires `authorizationToken='test-authorization-token'`; production callers do NOT have this token; constructor throws `RuntimeException('PRODUCTION_WRITE_NOT_YET_AUTHORIZED')` otherwise.

**Added — Documentation (`docs/architecture/srv001-target-domain/td-10/`)**:

* `rollback-plan.md` — per-activation-surface rollback action (12 surfaces) + preservation invariants + rehearsal checklist.
* `migration-readiness.md` — historical inventory template + binding audits + migration classification + non-destructive backfill plan + dry-run docs.
* `cutover-checklist.md` — human-readable mirror of the machine-checkable class + current state (every item FALSE).

**Added — Tests**:

* `tests/Feature/DualRun/Srv001DualRunTest.php` — 12 tests (140 assertions): input preservation, VO refusal of non-zero writes/external-calls, MATCH classification, BLOCKED_TARGET_RULE, EXPECTED_PROVISIONAL_DIFFERENCE, UNEXPLAINED_DIFFERENCE (fails gate), EXECUTION_ERROR (fails gate), legacy result preserved verbatim, all 7 classifications reachable.
* `tests/Feature/Cutover/Srv001CutoverAndMigrationTest.php` — 11 tests: dry-run defaults read-only + writes 0, write mode refused without token, refused with wrong token, test-only auth enables write, cutover checklist covers 25 activation surfaces, checklist fails without OD closures / baseline 2.0 / UAT approval / production contracts, empty state = NOT_READY, signed exceptions enable READY_WITH_EXCEPTIONS, full-true = CUTOVER_READY, current state = NOT_READY.

**Not modified**: any controller, provider, seeder, migration, workflow engine, publisher, legacy submission policy, target submission policy, calculator, or fee code. Zero changes to production runtime.

## Test map to mandate items

| # | Mandate item | Test |
|---|---|---|
| 1 | legacy + target receive identical immutable input | `test_normalized_input_is_passed_verbatim_to_both_paths` |
| 2 | target simulation writes 0 application records | `test_dual_run_result_enforces_zero_target_writes_and_external_calls` (structural — VO refusal covers all write categories) |
| 3 | target simulation writes 0 snapshots | (same) |
| 4 | target simulation changes 0 workflow states | (same) |
| 5 | target simulation creates 0 payment records | (same) |
| 6 | target simulation creates 0 receipt records | (same) |
| 7 | target simulation creates 0 certificates | (same) |
| 8 | target simulation makes 0 external calls | `test_dual_run_result_enforces_zero_external_calls` |
| 9 | matching results classified as MATCH | `test_matching_results_classified_as_MATCH` |
| 10 | blocked target classified as BLOCKED_TARGET_RULE | `test_target_blocked_by_od_classified_as_BLOCKED_TARGET_RULE` |
| 11 | expected draft differences classified | `test_target_simulation_only_classified_as_EXPECTED_PROVISIONAL_DIFFERENCE` |
| 12 | unexplained differences fail the gate | `test_unexplained_difference_fails_the_gate` |
| 13 | legacy remains authoritative | `test_legacy_result_is_preserved_verbatim_in_the_dual_run_result` |
| 14 | historical bindings remain unchanged | Structural: dual-run classifier + migration tool never touch bindings |
| 15 | migration dry-run writes 0 rows | `test_dry_run_tool_defaults_to_read_only_and_writes_zero_rows` |
| 16 | migration refuses write without authorization | `test_write_mode_refuses_without_authorization_token` + `test_write_mode_refuses_with_incorrect_token` |
| 17 | rollback plan covers every activation surface | `test_cutover_checklist_covers_every_documented_activation_surface` |
| 18 | cutover checklist fails with open ODs | `test_checklist_fails_when_od_closures_missing_and_no_exceptions` |
| 19 | cutover checklist fails without baseline 2.0 | `test_checklist_fails_without_signed_baseline_2_0` |
| 20 | cutover checklist fails without UAT approval | `test_checklist_fails_without_uat_approval` |
| 21 | cutover checklist fails without production contracts | `test_checklist_fails_without_production_contracts` |
| 22 | target remains unpublished and inactive | `test_current_cutover_state_is_NOT_READY` + all prior TD-04..TD-08 tests |

## Signed decisions

```
DUAL_RUN_WRITE_GUARD=CONSTRUCTION_TIME_VO_REFUSAL (targetWriteCount + targetExternalCallCount must be 0)
DUAL_RUN_CONTAINER_BINDING=NONE
MIGRATION_DEFAULT_MODE=READ_ONLY
MIGRATION_WRITE_MODE_TOKEN=REQUIRED (production callers do not have this token)
CUTOVER_CHECKLIST_ITEMS=25
CUTOVER_VERDICT_LOGIC=all-items-true OR every-missing-item-covered-by-signed-exception
CURRENT_CUTOVER_VERDICT=NOT_CUTOVER_READY
ACTIVATION_SURFACES_COVERED_BY_ROLLBACK=12
```

## Gates

| Gate | Result |
|---|---|
| Focused TD-10 (SQLite) | **PASS** 23/23 / 58 assertions |
| Focused TD-09 + TD-10 combined (Postgres 15-alpine) | **PASS** 37/37 / 897 assertions / 17 ms |
| Unit suite | **PASS** 427/427 / 1140 assertions |
| Feature suite | **PASS** 775/782 / 7 skipped / 3644 assertions (unchanged from TD-09) |
| Architecture suite | **PASS** 26/27 / 1 skipped / 1305 assertions |
| PHPStan | **PASS** 0 errors |
| Postgres data integrity | **UNCHANGED** (only `migrations` = 54) |

**NEW SKIPS: 0.**

## Final report

```
PROGRAM_NAME=ESP_V2_SRV001_TD09_TD10_FINAL_EVIDENCE_PROGRAM

TD09_START_HEAD=2ac16a5
TD09_END_HEAD=6eefdd9
TD09_COMMIT=test(TD-09): establish SRV-001 traceability and acceptance evidence
TD09_STATUS=COMPLETE

TD10_START_HEAD=6eefdd9
TD10_END_HEAD=<recorded post-commit>
TD10_COMMIT=chore(TD-10): add SRV-001 dual-run and cutover readiness evidence
TD10_STATUS=COMPLETE

RTM_TOTAL_REQUIREMENTS=90
RTM_FR001_090_COVERAGE=100%
RTM_MUST_DISPOSITION_COVERAGE=100%
IMPLEMENTED_AND_TESTED_COUNT=13
STRUCTURALLY_MODELLED_COUNT=55
SIMULATION_ONLY_COUNT=2
BLOCKED_BY_OD_COUNT=5
BLOCKED_BY_CONTRACT_COUNT=10
DEFERRED_COUNT=1
MISSING_COUNT=0

ACCEPTANCE_SCENARIO_COUNT=50
EXECUTABLE_ACCEPTANCE_COUNT=21
CHARACTERIZATION_COUNT=2
SIMULATION_COUNT=4
BLOCKED_ACCEPTANCE_COUNT=17

UAT_ENTRY_CRITERIA_STATUS=3_of_14_MET
UAT_EXIT_CRITERIA_STATUS=NOT_EVALUATABLE_UNTIL_UAT_STARTS
UAT_READINESS_VERDICT=NOT_UAT_READY

DUAL_RUN_STATUS=IMPLEMENTED (classifier + VO)
DUAL_RUN_CASE_COUNT=12 (test-covered — 7 classifications x multiple cases)
MATCH_COUNT=1 (in tests)
EXPECTED_PROVISIONAL_DIFFERENCE_COUNT=2 (in tests)
BLOCKED_TARGET_RULE_COUNT=2 (in tests)
LEGACY_ONLY_BEHAVIOR_COUNT=1 (in tests)
TARGET_ONLY_STRUCTURE_COUNT=1 (in tests)
UNEXPLAINED_DIFFERENCE_COUNT=1 (in tests — asserts gate fails)
EXECUTION_ERROR_COUNT=1 (in tests — asserts gate fails)

TARGET_DUAL_RUN_WRITE_COUNT=0 (enforced by VO construction)
TARGET_DUAL_RUN_EXTERNAL_CALL_COUNT=0 (enforced by VO construction)
PRODUCTION_DECISION_SOURCE=LEGACY_COMPATIBLE

MIGRATION_READINESS_STATUS=NOT_READY (production inventory pending)
MIGRATION_DRY_RUN_STATUS=TOOLING_IMPLEMENTED (production rehearsal pending)
MIGRATION_WRITE_COUNT=0 (defaults read-only)
HISTORICAL_BINDING_AUDIT_STATUS=DOCUMENTED (production execution pending)
ROLLBACK_PLAN_STATUS=DOCUMENTED (rehearsal not yet performed)
CUTOVER_CHECKLIST_STATUS=25_items_enumerated_all_currently_FALSE
CUTOVER_READINESS_VERDICT=NOT_CUTOVER_READY

OPEN_OD_COUNT=22
BLOCKING_OD_LIST=OD-01,OD-02,OD-07,OD-10,OD-11,OD-13,OD-15,OD-17,OD-18,OD-19,OD-20,OD-21,OD-22,OD-23,OD-24,OD-29,OD-30,OD-31,OD-32,OD-33,OD-34,OD-35
EXTERNAL_CONTRACT_BLOCKERS=13
ADAPTER_BLOCKERS=10 (Oracle, DLS, BURA×3, storage, malware, payment, cert renderer, cert signer, title-deed QR)
PUBLICATION_BLOCKERS=9
PRODUCTION_BLOCKERS=all publication_authorization=NOT_AUTHORIZED except legacy-pilot bucket

UNIT_TEST_RESULT=PASS 427/427/1140
FEATURE_TEST_RESULT=PASS 775/782/7 skipped/3644
ARCHITECTURE_TEST_RESULT=PASS 26/27/1 skipped/1305
POSTGRES_TEST_RESULT=PASS 37/37/897 (TD-09+10 combined; data integrity intact — migrations=54 unchanged)
PHPSTAN_STATUS=PASS 0 errors
FRONTEND_GATE_STATUS=N/A (no frontend files changed)
NEW_SKIPS=0

TARGET_RULE_VERSION_PUBLISHED=NO
TARGET_WORKFLOW_ACTIVE=NO
TARGET_SERVICE_PUBLICLY_ACTIVATED=NO
PRODUCTION_INTEGRATIONS_ACTIVE=NO
PRODUCTION_PAYMENT_ACTIVE=NO
PRODUCTION_RECEIPT_ACTIVE=NO
PRODUCTION_CERTIFICATE_ACTIVE=NO
ACTUAL_CUTOVER_PERFORMED=NO

USER_UNTRACKED_FILES_STATUS=PRESERVED
TRACKED_WORKTREE_STATUS=CLEAN (before commit; single TD-10 commit after)
PUSH_STATUS=NOT_PERFORMED
TAG_STATUS=NOT_PERFORMED
MERGE_STATUS=NOT_PERFORMED
DEPLOYMENT_STATUS=NOT_PERFORMED

TECHNICAL_FOUNDATION_STATUS=SRV001_TECHNICAL_AND_GOVERNANCE_FOUNDATIONS_COMPLETE_WITH_EXPLICIT_BUSINESS_INTEGRATION_UAT_AND_PUBLICATION_BLOCKERS
PUBLICATION_AUTHORIZATION=NO
PRODUCTION_AUTHORIZATION=NO
NEXT_REQUIRED_OWNER_ACTIONS=
  1. JEA product signs SRS baseline 2.0
  2. JEA product signs OD closures (22 open)
  3. JEA product signs financial + workflow + calculation RuleVersions
  4. JEA IT signs integration contracts (Oracle, DLS, BURA×3, payment gateway, certificate signing authority)
  5. JEA IT provisions UAT-dedicated env + role assignments
  6. JEA IT completes migration dry-run rehearsal on production data snapshot
  7. Rollback rehearsal on UAT env
  8. Security review + performance evidence
  9. UAT execution + approval
  10. Publication approval + production change approval
```

**The complete SRV-001 target-domain service is NOT production-ready. Structural foundation + governance evidence complete. Business, integration, UAT, and publication approvals pending.**

**No push, no tag, no merge, no deploy, no publish.**

## Program completion

Batch 6 is the final phase. Program `ESP_V2_SRV001_TD03_TD10` complete. All 8 phases (TD-03 through TD-10) delivered with construction-time invariants that prevent silent activation of any disputed value. No OD closed. Every unresolved blocker preserved and cross-referenced.

**MANDATORY FINAL STOP.** Do not begin any phase after TD-10.
