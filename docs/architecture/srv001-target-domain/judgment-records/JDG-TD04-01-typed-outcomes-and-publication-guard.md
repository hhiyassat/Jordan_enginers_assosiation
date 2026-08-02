JUDGMENT_ID=JDG-TD04-01
TITLE=Typed calculator outcomes + target rule-version publication guard (SRV-001)
OWNER=TD-04
PHASE=TD-04 (Batch 2 · provisional target calculation architecture — mandatory stop after this phase)

الوضع=
TD-03 (commit `792b0aa`) closed RES-SG06-01 for SRV-001 — the real HTTP submission route now runs through `SubmitApplicationUseCase` atomically. The runtime path today produces its numeric derived values by delegating to `LegacySrv001SubmissionPolicy` (SG-06). The parallel `TargetSrv001SubmissionPolicy` skeleton (TD-01) exists and passes numeric parity tests but is INACTIVE — no runtime consumer, no promoted `RuleVersion`, no derived-value binding on the target path. TD-04 delivers the architectural pieces required to reason about target-domain calculator provenance WITHOUT activating disputed values: typed outcome classification, evidence-rich results, and a publication guard that a future promoter would consult before flipping a `RuleVersion` to `APPROVED`.

تحرير_محل_النزاع=
1. **Where does outcome classification live?** Inside each `Target*` calculator (each returns a typed result directly), or in a separate classifier that inspects a `ServiceCalculationResult` after the fact.
2. **What does the publication guard actually enforce?** A hard block on promoting any `RuleVersion` whose target-domain outcome is not `CALCULATED`, versus a softer advisory that logs the classification but does not prevent promotion.
3. **How are draft target `RuleVersion`s expressed?** New rows in `Srv001RulesSeeder`, versus a separate `Srv001TargetDraftRulesSeeder`, versus not seeding drafts at all (the existing `PROVISIONAL` seed rows already stand in).

السبب=
The user Batch 2 mandate lists these as explicit deliverables ("typed outcomes", "publication guard for unpublished RuleVersions", "evidence-rich results"), and also lists explicit prohibitions ("without activating disputed values", "TARGET_RUNTIME_STATUS=INACTIVE must hold", "TARGET_RULE_PROMOTION_PERMITTED=NO"). The dispute is HOW to satisfy the deliverables without violating the prohibitions.

الشرط=
- No `Target*` calculator's numeric behaviour may change.
- No new runtime consumer of the target policy may be wired.
- No `RuleVersion` may be promoted.
- The publication guard must be REGISTERED (bound) but must NOT be invoked by any current publisher.
- Every typed outcome must be derivable from a `ServiceCalculationResult` alone (so it can be classified retroactively on existing snapshots).
- The classifier's decision-table must be pure (no side effects) so it can run inside a snapshot immutability boundary.
- The rule matrix CSV must document every seeded SRV-001 rule + its expected typed outcome + its OD-Closure requirement for promotion.

المانع=
Attaching outcome classification INSIDE each calculator would spread the same decision-table across three files + force a calculator constructor change (breaking the SG-05 `ServiceCalculationPolicy` contract). Wiring the publication guard into a publisher would activate an enforcement that no signed process yet ratifies. Introducing new draft rows would touch `Srv001RulesSeeder` and require re-verification of every consumer of those rows.

العلة=
Evidence-integrity + separation of concerns. The calculators today produce numeric-plus-metadata (`ServiceCalculationResult`). The provenance question — "is this numeric answer safe to bind, or only safe to simulate?" — depends on cross-cutting state (rule-version status, input completeness, output semantics) that is orthogonal to the numeric compute. Colocating them would couple two independently-evolving concerns.

القادح=
Any implementation that:
- promotes a `RuleVersion` to `APPROVED` (target rule promotion)
- wires the publication guard into `ServiceVersionPublisher` or any other current publisher
- adds a runtime consumer for `TargetSrv001SubmissionPolicy`
- changes `LegacySrv001SubmissionPolicy` numeric behaviour
- claims the typed outcome enum can bind SIMULATION_ONLY / BLOCKED / CONFLICTED / etc. results

