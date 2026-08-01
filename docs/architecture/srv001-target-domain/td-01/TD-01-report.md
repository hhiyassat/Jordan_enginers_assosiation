# TD-01 · Target-Domain Skeleton Classes

**Program:** `ESP_V2_SRV001_TARGET_DOMAIN_IMPLEMENTATION`
**Phase:** TD-01
**Baseline HEAD (start):** `cd73651` (post TD-00R reconciliation)

Introduces the target-domain skeleton per JDG-TD00-02 authorised scope item #1. Parallel classes to `Legacy*`, delegating internally to preserve numeric outputs. **Not wired to runtime** — consumers are unit tests only.

## What ships

### Value objects (`Modules\JeaServices\Domain\Srv001\ValueObjects\`)

| Class | Purpose |
|---|---|
| `Srv001SubmissionInputs` | Immutable typed carrier for the pilot form fields; `fromApplicationData()` factory parses the raw `applications.data` array |
| `Srv001DerivedValues` | Typed output — same key set the legacy `Srv001Guard` writes today + a `target_domain_provisional` marker |
| `Srv001CalculationEvidence` | Bundle of the three `ServiceCalculationResult` payloads from Target* calculators |
| `Srv001ValidationErrors` | Field-id keyed errors — same shape `ServiceSubmissionDecision::rejected` expects |

### Ports (`Modules\JeaServices\Domain\Srv001\Contracts\`)

| Interface / DTO | Purpose |
|---|---|
| `EngineerRegistryPort` + `EngineerSpecialty` | JEA membership lookups — head-of-specialization verification, engineer-number auto-fill, FR-SS-082 specialist-loss detection |
| `DlsLookupPort` + `DlsParcelRecord` | DLS Key lookup + FR-SS-087 QR-code parcel extraction |

**Adapter status**: interfaces only in TD-01. No production adapter. Real JEA API + DLS adapters remain BLOCKED_UNTIL_OD-30.

### Calculators (`Modules\JeaServices\Domain\Srv001\Calculators\`)

| Class | Delegates to | Status |
|---|---|---|
| `TargetExplorationRequirementMatrixCalculator` | `LegacyExplorationRequirementMatrixCalculator` | TARGET_DOMAIN_PROVISIONAL |
| `TargetWellsCountCalculator` | `LegacyWellsCountCalculator` | TARGET_DOMAIN_PROVISIONAL |
| `TargetNetDepthTableCalculator` | `LegacyNetDepthTableCalculator` | TARGET_DOMAIN_PROVISIONAL |

Each Target* calculator:

1. Delegates to its Legacy* counterpart (identical numeric output).
2. Adds `target_domain_classification: TARGET_DOMAIN_PROVISIONAL` to `intermediate_values`.
3. Adds explicit OD blockers to `open_decisions` so downstream snapshot consumers see the provisional status + which ODs must close before promotion (OD-07 / OD-20 / OD-22 / BR-CALC-01 / SRS §4.3 selection rule).

### Submission policy (`Modules\JeaServices\Domain\Srv001\TargetSrv001SubmissionPolicy`)

Implements `ServiceSubmissionPolicy` (SG-05 contract). Consumes `Srv001SubmissionInputs`, orchestrates the three Target* calculators, returns `ServiceSubmissionDecision`. Contract compliance:

* Accepts typed input (`Application` → parses to `Srv001SubmissionInputs`)
* Returns typed `ServiceSubmissionDecision`
* Does **NOT** call `$app->save`
* Does **NOT** mutate the passed `Application` entity
* Does **NOT** dispatch jobs / emit events / call transports
* Does **NOT** open a DB transaction

**Status classification**: `TARGET_DOMAIN_PROVISIONAL`. Numeric outputs identical to `LegacySrv001SubmissionPolicy` for every input tested.

## What does NOT ship

* No runtime wiring. `Srv001Guard` (runtime) unchanged. `ServiceSubmissionGuardRegistry` unchanged.
* No changes to `WorkflowEngine`, `ApplicationController`, or any other controller.
* No fee changes, workflow changes, or numeric changes.
* No production adapter for the two ports.
* No `Srv001Guard` refactor (that is RES-SG06-01, still a follow-up after target-canonical publication).

## Legacy behaviour preservation invariants

