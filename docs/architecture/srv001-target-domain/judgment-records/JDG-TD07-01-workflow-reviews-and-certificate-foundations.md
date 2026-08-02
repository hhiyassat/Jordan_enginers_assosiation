JUDGMENT_ID=JDG-TD07-01
TITLE=Versioned SRV-001 workflow + reviews + partial-edit-grant use cases + certificate foundations (all unresolved transitions typed-blocked)
OWNER=TD-07
PHASE=TD-07 (Batch 4 · versioned workflow, review, special paths, certificates — mandatory stop after this phase)

الوضع=
TD-05 (`389342f`) delivered the fail-closed external-port boundary. TD-06 (`d104373`) delivered document metadata + partial-edit-grant structural VOs + security ports. Batch 4 requires: versioned workflow graph with typed blocked transitions for OD-18/OD-29/OD-31/OD-32/OD-33/OD-34; reviews + notes; PartialEditGrant runtime use cases (issue / consume / revoke / expire / validate); BURA + Map ports; payment eligibility boundary; certificate eligibility + issuance request (blocked). All while TARGET_RUNTIME_STATUS remains INACTIVE and PRODUCTION_CERTIFICATE_STATUS remains BLOCKED.

تحرير_محل_النزاع=
1. **How do unresolved workflow transitions appear in the graph?** Three options: (a) omit them from the graph entirely so the evaluator returns NOT_FOUND; (b) include them with `RUNTIME_BLOCKED_BY_OD` so callers see the distinction between "graph has no such transition in this version" and "the transition exists but awaits an Open Decision"; (c) include with a boolean `enabled=false` flag that erodes the typing signal.
2. **How does the certificate foundation avoid claiming production readiness?** Model eligibility + draft issuance request but forbid the request from reaching ISSUED status at runtime.
3. **Where does the review-decision invariant live?** In the caller (fragile), or in the ReviewDecision VO's construction (enforced by construction).

السبب=
The mandate is explicit:
- "unresolved transitions must be represented as typed blocked transitions, not guessed final behavior"
- "Provide typed result: WORKFLOW_TRANSITION_BLOCKED; reason=OD-34"
- "Do not activate unresolved workflows"
- "PRODUCTION_CERTIFICATE_STATUS=BLOCKED"
- "mandatory rejection note enforced"
- Explicit list of blocking ODs: OD-18, OD-29, OD-30, OD-31, OD-32, OD-33, OD-34

الشرط=
- `Srv001WorkflowGraph::currentVersion()` must report `runtimeStatus=INACTIVE` and enumerate blocking ODs.
- The second_review_approve transition must return `BLOCKED_BY_OD` with `['OD-34']` in the blocking-OD list.
- Committee substitution (OD-31) + alternate second reviewer (OD-32) + sensory-inspection gate (OD-33) must NOT appear as enumerated actions — they return `NOT_FOUND` from the evaluator, preserving the semantic distinction between "unresolved OD" and "not in this version's graph".
- `ReviewDecision::__construct()` must throw when outcome is RETURN or REJECT and no MANDATORY_REJECTION note is attached — invariant enforced by construction.
- BURA (InspectionAdditionPort, TransactionStopPort) + Map (LocationLinkPort) ports must exist but no production adapter may be wired.
- Payment eligibility must be a boundary decision only — `PaymentEligibilityDecision` must have NO method that could initiate payment (`pay`, `initiate`, `charge`, `confirm`, `settle`, `refund` all absent).
- `CertificateIssuanceRequest` defaults to STATUS_DRAFT; ISSUED status exists only as an enum value the runtime today never sets.
- No target `RuleVersion` promoted. No container binding for target runtime.
- Historical transition definitions immutable (`readonly` properties enforced by architecture test).

المانع=
Adding a runtime consumer that resolves `Srv001WorkflowGraph` from the container and calls the evaluator would activate the workflow. Forgetting the OD-34 typed BLOCKED and leaving the evaluator to say NOT_FOUND would erase the distinction between "graph structure incomplete" and "graph awaiting OD closure". Colocating the mandatory-note invariant with callers instead of ReviewDecision construction would let unenforced call sites persist rejections without notes.