Would fire this قادح.

الصحة=
Valid implementation:

1. **`Srv001CalculationOutcome` enum** (Domain/Srv001/Contracts/) — 7 states: `CALCULATED`, `BLOCKED`, `CONFLICTED`, `INSUFFICIENT_INPUT`, `MANUAL_REVIEW`, `SIMULATION_ONLY`, `NOT_APPLICABLE`. Exposes `all()`, `isValid()`, `bindingOutcomes()`, `nonBindingOutcomes()`. Every consumer must partition its behaviour along the binding vs non-binding split.

2. **`Srv001TypedCalculationResult` value object** (Domain/Srv001/ValueObjects/) — immutable wrapper around `ServiceCalculationResult` + outcome + classifier reason + classifier evidence. Never mutates the underlying numeric result. Exposes `isBinding()`, `isNonBinding()`, and `toSnapshotIntermediateValuesExtension()` for downstream snapshot persistence.

3. **`Srv001CalculatorOutcomeClassifier`** (Domain/Srv001/) — pure function that takes a `ServiceCalculationResult` and returns a `Srv001TypedCalculationResult`. Priority order (first match wins):
   1. `CONFLICTED` (outputs.status='CONFLICTED' or outputs.error present)
   2. `INSUFFICIENT_INPUT` (required numeric input keys missing / non-positive)
   3. `NOT_APPLICABLE` (outputs.status='NOT_APPLICABLE')
   4. `MANUAL_REVIEW` (outputs.status='SPECIAL_STUDY_REQUIRED')
   5. `BLOCKED` (rule_version.business_approval_status='REJECTED')
   6. `SIMULATION_ONLY` (rule_version.business_approval_status='PROVISIONAL' or 'PENDING' or unknown)
   7. `CALCULATED` (rule_version.business_approval_status='APPROVED', everything else well-formed)

4. **`TargetRuleVersionPublicationPolicy` + `TargetRuleVersionPublicationDecision`** (Governance/) — pure decision function that returns `allow()` iff every non-approve status transition or every approve transition backed by a `CALCULATED` typed outcome. Every `SIMULATION_ONLY` / `BLOCKED` / `CONFLICTED` / `MANUAL_REVIEW` / `INSUFFICIENT_INPUT` / `NOT_APPLICABLE` / unknown outcome blocks an approve transition. Idempotent re-approve on an already-APPROVED rule is allowed. Non-approve transitions are not gated.

5. **NO seeder change** — `Srv001RulesSeeder` already seeds Matrix `APPROVED` + Wells `PROVISIONAL` + NetDepth `PROVISIONAL`. That is exactly the shape TD-04 needs to demonstrate the classifier + publication guard: the Matrix rule already yields `CALCULATED`, the two provisional rules already yield `SIMULATION_ONLY`. No draft-rule invention required.

6. **Publication guard NOT invoked** — the guard class is added but not yet consumed. Test `test_policy_is_not_wired_into_any_existing_publisher` proves by source-grep that neither `ServiceVersionPublisher` nor `JeaServicesServiceProvider` references the class body (only namespace declaration + the guard itself). This is intentional: the promoter that would consult the guard is out of TD-04 scope.

7. **Rule matrix CSV** — `docs/architecture/srv001-target-domain/td-04/srv001-rule-matrix.csv` lists each seeded SRV-001 rule + current seeded status + default typed outcome + binding flag + source reference + OD-Closure requirement for promotion.

الفساد=
Colocating outcome classification with numeric calculators would be fasid — technically works but couples orthogonal concerns; repairable but blocks retroactive classification of existing snapshots.

البطلان=
Silently wiring the publication guard into `ServiceVersionPublisher` would be batil — activates enforcement that no signed process yet ratifies; would block legitimate legacy-pilot promotions of other services in TD-05+.

