JUDGMENT_ID=JDG-TD02-01
TITLE=Atomic application submission use case (data + version bind + snapshots in one DB transaction)
OWNER=TD-02
PHASE=TD-02

الوضع=
Foundation (SG-*) delivered CalculationSnapshotWriter (SG-04) + ApplicationVersionBinder (SG-03) + ServiceSubmissionDecision (SG-05) as separate components. Nothing consumes all three atomically. Legacy runtime path (Srv001Guard) still writes derived values via `$app->save()` inside its own single-row save, BEFORE WorkflowEngine::submit's transaction opens (documented in JDG-RC01-06 §item 5 as an acknowledged pre-existing legacy quirk). No consumer today calls CalculationSnapshotWriter::writeForSubmit — snapshots are effectively write-capable-but-never-written. Target-domain skeleton (TD-01/TD-01A) is unwired.

تحرير_محل_النزاع=
Should TD-02 introduce a use-case class that consumes a ServiceSubmissionDecision and performs (data merge + version binding + snapshot writes) inside one DB transaction, WITHOUT wiring it into the runtime? If yes, what is the correct dependency shape so future runtime activation can happen cleanly (RES-SG06-01) — and how do we prove atomicity + rollback + immutability without changing legacy numeric outputs?

السبب=
User TD-02 directive: "introduce the application submission use case; consume ServiceSubmissionDecision; bind the ServiceDefinitionVersion; call CalculationSnapshotWriter::writeForSubmit; place application persistence, version binding, and snapshot writing inside one database transaction". Also: "keep Target domain runtime activation blocked; keep RuleVersion unpublished or simulation/draft only; do not introduce SRV-001 branching into the generic engine."

الشرط=
Use case MUST:
- be service-code AGNOSTIC (no `if service_code === 'SRV-001'` branch anywhere in generic layer)
- consume ServiceSubmissionDecision (produced upstream by any policy — Legacy or Target)
- open a single DB::transaction that wraps: data merge → version bind → application save → snapshot writes
- roll back all three side effects on ANY failure inside the transaction
- not touch WorkflowEngine::submit's status transition, fee calculation, or workflow stage — that remains the runtime's responsibility
- not touch Srv001Guard, ServiceSubmissionGuardRegistry, or ApplicationController wiring
- preserve ServiceDefinition::lockForUpdate() concurrency invariant (RC-03) by not calling ServiceVersionPublisher during submission
- provide a typed result (SubmitApplicationResult) so callers know exactly what committed / what rolled back
- support test doubles for ApplicationVersionBinder to prove rollback semantics on binding failure (requires a small interface extraction)

المانع=
Wiring the use case into any controller / registry / observer would violate the user directive "keep Target domain runtime activation blocked". Passing a service_code parameter to the use case would violate "do not introduce SRV-001 branching into the generic engine". Skipping the rollback proofs would leave the atomicity claim unverified.

العلة=
Transactional integrity + phased delivery + generic-engine purity. Historically (per JDG-RC01-06 §item 5) `Srv001Guard::validate` saves outside the caller's transaction — an accepted legacy quirk. The use case restores proper transactional discipline for the target-domain path, without touching legacy.

القادح=
Any implementation that:
- makes the use case service-code-aware (e.g., resolves the policy internally by looking up service_code)
- calls the use case from any controller/registry/observer today
- publishes a RuleVersion or promotes any rule to APPROVED
- changes any Legacy* calculator output

Would fire this قادح.

الصحة=
Valid implementation:
- `Modules\JeaServices\UseCases\SubmitApplication\SubmitApplicationUseCase` — service-code-agnostic, dependency-injected `ApplicationVersionBinderContract` (interface) + `CalculationSnapshotWriter`.
- `Modules\JeaServices\UseCases\SubmitApplication\SubmitApplicationResult` — typed result with `succeeded` / `rejectionErrors` / `rollbackReason` / `versionBindingClassification` / `boundVersionId` / `snapshotIds` / `derivedValuesPersisted`.
- New `Modules\JeaServices\Governance\ApplicationVersionBinderContract` interface (extracted from the existing `final ApplicationVersionBinder`). Concrete implementation continues to be the SG-03 class; use case depends on the abstraction to enable test-double substitution for rollback proof.
- 12 focused tests covering: happy path commit, rejected decision short-circuit, snapshot-failure rollback, version-binding-failure rollback, immutability, rule-provenance recording, legacy-output parity, TARGET_RUNTIME_ACTIVATED=NO source-level assertion + registry check, PUBLICATION_STATUS=BLOCKED verification, LEGACY_UNVERSIONED classification, and second-commit unique-constraint rollback.

الفساد=
An implementation that opens a transaction but places any of the three writes OUTSIDE it would be fasid (defeats atomicity). Repairable by moving the write inside the closure.

البطلان=
Wiring the use case into the runtime path in TD-02 = batil (violates the "keep Target domain runtime activation blocked" directive and would prematurely close RES-SG06-01).

الأثر=
(1) One new namespace `UseCases\SubmitApplication\` with 2 classes. (2) One new governance-layer interface `ApplicationVersionBinderContract` — SG-03 concrete class now implements it. Existing binder API unchanged. (3) 12 focused tests in `tests/Feature/UseCases/`. (4) Runtime unchanged — `Srv001Guard` still sole SRV-001 runtime path. (5) RES-SG06-01 remains OPEN per user directive: "Do not close unless the actual runtime direct-write path is replaced".

البقايا=
- RES-TD02-01 (informational): runtime consumer of `SubmitApplicationUseCase` — a future WorkflowEngine::submit replacement or a new submission controller — is a TD-03+ decision. Closes RES-SG06-01 when built.
- RES-TD02-02 (design): once the runtime consumer arrives, `ApplicationVersionBinderContract` will need a container binding in `JeaServicesServiceProvider` so DI resolves it automatically.

التعارض=
None between the mandate and the chosen implementation. Legacy behaviour preservation, generic-engine purity, and transaction atomicity are jointly satisfied.

الجمع=
Reconciled — the use case is a callable-only orchestrator; the runtime path is unchanged; the extraction of an interface adds no behaviour, only enables clean test doubles.

الترجيح=
Tier-4 (target architecture — transactional discipline + hexagonal ports) + Tier-5 (runtime safety — atomicity proven) + Tier-6 (current implementation — Srv001Guard untouched) all support the chosen design.

التوقف=
STOPPED on runtime wiring for the use case (per user directive). Continues on structural definition + rollback tests.

READINESS_CLASSIFICATION=Compliant with JDG-TD00-02 authorised scope item #2. RES-SG06-01 status unchanged (still OPEN per user directive).

IMPLEMENTATION_ACTION=Create SubmitApplicationUseCase + SubmitApplicationResult + ApplicationVersionBinderContract. 12-test suite proving every mandate assertion. One commit. No push.

CLOSURE_EVIDENCE=
- 12/12 focused tests pass (59 assertions / 480 ms)
- Full suite: 1031 collected / 1023 passed / 8 skipped (unchanged since TD-01B — no new skips)
- PHPStan full baseline: 0 errors
- grep confirms `SubmitApplicationUseCase` not imported by ApplicationController / PaymentsController / CertificatesController / WorkflowEngine (source-level assertion in test)
- `ServiceSubmissionGuardRegistry::registeredCodes()` still contains `'SRV-001'` (assertion in test)
- `ServicePublicationPolicy::evaluate` still returns BLOCKED for the seeded SRV-001 (assertion in test)