| Invariant | Verified by |
|---|---|
| `WellsCountCalculator` output unchanged | `TargetCalculatorsParityTest::test_wells_count_parity` (6 samples) |
| `NetDepthTable` output unchanged | `TargetCalculatorsParityTest::test_net_depth_parity` (3 samples covering floors 3/5/9) |
| `ExplorationRequirementMatrix` output unchanged | `TargetCalculatorsParityTest::test_exploration_matrix_parity` (6 samples covering directive §8 examples) |
| Government routing behaviour matches legacy | `TargetSrv001SubmissionPolicyTest::test_government_project_sector_rejected_matches_legacy` |
| Happy-path derived-values match legacy | `TargetSrv001SubmissionPolicyTest::test_happy_path_derived_values_match_legacy` (element-by-element parity check) |
| Below-minimum rejection matches legacy | `TargetSrv001SubmissionPolicyTest::test_below_minimum_rejection_matches_legacy` |
| SPECIAL_STUDY_REQUIRED matches legacy | `TargetSrv001SubmissionPolicyTest::test_special_study_required_matches_legacy` |
| Target does NOT mutate Application | `TargetSrv001SubmissionPolicyTest::test_target_does_not_mutate_application_persistent_state` |
| Target uses same rule_version_ids as legacy | `TargetSrv001SubmissionPolicyTest::test_target_and_legacy_produce_same_snapshot_ruleversion_set` |

## Files added

| File | Lines |
|---|---|
| `backend/modules/JeaServices/Domain/Srv001/ValueObjects/Srv001SubmissionInputs.php` | ~90 |
| `backend/modules/JeaServices/Domain/Srv001/ValueObjects/Srv001CalculationEvidence.php` | ~50 |
| `backend/modules/JeaServices/Domain/Srv001/ValueObjects/Srv001DerivedValues.php` | ~55 |
| `backend/modules/JeaServices/Domain/Srv001/ValueObjects/Srv001ValidationErrors.php` | ~35 |
| `backend/modules/JeaServices/Domain/Srv001/Contracts/EngineerRegistryPort.php` | ~40 |
| `backend/modules/JeaServices/Domain/Srv001/Contracts/EngineerSpecialty.php` | ~20 |
| `backend/modules/JeaServices/Domain/Srv001/Contracts/DlsLookupPort.php` | ~35 |
| `backend/modules/JeaServices/Domain/Srv001/Contracts/DlsParcelRecord.php` | ~25 |
| `backend/modules/JeaServices/Domain/Srv001/Calculators/TargetExplorationRequirementMatrixCalculator.php` | ~65 |
| `backend/modules/JeaServices/Domain/Srv001/Calculators/TargetWellsCountCalculator.php` | ~70 |
| `backend/modules/JeaServices/Domain/Srv001/Calculators/TargetNetDepthTableCalculator.php` | ~65 |
| `backend/modules/JeaServices/Domain/Srv001/TargetSrv001SubmissionPolicy.php` | ~200 |
| `backend/tests/Unit/Domain/Srv001/Srv001SubmissionInputsTest.php` | 5 tests |
| `backend/tests/Unit/Domain/Srv001/TargetCalculatorsParityTest.php` | 4 tests, 15 data-provider samples |
| `backend/tests/Unit/Domain/Srv001/TargetSrv001SubmissionPolicyTest.php` | 9 tests |
| **TOTAL** | **12 source files + 3 test files + this report** |

## Gates

| Gate | Result |
|---|---|
| TD-01 focused tests (unit/Domain/Srv001) | **PASS** (30 / 30 / 108 assertions / 527ms) |
| Regression sweep (Governance/Srv001/Workflow/ServiceCatalog/Domain) | **PASS** (206 / 206 / 1135 assertions / 5.3s) |
| PHPStan (full baseline, `--memory-limit=1G`) | **PASS** (0 errors) |

## Verdict

**TD-01 COMPLETE**. Skeleton is in place, contract compliance proven, numeric parity with legacy demonstrated across all sampled inputs. Zero runtime behaviour change. Publication remains BLOCKED for every rule per JDG-TD00-02 verdict.

## Next phase

Per JDG-TD00-02 authorised scope item #2: rule-version + snapshot writer end-to-end wiring (closes RES-SG06-01 by giving snapshot writes a runtime path, still without changing legacy numeric outputs).
