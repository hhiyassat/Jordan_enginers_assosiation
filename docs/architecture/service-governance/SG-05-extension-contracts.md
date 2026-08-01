# SG-05 · Minimal Extension Contracts

**Program:** `ESP_V2_SERVICE_GOVERNANCE_VERSIONING_FOUNDATION`
**Phase:** SG-05
**Baseline HEAD:** `af8276e...` (post SG-04)

Formalises the two extension contracts that have proven current or immediate-next-phase consumers. Applies `التوقف` to the four candidates whose consumers are speculative.

## Judgment

Per `JDG-SG05-01`:

| Candidate | Decision | Reason |
|---|---|---|
| `ServiceSubmissionPolicy` | **IMPLEMENT** | Existing consumer (`Srv001Guard`); SG-06 refactor requires typed decision |
| `ServiceCalculationPolicy` | **IMPLEMENT** | Three SRV-001 calculators; SG-04 rule-registry pairs with this contract |
| `ServiceEligibilityPolicy` | `التوقف` | Government routing (only eligibility rule) fits inside submission policy cleanly |
| `ServiceStageAction` | `التوقف` | No service-specific action exists |
| `ServiceFeeStrategy` | `التوقف` | FeeCalculator dispatch on `schema.fee.type` is not per-service |
| `ServiceIntegrationContributor` | `التوقف` | Integrations (Nashmi, GSB) are per-integration, not per-service |

Each deferred candidate has an explicit trigger condition in `RES-SG05-01`: extract when a second consumer appears.

## What ships in SG-05

### `ServiceSubmissionPolicy` interface + `ServiceSubmissionDecision` value object

Contract:

```php
interface ServiceSubmissionPolicy {
    public function serviceCode(): string;
    public function evaluate(Application $application): ServiceSubmissionDecision;
}
```

The `evaluate` method:

* MUST NOT call `$app->save`.
* MUST NOT mutate the application's persistent state.
* MUST NOT dispatch jobs / emit events / call transports.
* MUST NOT open a DB transaction.
* MAY read Application, ServiceDefinition, ServiceDefinitionVersion.
* MAY invoke pure calculators.

The returned `ServiceSubmissionDecision` carries:

* `accepted` boolean.
* `errors` (field-id keyed) — when rejected.
* `derivedValues` — key/value to persist onto `applications.data` (caller does the write).
* `warnings` — informational.
* `calculationSnapshots` — one entry per calculator executed, ready for `CalculationSnapshotWriter::writeForSubmit`.

### `ServiceCalculationPolicy` interface + `ServiceCalculationResult` value object

Contract:

```php
interface ServiceCalculationPolicy {
    public function ruleIdentifier(): string;
    public function compute(array $inputs): ServiceCalculationResult;
}
```

A calculator is a pure function; the result carries `ruleVersionId`, `inputs`, `outputs`, optional `intermediateValues`, `warnings`, `openDecisions`. The result has a `toSnapshotPayload()` helper that produces the array shape expected by `ServiceSubmissionDecision::accepted`'s `$calculationSnapshots` parameter.

## What SG-06 will use these for

`LegacySrv001SubmissionPolicy` implements `ServiceSubmissionPolicy` and orchestrates:

* Reading the SRV-001 form data.
* Running the government-routing check (returns `rejected` decision with routing hint).
* Invoking `ExplorationRequirementMatrixCalculator`, `WellsCountCalculator`, `NetDepthTableCalculator` (each implementing `ServiceCalculationPolicy` — thin wrappers over the existing static-method classes).
* Returning a decision with derived values + snapshot payloads.

The calling use case (WorkflowEngine::submit, or a new dedicated submission use case) persists the derived values, calls `CalculationSnapshotWriter::writeForSubmit` for each snapshot payload, and transitions the workflow.

## Files added

* `backend/modules/JeaServices/Governance/ServiceSubmissionPolicy.php`
* `backend/modules/JeaServices/Governance/ServiceSubmissionDecision.php`
* `backend/modules/JeaServices/Governance/ServiceCalculationPolicy.php`
* `backend/modules/JeaServices/Governance/ServiceCalculationResult.php`
* `backend/tests/Unit/Governance/ContractShapeTest.php`
* `docs/architecture/service-governance/judgments/JDG-SG05-01-contract-scope.md`

## Gates

| Gate | Result |
|---|---|
| Focused contract-shape tests | PASS (5 / 5 / 18 assertions) |
| PHPStan on Governance + tests/Unit/Governance | PASS (0 errors) |

## Residuals

| RESIDUAL_ID | Owner | Status | Notes |
|---|---|---|---|
| RES-SG05-01 | as-needed | OPEN | Extract `ServiceEligibilityPolicy` / `ServiceStageAction` / `ServiceFeeStrategy` / `ServiceIntegrationContributor` when a second consumer appears |

## Verdict

**PASS** — Two contracts + two value objects formalised; four candidates deferred with explicit trigger. Zero behaviour change. SG-06 has a defined target contract to refactor `Srv001Guard` against.
