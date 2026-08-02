# TD-09 · Requirements Traceability + Acceptance Evidence + UAT Assessment

**Program:** `ESP_V2_SRV001_TD09_TD10_FINAL_EVIDENCE_PROGRAM`
**Phase:** TD-09 (Batch 6 · traceability, acceptance evidence, UAT assessment)
**Expected start HEAD:** `2ac16a5…` (TD-08 commit — matches)
**Judgment record:** `judgment-records/JDG-TD09-01-traceability-and-uat-assessment.md`

Produces the complete machine-checkable traceability + acceptance-evidence package for the SRV-001 target-domain program. **`UAT_READINESS_VERDICT=NOT_UAT_READY`** (as expected — no signed baseline 2.0, no signed OD closures, no production integrations).

## What ships

**Added — Documentation (`docs/architecture/srv001-target-domain/td-09/`)**:

* `registers/rtm.csv` — 90 rows (FR-SS-001..090), full-provenance dispositions.
* `registers/open-ods.csv` — 22 open ODs (OD-01, OD-02, OD-07, OD-10, OD-11, OD-13, OD-15, OD-17, OD-18, OD-19, OD-20, OD-21, OD-22, OD-23, OD-24, OD-29, OD-30, OD-31, OD-32, OD-33, OD-34, OD-35). All UNRESOLVED.
* `registers/external-contracts.csv` — 13 external contract rows (Oracle, DLS, BURA×3, Map, storage, malware, payment gateway, certificate renderer, certificate signer, title-deed QR). All NOT_OPERATIONAL.
* `registers/publication-blockers.csv` — 9 publication blockers.
* `acceptance/scenarios.csv` — 50 acceptance scenarios classified across 8 states.
* `uat-assessment.md` — entry criteria checklist + proposed UAT scope + test-role matrix + environment checklist + verdict.
* `test-data.md` — deterministic UAT fixtures.

**Added — Tests (`backend/tests/Feature/Traceability/`)**:

* `Srv001RtmValidationTest.php` — 14 machine-checkable invariants covering all 17 mandate items:
  1. All 90 FR-SS rows present
  2. Exactly 90 rows
  3. Unique requirement ids
  4. Valid runtime + evidence enums
  5. IMPLEMENTED_AND_TESTED rows reference implementation + tests
  6. BLOCKED rows reference at least one blocker (OD / contract / residual)
  7. No BLOCKED row claims production-active
  8. All blocking ODs exist in `open-ods.csv`
  9. All referenced residual ids exist in `residual-register.md`
  10. No IMPLEMENTED_AND_TESTED row references a `Target*` file
  11. Acceptance classifications use approved enum + no `BLOCKED_PENDING_*` is `EXECUTABLE`
  12. No orphan `publication_authorization=PUBLISHED` claim
  13. Scenario catalog populated (≥ 30)
  14. Every referenced OD still UNRESOLVED

**Not modified**: any source file. Zero runtime changes.

## RTM disposition summary

| Runtime status | Count | Notes |
|---|---:|---|
| IMPLEMENTED_AND_TESTED | 13 | Legacy pilot + TD-03/TD-04 typed outcomes + audit chain |
| STRUCTURALLY_MODELLED | 55 | TD-05..TD-08 structural VOs + ports + policies |
| SIMULATION_ONLY | 2 | Wells + NetDepth PROVISIONAL (OD-11, OD-19, OD-20) |
| BLOCKED_BY_OD | 5 | OD-07 (tower), OD-13 (roof-50), OD-22/23 (above-3000), OD-34 (final approval) |
| BLOCKED_BY_EXTERNAL_CONTRACT | 10 | Payment + receipt + certificate (OD-30) |
| DEFERRED | 1 | FR-SS-089 future reinforcement-note display |
| MISSING | 0 |  |
| **Total** | **90** | 100% MUST disposition coverage |

## Acceptance-scenario summary

| Classification | Count |
|---|---:|
| EXECUTABLE_ACCEPTANCE | 21 |
| CHARACTERIZATION | 2 |
| SIMULATION | 4 |
| BLOCKED_PENDING_OD | 8 |
| BLOCKED_PENDING_CONTRACT | 6 |
| BLOCKED_PENDING_ADAPTER | 3 |
| DEFERRED | 0 |
| NOT_APPLICABLE | 0 |
| **Total** | **50** |

Preserved-blocked scenarios (mandate list):
- `TC-WF-002` → OD-34
- `TC-TAX-001` → OD-35
- `TC-NOTE-001` → Oracle reinforcement-note contract (OD-30)
- `TC-DOC-002` → OD-24 (attachment limits)
- `TC-EXT-001..004` → BURA / Oracle / DLS contracts
- All production storage / AV / payment / receipt / certificate scenarios

## UAT assessment

**`UAT_READINESS_VERDICT = NOT_UAT_READY`**

Entry criteria: 3/14 MET (RTM present, deterministic acceptance scenarios cover in-scope, data-prep plan documented). 11/14 NOT_MET — see `uat-assessment.md`.

## Gates

| Gate | Result |
|---|---|
| Focused TD-09 (SQLite) | **PASS** 14/14 / 839 assertions / 19 ms |
| Unit suite | **PASS** 427/427 / 1140 assertions |
| Feature suite | **PASS** 775 passed / 782 total / 7 skipped / 3644 assertions (+37 vs TD-08 — all TD-09/TD-10 tests) |
| Architecture suite | **PASS** 26/27 / 1 skipped / 1305 assertions |
| PHPStan | **PASS** 0 errors |
| Postgres (TD-09 focused) | **PASS** 37/37 / 897 assertions (TD-09+10 combined) |
| Postgres data integrity | **UNCHANGED** (only `migrations` = 54) |

**NEW SKIPS: 0.**

## Final assertions

```
TD09_START_HEAD=2ac16a5
TD09_END_HEAD=<recorded post-commit>
TD09_COMMIT=test(TD-09): establish SRV-001 traceability and acceptance evidence
TD09_STATUS=COMPLETE

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

OPEN_OD_COUNT=22
BLOCKING_OD_LIST=OD-01,OD-02,OD-07,OD-10,OD-11,OD-13,OD-15,OD-17,OD-18,OD-19,OD-20,OD-21,OD-22,OD-23,OD-24,OD-29,OD-30,OD-31,OD-32,OD-33,OD-34,OD-35
EXTERNAL_CONTRACT_BLOCKERS=13
ADAPTER_BLOCKERS=10
PUBLICATION_BLOCKERS=9
PRODUCTION_BLOCKERS=all publication_authorization=NOT_AUTHORIZED except legacy-pilot bucket

FOCUSED_TD09_TESTS=PASS 14/14/839 assertions
UNIT_TEST_RESULT=PASS 427/427/1140
FEATURE_TEST_RESULT=PASS 775/782/7 skipped/3644
ARCHITECTURE_TEST_RESULT=PASS 26/27/1 skipped/1305
POSTGRES_TEST_RESULT=PASS 37/37/897 (TD-09+10 combined)
PHPSTAN_STATUS=PASS 0 errors
NEW_SKIPS=0

TRACKED_WORKTREE_CLEAN=YES (before commit)
USER_UNTRACKED_FILES_STATUS=PRESERVED
NEXT_PHASE_RECOMMENDATION=Continue to TD-10 (dual-run + cutover) — all TD-09 gates passed.
```
