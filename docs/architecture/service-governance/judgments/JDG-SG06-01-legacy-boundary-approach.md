JUDGMENT_ID=JDG-SG06-01
TITLE=SRV-001 legacy boundary — refactor-in-place vs parallel implementation
SCOPE=architecture/service-governance/SG-06
OWNER=service-governance-remediation

الوضع=
`Srv001Guard::validate` is a tested (`Srv001GuardTest`), production-wired component. It mutates `$app->data` and calls `$app->save()` inside the validate method. Program §Phase SG-06 mandates "Establish a safe boundary around the existing SRV-001 pilot without implementing the target SRS" AND "Preserve all externally observable current behavior" AND "After refactoring, all characterization tests must remain unchanged."

تحرير_محل_النزاع=
Should SG-06 (A) refactor `Srv001Guard::validate` in place to return a `ServiceSubmissionDecision` and stop calling `$app->save`, OR (B) introduce a NEW `LegacySrv001SubmissionPolicy` in parallel that satisfies the new contract, leaving `Srv001Guard` intact until a follow-up phase wires the new class end-to-end?

السبب=
The characterization-tests-must-remain-unchanged mandate is a strong constraint. Refactoring in place changes the return type + removes the `$app->save` — every caller downstream (WorkflowEngine::submit → app(ServiceSubmissionGuardRegistry) → Srv001Guard::validate) sees a different return shape. Every test that currently asserts on `$app->save`'s side effects would need to be moved to the caller.

الشرط=
Chosen approach must (i) NOT change observable behaviour of the current SRV-001 submission flow, (ii) NOT alter numeric outputs, (iii) create a class that implements the new `ServiceSubmissionPolicy` contract from SG-05, (iv) create wrappers around the three calculators that implement `ServiceCalculationPolicy` from SG-05, (v) leave characterization tests passing verbatim.

المانع=
Refactor-in-place would break every current test that pins `Srv001Guard::validate` returning `[]` on success — the new return would be a `ServiceSubmissionDecision` object. It would also require updating `ServiceSubmissionGuardRegistry` to a new interface. This is a substantial change with high blast radius.

Parallel-implementation preserves the existing tests unchanged and lets SG-06 introduce the boundary CLASS without wiring it. A follow-up phase wires the new class end-to-end once the calling use case is ready to consume typed decisions.

العلة=
Program §Section 14 §"a proposed generic abstraction has only one speculative consumer" mentions `التوقف` — but the target CONSUMER (a use case that reads typed decisions) does not exist yet at HEAD. Refactor-in-place would require simultaneously introducing that consumer — expanding scope beyond SG-06.

القادح=
None for the parallel approach.

الصحة=
Valid approach: PARALLEL implementation. Ship `LegacySrv001SubmissionPolicy` implementing `ServiceSubmissionPolicy`. Ship three calculator adapters implementing `ServiceCalculationPolicy`. Do NOT touch `Srv001Guard` or `ServiceSubmissionGuardRegistry`. Do NOT change WorkflowEngine::submit's guard call. Characterization for the NEW class asserts the same numeric outputs the OLD guard produces on identical input.

الفساد=
Refactor-in-place without a matching use case would be fasid — the new return shape would be discarded by the current caller.

البطلان=
Changing SRV-001 numeric behaviour to satisfy the target SRS is batil for this program (§ intro).

الأثر=
(1) New files added; no existing SRV-001 file touched. (2) `Srv001Guard` remains the runtime path; `LegacySrv001SubmissionPolicy` is the parallel-shape class. (3) Follow-up phase (post-program) swaps the caller from the old guard to the new policy inside its own transaction.

البقايا=
RES-SG06-01: Wire the calling use case to consume `ServiceSubmissionDecision` and switch `ServiceSubmissionGuardRegistry` (or its successor) to route to `LegacySrv001SubmissionPolicy`. Owner: post-program.

التعارض=
Program mandate ("After refactoring, all characterization tests must remain unchanged") vs a strict reading of "refactor". Reconciled: SG-06 refactors the SHAPE (introducing the boundary class), not the CONSUMER wiring.

الجمع=
Reconcile: parallel class satisfies the boundary mandate; wiring is a follow-up.

الترجيح=
Tier-5 (runtime safety + historical integrity — no observable behaviour change).

التوقف=
Not stopped.

EVIDENCE=
- backend/modules/JeaServices/Engine/Srv001Guard.php (existing implementation, tested)
- backend/tests/Feature/Srv001GuardTest.php (characterization tests to preserve)
- backend/modules/JeaServices/Governance/ServiceSubmissionPolicy.php (SG-05 contract)

DECISION=
Parallel `LegacySrv001SubmissionPolicy` + three calculator adapters. `Srv001Guard` untouched. Wiring deferred to RES-SG06-01.

IMPLEMENTATION_EFFECT=
New files: `LegacySrv001SubmissionPolicy.php`, `LegacyExplorationRequirementMatrixCalculator.php`, `LegacyWellsCountCalculator.php`, `LegacyNetDepthTableCalculator.php`. Zero existing-file mutation.

MIGRATION_EFFECT=
None. Zero DB changes. Zero seeder changes. Zero controller changes.

TEST_EVIDENCE=
Test: LegacySrv001SubmissionPolicy on a happy-path submission produces the same derived-value set as Srv001Guard would. Test: three calculator adapters delegate to the underlying static methods with same outputs.

OPEN_RESIDUALS=
- RES-SG06-01 (wire the parallel class end-to-end in a follow-up).