العلة=
Runtime safety + evidence integrity + separation of concerns. Workflow transitions are governance surfaces — misreporting a transition's status (BLOCKED vs NOT_FOUND vs ALLOWED) misdirects downstream reviewers and audit auditors. Certificate issuance touches legal artefacts — a false-positive ISSUED state creates unrecoverable evidence.

القادح=
Any implementation that:
- makes any WorkflowTransitionDecision return `ALLOWED` for the OD-34 second_review_approve transition
- adds a runtime consumer for `Srv001WorkflowGraph` in `JeaServicesServiceProvider`
- returns `CertificateEligibilityDecision::ELIGIBLE` without payment evidence
- lets a `ReviewDecision::REJECT` be constructed without a mandatory-rejection note
- claims a BURA or Map adapter is production-ready
- introduces production credentials / URLs

Would fire this قادح.

الصحة=
Valid implementation:

1. **Workflow graph** — `Srv001WorkflowGraph::CURRENT_VERSION_ID` = `srv001-workflow-td07-provisional-v1`; `currentVersion()->runtimeStatus = INACTIVE`; `blockingOds` enumerates OD-18, OD-29, OD-30, OD-31, OD-32, OD-33, OD-34. `transitions()` returns the confirmed structural path (submit → offices-dept-approve → first_review_approve → …) PLUS the OD-34-blocked second_review_approve transition + downstream payment/certificate paths (marked ALLOWED for evaluator symmetry once OD-34 resolves — today never reached at runtime).
2. **Evaluator** — pure decision function. Returns `ALLOWED / BLOCKED_BY_OD / NOT_FOUND` (MANUAL_REVIEW and BLOCKED_BY_MISSING_EVIDENCE reserved for future consumers). `isAllowed()` reads only the outcome field — fail-closed by construction.
3. **Reviews** — `ReviewNote` with 4 categories (`MANDATORY_REJECTION`, `OPTIONAL_ACCEPTANCE`, `COMMUNITY_OBSERVATION`, `INTERNAL_MANDATORY`); `isAutomaticallyBlocking()` returns true only for MANDATORY_REJECTION. `ReviewDecision` construction enforces the mandatory-note invariant.
4. **PartialEditGrant use cases** — five explicit use cases (Issue, Consume, Revoke, Expire, Validate). Consume routes through the enforcement policy and throws `InvalidArgumentException` when the policy denies (grants stay honest — no partial execution). Revoke + Expire are pure state transitions returning new VOs.
5. **BURA + Map ports** — three port interfaces (`InspectionAdditionPort`, `TransactionStopPort`, `LocationLinkPort`) returning `Srv001PortDecision` from TD-05. No adapters wired.
6. **Payment eligibility** — `PaymentEligibilityDecision` with 4 outcomes (`ELIGIBLE`, `BLOCKED`, `RULE_UNAVAILABLE`, `MANUAL_REVIEW_REQUIRED`). NO side-effect methods. Actual payment orchestration lands in TD-08.
7. **Certificates** — `CertificateEligibilityDecision`, `CertificateIssuanceRequest` (defaults DRAFT), `CertificateRenderingPort`, `CertificateSigningPort`. Production issuance BLOCKED.

الفساد=
Registering the graph + evaluator as container singletons would be fasid — accidentally resolving them at runtime would surface a still-inactive graph as live workflow. Not done.

البطلان=
Adding a production certificate rendering adapter with hardcoded PDF templates before publication authorisation lands would be batil — every issued document would carry unratified legal weight.

