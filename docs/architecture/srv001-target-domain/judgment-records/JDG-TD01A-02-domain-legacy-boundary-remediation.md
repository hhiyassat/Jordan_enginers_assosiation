JUDGMENT_ID=JDG-TD01A-02
TITLE=Domain → Legacy dependency remediation via rule ports + adapters (outside domain layer)
OWNER=TD-01A
PHASE=TD-01A

الوضع=
TD-01 (commit `f6dd8f4`) introduced 3 Target* calculators inside `Modules\JeaServices\Domain\Srv001\Calculators\`. Direct grep confirms each imports its Legacy* counterpart from `Modules\JeaServices\Governance\Srv001\`:

- `TargetExplorationRequirementMatrixCalculator.php:9` — `use ...LegacyExplorationRequirementMatrixCalculator`
- `TargetNetDepthTableCalculator.php:9` — `use ...LegacyNetDepthTableCalculator`
- `TargetWellsCountCalculator.php:9` — `use ...LegacyWellsCountCalculator`

The Legacy* classes are declared `LEGACY_PILOT_PENDING_BUSINESS_APPROVAL` (SG-06). Their outputs are numerically PROVISIONAL (SG-04 rule-version classification). The Domain layer must NOT permanently depend on a PROVISIONAL legacy — that would make the legacy the de-facto target design.

Numeric parity in the TD-01 test suite is **characterisation evidence** (proves the Target* class doesn't accidentally change legacy behaviour) — **not** proof that legacy values are the approved target rules.

تحرير_محل_النزاع=
Should the Domain\Srv001 layer keep the direct Legacy import (as a temporary compromise recorded as an architecture residual) OR extract a rule-port + adapter pair moving the Legacy dependency outside the domain layer?

السبب=
User directive TD-01A "Special architectural review" mandates one of the two: "move the delegation into a compatibility adapter outside the domain layer; OR record an explicit temporary architecture residual … prevent the dependency from becoming the final target-domain design."

الشرط=
- Domain\Srv001 files MUST NOT import Governance\Srv001\Legacy* classes after remediation.
- Legacy* numeric outputs MUST remain unchanged (JDG-TD00-02 invariant).
- Test suites MUST continue to prove numeric parity with legacy so any accidental behaviour change is caught.
- Adapters MUST live outside the Domain namespace (`Modules\JeaServices\Adapters\Srv001\` chosen).
- No runtime wiring change (runtime path still uses `Srv001Guard`).
- An architecture test SHOULD enforce the boundary going forward.

المانع=
Deep refactor of TD-01 = churn. User directive: "narrowly required architecture/test corrections … but it must not introduce runtime wiring." Any refactor must stop at the boundary fix and boundary test.

العلة=
Hexagonal-architecture correctness. If the Domain layer imports Legacy* (which is a Governance-layer compatibility adapter for pilot behaviour), the eventual target-canonical implementation cannot cleanly replace the legacy behaviour — the Domain would carry a Legacy dependency edge that outlasts the pilot.

القادح=
Choosing the "record a residual" option (§option-b) fires this قادح if the residual becomes indefinite — the anti-pattern would entrench.

الصحة=
Valid remediation (option-a chosen):

1. Introduce 3 rule ports in `Modules\JeaServices\Domain\Srv001\Contracts\`:
   - `Srv001ExplorationMatrixRule`
   - `Srv001WellsCountRule`
   - `Srv001NetDepthRule`

   Each port exposes: `compute($primitiveInput): array` returning the raw domain-oriented output, plus `ruleVersionId(): int` and `openDecisions(): list<string>` so the Target* wrapper can build the `ServiceCalculationResult` in the governance shape.

2. Refactor 3 Target* calculators to accept the port via constructor instead of the Legacy* class. Remove Legacy imports from Domain.

3. Introduce 3 Legacy-bridge adapters in a new `Modules\JeaServices\Adapters\Srv001\` namespace (outside Domain):
   - `LegacyBridgeExplorationMatrixRule` implements `Srv001ExplorationMatrixRule` — delegates to Legacy*
   - `LegacyBridgeWellsCountRule` implements `Srv001WellsCountRule`
   - `LegacyBridgeNetDepthRule` implements `Srv001NetDepthRule`

4. Update the 2 TD-01 tests (`TargetCalculatorsParityTest`, `TargetSrv001SubmissionPolicyTest`) to inject the Adapter — parity assertions remain unchanged; numeric outputs still identical to legacy.

5. Add an architecture test `Srv001DomainBoundariesTest` that asserts:
   - Every file under `backend/modules/JeaServices/Domain/Srv001/` must NOT contain any string matching `Modules\JeaServices\Governance\Srv001\Legacy` (import OR fully-qualified reference).
   - Every file under the same directory must NOT contain any string matching `Modules\JeaServices\Engine\` (engines like WellsCountCalculator/NetDepthTable — the raw pilot classes).

الفساد=
The current TD-01 state is fasid at the boundary — repairable exactly by the refactor above.

البطلان=
Leaving the direct import in place indefinitely = batil against the mandate "prevent the dependency from becoming the final target-domain design."

الأثر=
(1) 3 Target* files edited to remove Legacy imports. (2) 3 rule ports added in Domain. (3) 3 LegacyBridge* adapters added outside Domain. (4) 2 test files updated to construct via adapter. (5) 1 architecture test added. (6) Numeric parity preserved. (7) Runtime unchanged. (8) An `RES-TD01A-02` architecture residual recorded as CLOSED (the boundary fix).

البقايا=
- Legacy* remains present in Governance\Srv001\ (SG-06); this is by design — it is the runtime path.
- Bridge adapters carry the "TARGET_DOMAIN_PROVISIONAL — using LegacyBridge pending approved target rule" classification; when the approved target rule appears, a new adapter or a native Domain rule implementation replaces the bridge.

التعارض=
Domain-layer purity vs pragmatic reuse of numerically-tested legacy code. Reconciled: purity is preserved by the port; legacy reuse continues via the adapter outside the domain.

الجمع=
Reconciled — port defines Domain need; adapter provides Legacy-backed implementation.

الترجيح=
Tier-4 (target architecture — hexagonal boundary) demands the port. Tier-5 (runtime safety — numeric parity) demands the adapter. Both requirements satisfied by option-a.

التوقف=
Not stopped. Refactor is authorised as "narrowly required" architectural correction per user directive.

READINESS_CLASSIFICATION=After remediation: `BOUNDARY_HEALED`. No effect on JDG-TD00-02 authorised scope or overall program verdict.

IMPLEMENTATION_ACTION=Execute the 5-step remediation. One commit. Include architecture test.

CLOSURE_EVIDENCE=
- grep after refactor: 0 matches of Legacy imports in Domain\Srv001
- architecture test passes
- TD-01 parity tests still pass unchanged (numeric outputs unchanged)
- PHPStan clean
- Regression sweep clean
