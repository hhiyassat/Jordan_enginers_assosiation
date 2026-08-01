JUDGMENT_ID=JDG-SG05-01
TITLE=Extension contract scope — which contracts are justified today
SCOPE=architecture/service-governance/SG-05
OWNER=service-governance-remediation

الوضع=
Program §Phase SG-05 names six candidate contracts (ServiceSubmissionPolicy, ServiceCalculationPolicy, ServiceEligibilityPolicy, ServiceStageAction, ServiceFeeStrategy, ServiceIntegrationContributor). Repository at HEAD contains: (a) `Modules\JeaServices\Engine\ServiceSubmissionGuard` interface — already used by `Srv001Guard`; (b) three SRV-001 calculators as free-standing classes with static methods; (c) `Modules\JeaServices\Engine\StageActions` dispatching on action names inside a single file; (d) `Modules\JeaServices\Engine\FeeCalculator::calculateBreakdown` dispatching on `schema.fee.type` in a single file; (e) Nashmi and GSB integrations as separate modules with their own middleware. There is exactly ONE service-specific extension consumer today: SRV-001.

تحرير_محل_النزاع=
For each of the six candidate contracts, is a formal interface justified by an existing or immediately-required consumer, or is it speculative framework that should be deferred until proven?

السبب=
Program §Phase SG-05 explicitly forbids speculative frameworks and requires per-candidate judgment.

الشرط=
A contract is justified when (i) at least one real consumer exists in the codebase today or is required by SG-06, (ii) the shape of the contract can be determined by observing the current consumer's behaviour, (iii) formalising the contract enables a discipline rule (e.g. return typed decision + no `$app->save`), (iv) not formalising the contract leaves an observable governance gap.

المانع=
Introducing a contract with a single speculative consumer would be a violation of Program §Phase SG-05 ("Do not implement every candidate automatically") and §14 ("a proposed generic abstraction has only one speculative consumer" is a `التوقف` condition).

العلة=
Discipline + program deliverability. Each formal contract adds a maintenance surface (tests, PHPStan property annotations, PHPDoc for consumers). A contract with no consumer is dead weight.

القادح=
None per-candidate; evidence is enumerated below.

الصحة (per candidate)=
1. `ServiceSubmissionPolicy` — VALID to formalise NOW. Consumer: `Srv001Guard` today; SG-06 refactor requires a typed-decision contract; SG-00 §D corrected the Service Package Contract to require typed decisions instead of `$app->save`.
2. `ServiceCalculationPolicy` — VALID to formalise NOW. Consumers: three SRV-001 calculators. SG-04's rule-version registry is the storage layer; a formal contract makes the "calculators return a typed CalculationResult with inputs+outputs+intermediate+warnings+rule_version" pattern explicit and testable.
3. `ServiceEligibilityPolicy` — NOT PROVEN. The only service-eligibility rule today (SRV-001 government routing) is inside `Srv001Guard::validate`. That fits inside `ServiceSubmissionPolicy` cleanly. No independent eligibility consumer exists — التوقف.
4. `ServiceStageAction` — NOT PROVEN. `StageActions::run($action, $app)` has a fixed set of action names and no service-specific action exists. If a service ever needs a bespoke action, extract then — التوقف.
5. `ServiceFeeStrategy` — NOT PROVEN. `FeeCalculator::calculateBreakdown` dispatches on `schema.fee.type` inside one file. No per-service fee strategy exists (SRV-001 uses the generic `per_unit` strategy). If a service ever needs a bespoke formula, extract then — التوقف.
6. `ServiceIntegrationContributor` — NOT PROVEN. Nashmi and GSB are per-integration, not per-service. Adding this contract now is speculative — التوقف.

الفساد=
Implementing all six would be fasid — introduces four contracts with no consumers.

البطلان=
Implementing none would be batil — abandons the mandate's discipline rule (typed decisions).

الأثر=
(1) Formalise `ServiceSubmissionPolicy` interface + `ServiceSubmissionDecision` value object. (2) Formalise `ServiceCalculationPolicy` interface + `ServiceCalculationResult` value object. (3) `Srv001Guard` continues to implement `ServiceSubmissionGuard` (legacy shape) — SG-06 adds `LegacySrv001SubmissionPolicy` that implements the new contract. (4) Four candidates deferred.

البقايا=
RES-SG05-01: `ServiceEligibilityPolicy` / `ServiceStageAction` / `ServiceFeeStrategy` / `ServiceIntegrationContributor` extraction triggered whenever a second consumer appears. Owner: as the need arises.

التعارض=
Program §14 (`التوقف` on speculative consumers) vs Program §5.5 (extension registries). Reconciled: extension REGISTRY need not exist until an extension EXTENSION exists.

الجمع=
Reconcile: implement the two proven contracts; defer the four unproven candidates with an explicit trigger condition.

الترجيح=
Tier-4 (target architecture) demands the two proven contracts. Program mandate + Tier-6 (current implementation) demand deferral of the four unproven.

التوقف=
Applied per-candidate: 4 candidates deferred. Phase itself not stopped.

EVIDENCE=
- backend/modules/JeaServices/Engine/ServiceSubmissionGuard.php (existing interface — the legacy shape)
- backend/modules/JeaServices/Engine/ServiceSubmissionGuardRegistry.php (dispatch registry)
- backend/modules/JeaServices/Engine/Srv001Guard.php (sole current implementation)
- backend/modules/JeaServices/Engine/WellsCountCalculator.php + NetDepthTable.php + ExplorationRequirementMatrix.php (three calculators, pure static methods, no shared contract)
- backend/modules/JeaServices/Engine/StageActions.php (single file, fixed action dispatch)
- backend/modules/JeaServices/Engine/FeeCalculator.php (single file, fixed type dispatch)

DECISION=
Implement `ServiceSubmissionPolicy` + `ServiceSubmissionDecision` + `ServiceCalculationPolicy` + `ServiceCalculationResult` in `backend/modules/JeaServices/Governance/`. Defer the other four candidates to RES-SG05-01.

IMPLEMENTATION_EFFECT=
Two new interfaces + two new value objects. No behaviour change (nothing implements the new contracts yet — SG-06 wires SRV-001 to them).

MIGRATION_EFFECT=
None (code-only).

TEST_EVIDENCE=
Interface + value-object shape tests. A trivial passing implementation demonstrates the contract can be satisfied.

OPEN_RESIDUALS=
- RES-SG05-01 (four deferred contracts).
