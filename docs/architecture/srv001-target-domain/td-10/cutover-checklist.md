# TD-10 · Cutover Checklist

Machine-checkable version implemented at `modules/JeaServices/Domain/Cutover/CutoverChecklist.php`.

## Current evaluation

**`CUTOVER_READINESS_VERDICT = NOT_CUTOVER_READY`**

Every checklist item is currently `false` (test-verified by `test_current_cutover_state_is_NOT_READY`).

## Checklist items (25)

| # | Item | State | Blocker |
|---|---|---|---|
| 1 | `signed_srs_baseline_2_0` | FALSE | SRS v1.2 DRAFT_REVIEW |
| 2 | `signed_od_closures` | FALSE | 22 open ODs |
| 3 | `approved_detailed_rtm` | FALSE | RTM disposition unratified (RES-TD09-01) |
| 4 | `approved_workflow_version` | FALSE | OD-18, OD-29, OD-31, OD-32, OD-33, OD-34 unresolved |
| 5 | `approved_calculation_rule_versions` | FALSE | Wells + NetDepth PROVISIONAL (OD-11, OD-19, OD-20) |
| 6 | `approved_financial_rule_versions` | FALSE | OD-01, OD-10, OD-15, OD-17, OD-19, OD-35 unresolved |
| 7 | `approved_attachment_limits` | FALSE | OD-24 unresolved |
| 8 | `oracle_contract` | FALSE | OD-30 |
| 9 | `dls_contract` | FALSE | OD-30 |
| 10 | `bura_contracts` | FALSE | OD-30 |
| 11 | `storage_adapter` | FALSE | No production storage adapter |
| 12 | `malware_scanner` | FALSE | No production AV adapter |
| 13 | `payment_gateway` | FALSE | No signed gateway contract |
| 14 | `receipt_authority` | FALSE | No signed receipt authority |
| 15 | `certificate_authority` | FALSE | No signed certificate signing authority |
| 16 | `sandbox_evidence` | FALSE | No sandbox tested |
| 17 | `security_review` | FALSE | Not yet performed |
| 18 | `performance_evidence` | FALSE | Not yet measured |
| 19 | `uat_approval` | FALSE | UAT_READINESS_VERDICT=NOT_UAT_READY (TD-09) |
| 20 | `migration_dry_run_approval` | FALSE | Dry-run tooling exists; production data inventory pending |
| 21 | `rollback_rehearsal` | FALSE | Rollback plan documented; rehearsal not performed |
| 22 | `monitoring_readiness` | FALSE | Not yet configured |
| 23 | `support_readiness` | FALSE | Runbook pending |
| 24 | `publication_approval` | FALSE | Blocked by items 1..15 |
| 25 | `production_change_approval` | FALSE | Blocked by items 1..24 |

## Verdict rule

- `CUTOVER_READY` — all 25 items true, no exceptions.
- `CUTOVER_READY_WITH_SIGNED_EXCEPTIONS` — all missing items covered by an explicit `signedExceptions` array.
- `NOT_CUTOVER_READY` — any missing item without a signed exception.

## Enforcement

`CutoverChecklist::evaluate($state, $signedExceptions)` returns the verdict. Called from `tests/Feature/Cutover/Srv001CutoverAndMigrationTest.php`.

**No production cutover is performed by this program.**
