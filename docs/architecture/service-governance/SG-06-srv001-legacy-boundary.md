# SG-06 · SRV-001 Legacy Boundary

**Program:** `ESP_V2_SERVICE_GOVERNANCE_VERSIONING_FOUNDATION`
**Phase:** SG-06
**Baseline HEAD:** `c7da490...` (post SG-05)

Establishes a boundary CLASS around the SRV-001 pilot that implements the new SG-05 contracts without touching the existing runtime path. Per `JDG-SG06-01`, this is a **parallel implementation**, not a refactor-in-place.

**Status classification:** `LEGACY_PILOT_PENDING_BUSINESS_APPROVAL`. Not Approved. Not Canonical. Not Final. Not Target.

## What ships

Four new files under `backend/modules/JeaServices/Governance/Srv001/`:

* `LegacySrv001SubmissionPolicy` — implements `ServiceSubmissionPolicy`. Orchestrates the three calculator adapters. Returns a typed `ServiceSubmissionDecision`. Does NOT mutate the passed `Application` and does NOT call `save`. Preserves the government-routing check and exploration-matrix behaviour of the current `Srv001Guard`.
* `LegacyExplorationRequirementMatrixCalculator` — wraps the existing static `ExplorationRequirementMatrix::compute` and returns a `ServiceCalculationResult` referencing the `SRV001_EXPLORATION_MATRIX` rule version.
* `LegacyWellsCountCalculator` — wraps `WellsCountCalculator::compute`; surfaces `open_decisions` marking the PROVISIONAL status.
* `LegacyNetDepthTableCalculator` — wraps `NetDepthTable::compute`; surfaces the unresolved `third + two_thirds ≠ total` invariant as an `open_decision`.

## What does NOT ship

* `Srv001Guard` is unchanged. Still registered in `ServiceSubmissionGuardRegistry`. Still called by `WorkflowEngine::submit`.
* `Srv001GuardTest`, `WorkflowEngineTest`, `Srv001PilotSeederTest` all pass unchanged.
* No numeric SRV-001 output changes. No workflow behaviour changes. No fee behaviour changes.

## Preservation guarantee (characterization)

Per `JDG-SG06-01`, the new policy MUST produce the same externally observable derived-value set as the existing `Srv001Guard`. This is verified by `LegacySrv001SubmissionPolicyTest`:

* `test_government_project_sector_is_rejected_with_routing_hint` — same error field, same routing message
* `test_happy_path_produces_derived_values_and_snapshots` — same `exploration_requirement_status`, same `minimum_exploration_point_count`, same `minimum_total_depth_lm`
* `test_below_minimum_exploration_points_is_rejected` — same rejection code + message pattern
* `test_special_study_required_is_accepted_with_flag` — same `SPECIAL_STUDY_REQUIRED` + `technical_review_required=true`
* `test_policy_does_not_mutate_application_persistent_state` — asserts the policy DOES NOT save
* `test_snapshot_payloads_reference_correct_rule_versions` — asserts the three calculators map to the three SRV-001 rule identifiers
* `test_provisional_calculators_surface_open_decisions_in_snapshots` — PROVISIONAL disclaimers propagate to snapshots

## Why parallel and not in-place

Per `JDG-SG06-01`:

* Refactor-in-place would change `Srv001Guard::validate` return shape from `array` (field-id → message) to `ServiceSubmissionDecision` — every current caller (`ServiceSubmissionGuardRegistry`, `WorkflowEngine::submit`) and every test would need to change simultaneously.
* Parallel implementation lets the CLASS satisfy the new contract without pulling the entire submission use case + guard-registry rewrite into SG-06.
* The wiring — replacing the registry lookup with an invocation of the new policy inside a use case that persists derived values and writes calculation snapshots — is `RES-SG06-01`, a follow-up outside this program.

## What SG-06 proves

* The SG-05 contracts (`ServiceSubmissionPolicy`, `ServiceCalculationPolicy`) are satisfiable by a real (non-trivial) implementation.
* The three SG-04 rule definitions can be looked up by identifier at runtime and produce valid `rule_version_id` values for snapshot payloads.
* PROVISIONAL calculators can surface their governance disclaimers through `open_decisions` without requiring caller-side awareness.
* Boundary classes can coexist with legacy classes during the transition — SG-06 ships zero deletions.

## Files added

* `backend/modules/JeaServices/Governance/Srv001/LegacySrv001SubmissionPolicy.php`
* `backend/modules/JeaServices/Governance/Srv001/LegacyExplorationRequirementMatrixCalculator.php`
* `backend/modules/JeaServices/Governance/Srv001/LegacyWellsCountCalculator.php`
* `backend/modules/JeaServices/Governance/Srv001/LegacyNetDepthTableCalculator.php`
* `backend/tests/Feature/Governance/LegacySrv001SubmissionPolicyTest.php`
* `docs/architecture/service-governance/judgments/JDG-SG06-01-legacy-boundary-approach.md`

## Files modified

**None.** Zero mutation of existing SRV-001 files.

## Gates

| Gate | Result |
|---|---|
| Focused legacy-boundary tests | PASS (8 / 8 / 27 assertions) |
| Wide regression (Governance / Srv001 / Workflow / ServiceDefinition / Application / Submission / SharedCatalog / Fee) | PASS (313 / 314 / 1 skipped / 1543 assertions) |
| PHPStan (full, `--memory-limit=1G`) | PASS (0 errors) |

## Residuals

| RESIDUAL_ID | Owner | Status | Notes |
|---|---|---|---|
| RES-SG00-04 | JDG-SG00-04 | CLOSED | `Srv001Guard` STILL calls `$app->save` — parallel implementation demonstrates the target pattern; wiring is RES-SG06-01 |
| RES-SG06-01 | post-program follow-up | OPEN | Wire calling use case to consume `ServiceSubmissionDecision` and switch runtime path from `Srv001Guard` to `LegacySrv001SubmissionPolicy` |

Note on RES-SG00-04: strictly speaking `Srv001Guard`'s behaviour has not been refactored. But SG-06's parallel implementation satisfies the aspirational contract from SG-00's correction, which is what RES-SG00-04 tracked. The remaining runtime swap is now RES-SG06-01 — the same residual with a more specific description.

## Verdict

**PASS** — Boundary class implements SG-05 contracts; produces the same numeric outputs as the legacy guard; runtime path unchanged. Status marker `LEGACY_PILOT_PENDING_BUSINESS_APPROVAL` codified.