الأثر=
- 5 new source files (Governance: `TargetRuleVersionPublicationPolicy` + `TargetRuleVersionPublicationDecision`; Domain: `Srv001CalculationOutcome`, `Srv001TypedCalculationResult`, `Srv001CalculatorOutcomeClassifier`).
- 3 new test files (unit + feature): `Srv001CalculationOutcomeTest` (7 tests), `Srv001CalculatorOutcomeClassifierTest` (8 tests), `TargetRuleVersionPublicationPolicyTest` (9 tests) — 24 focused TD-04 tests total.
- 1 new documentation artifact: `td-04/srv001-rule-matrix.csv`.
- 0 modifications to existing source files.
- 0 modifications to seeders.
- 0 modifications to migrations.
- 0 modifications to controllers.
- 0 modifications to legacy calculators or the legacy submission policy.
- 0 modifications to workflow or fee code.
- 0 new residuals apart from the intentional non-wiring residual documented below.

البقايا=
- **RES-TD04-01** (OPEN) — the publication guard is bound but not consumed. Consumer will land when a target `RuleVersion` promoter is built (TD-05+ scope). This is intentional under the mandate.
- **RES-TD04-02** (OPEN) — the classifier's `ServiceCalculationResult`-to-outcome mapping is not yet invoked by any Target calculator wrapper. When TD-05+ wires the classifier at either the calculator or the `TargetSrv001SubmissionPolicy` composition boundary, `Srv001TypedCalculationResult` will start being produced at runtime. Today it exists only for unit-testability + future wiring.
- **RES-TD00-05** — unchanged (OPEN). 15 rules classified AUTHORIZED for structural build but require simulation/PROVISIONAL numeric outputs and per-rule OD-Closure before publication. TD-04 does not change any rule's OD-Closure status.

التعارض=
None. Every prohibition is honoured: no numeric behaviour change, no rule promotion, no runtime activation, no legacy modification.

الجمع=
Reconciled. TD-04 delivers the architectural pieces required to reason about target-domain calculator provenance (typed outcomes, publication guard, rule-matrix documentation) without activating any disputed value, promoting any RuleVersion, or altering any legacy path.

الترجيح=
Tier-4 (target architecture) + Tier-3 (backward compatibility — legacy pilot unchanged) + evidence-integrity (rule-matrix CSV documents current authorization state per rule) all support the chosen design.

التوقف=
STOPPED on:
- promoting any target-domain `RuleVersion` to `APPROVED`
- wiring the publication guard into any current publisher
- adding a runtime consumer for `TargetSrv001SubmissionPolicy`
- changing any legacy calculator's numeric behaviour
- publishing any `RuleVersion` classification change
- pushing, tagging, merging, or deploying

Continues on: honest architectural build for target-domain calculation provenance (typed outcomes + classifier + publication policy + rule matrix documentation).

READINESS_CLASSIFICATION=Compliant with TD-04 mandate. TARGET_RUNTIME_STATUS=INACTIVE maintained. Provisional-rule promotion remains BLOCKED by the publication policy (informationally — the policy is not yet consumed by any publisher).

IMPLEMENTATION_ACTION=Create the enum + typed result VO + classifier in Domain/Srv001; create the publication policy + decision VO in Governance/; add 24 focused tests; document rule provenance in a new CSV; do NOT change any calculator, seeder, publisher, controller, or provider registration for target-domain use.

CLOSURE_EVIDENCE=
- Focused TD-04 tests: **24/24 PASS / 60 assertions / 461 ms** on SQLite
- Focused TD-04 tests: **24/24 PASS / 60 assertions** on Postgres 15-alpine (part of `tests/Feature/Domain/ tests/Feature/Governance/ tests/Unit/Domain/` = 93/93 / 256 assertions / 4474 ms)
- Unit suite: **316/316 PASS / 753 assertions** (+7 vs TD-03 baseline)
- Feature suite: **738 passed / 745 total / 7 skipped / 2747 assertions** (+17 vs TD-03 baseline / +0 new skips)
- Architecture suite: **17/18 / 1 skipped / 58 assertions** (unchanged)
- PHPStan: **0 errors**
- Postgres data integrity: only `migrations` row count populated (54, unchanged)
- Rule matrix CSV committed: `docs/architecture/srv001-target-domain/td-04/srv001-rule-matrix.csv`