الأثر=
- 18 new source files:
  * Domain/Workflow/ValueObjects: `WorkflowVersion`, `WorkflowState`, `WorkflowAction`, `WorkflowTransitionDefinition`, `WorkflowTransitionDecision`
  * Domain/Workflow: `Srv001WorkflowGraph`, `WorkflowTransitionEvaluator`
  * Domain/Reviews/ValueObjects: `ReviewNote`, `ReviewDecision`
  * Domain/Documents/UseCases: `Issue`, `Consume`, `Revoke`, `Expire`, `Validate` PartialEditGrant use cases (5)
  * Domain/Srv001/Contracts: `InspectionAdditionPort`, `TransactionStopPort`, `LocationLinkPort`
  * Domain/Payment/ValueObjects: `PaymentEligibilityDecision`
  * Domain/Certificates/ValueObjects: `CertificateEligibilityDecision`, `CertificateIssuanceRequest`
  * Domain/Certificates/Contracts: `CertificateRenderingPort`, `CertificateSigningPort`
- 3 new test files (28 focused tests / 140 assertions on SQLite).
- 0 modifications to existing source files.
- 0 new migrations.
- 0 changes to controllers, providers, seeders, workflow engine.

البقايا=
- **RES-TD07-01** (OPEN) — `Srv001WorkflowGraph` + `WorkflowTransitionEvaluator` not container-bound; no runtime consumer. When wired, MUST fail-closed on `NOT_FOUND` / `BLOCKED_BY_OD` and MUST NOT be resolvable by any existing controller until publication authorization lands.
- **RES-TD07-02** (OPEN) — PartialEditGrant use cases have no repository / audit-writer dependencies today. When persistence + audit land, the Consume use case's decide-then-persist path MUST commit inside one transaction (structural invariant recorded in the use case's docblock).
- **RES-TD07-03** (OPEN) — BURA + Map port adapters absent. Per-port closure requires signed integration contract + fake adapter promotion + contract test.
- **RES-TD07-04** (OPEN) — Certificate rendering / signing adapters absent. Production issuance remains BLOCKED. Closure requires publication authorization + production configuration + verified adapter (5-step control axis, TD-06 doctrine).
- **RES-TD07-05** (OPEN) — OD-18 special path resumption + reinforcement effects, OD-29 final action/state dictionary, OD-31/OD-32/OD-33/OD-34 all unchanged. TD-07 preserves the block by NOT enumerating alternate paths.

التعارض=
None. Every prohibition honoured.

الجمع=
Reconciled. TD-07 delivers the versioned workflow architecture + review VOs + partial-edit-grant runtime use cases + BURA/Map ports + payment/certificate foundations as pure Domain code with typed decisions. No runtime activation. No production integration.

الترجيح=
Tier-5 (runtime safety — no accidental activation of unresolved transitions) + Tier-4 (target architecture — versioned graph with typed BLOCKED_BY_OD signal) + Tier-3 (evidence integrity — construction-time invariants for ReviewDecision + WorkflowTransitionDefinition).

التوقف=
STOPPED on:
- activating `Srv001WorkflowGraph` at runtime
- selecting either second_review_approve → Approved_Technically OR second_review → first_review as canonical
- adding BURA/Map production adapters
- issuing production certificates
- pushing, tagging, merging, deploying

Continues on: versioned graph + typed decisions + review VOs + partial-edit-grant use cases + certificate foundations (all structural).

READINESS_CLASSIFICATION=Compliant with TD-07 mandate. TARGET_RUNTIME_STATUS=INACTIVE, WORKFLOW_RUNTIME_STATUS=INACTIVE, PRODUCTION_CERTIFICATE_STATUS=BLOCKED, all six referenced ODs preserved as blockers.

CLOSURE_EVIDENCE=
- Focused TD-07 tests: **28/28 PASS / 140 assertions / 15 ms** on SQLite
- All domain + governance suites on Postgres 15-alpine: **179/179 PASS / 581 assertions / 4671 ms**
- Unit suite: **402/402 PASS / 1078 assertions** (+28 vs TD-06 baseline)
- Feature suite: **738/745 / 7 skipped / 2747 assertions** (unchanged — TD-07 is pure Unit / Architecture)
- Architecture suite: **26/27 / 1 skipped / 1241 assertions** (unchanged test count; +160 assertions from graph invariants exercised via existing tests)
- PHPStan: **0 errors**
- Postgres data integrity: only `migrations` row count populated (54 rows, unchanged)
