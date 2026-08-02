JUDGMENT_ID=JDG-TD03-01
TITLE=Route SRV-001 runtime submission through the transactional SubmitApplicationUseCase (RES-SG06-01 closure)
OWNER=TD-03
PHASE=TD-03 (Batch 2 · runtime submission integration)

الوضع=
TD-02 (commit `272b9d6`) delivered `SubmitApplicationUseCase` composing SG-03 binding + SG-04 snapshot writes + data merge inside one `DB::transaction`. TD-02-SUPP (commit `52b6cd4`) added the audit port so the audit event lands inside the same transaction, plus honest idempotency reconciliation (RES-TD02-SUPP-01 filed). Both commits explicitly left the runtime path unchanged: `ApplicationController::submit` still routed through `ServiceSubmissionGuardRegistry → Srv001Guard::validate → $app->save`, which is RES-SG06-01. Batch 2 of the controlled implementation mandate now requires that runtime rewire — actual production HTTP submission must pass through the use case, atomically — WITHOUT changing legacy numeric outputs, without publishing target-domain values, without introducing a service-code branch into the generic controller, and without touching Postgres publication concurrency.

تحرير_محل_النزاع=
Three real disputes:

1. **Dispatch surface.** Add a second registry (parallel to `ServiceSubmissionGuardRegistry`) that returns a typed-decision `ServiceSubmissionPolicy` when one is registered for a service code, versus overloading the existing guard registry with a "decision-producing" mode. The former preserves the legacy guard registry unchanged for services without a typed policy yet; the latter conflates two contracts (error-list vs. typed decision) in one registry entry.

2. **Workflow atomicity model.** Model A — `WorkflowEngine::submit` is invoked INSIDE the same outer `DB::transaction` as the use case, so a workflow failure rolls the use-case writes back atomically. Model B — the workflow transition runs post-commit via durable orchestration (e.g., an event/outbox), so the use-case commit is durable even if the transition fails and a follow-up re-drives the workflow. Model B needs infrastructure that does not exist today (no outbox; no dispatcher; no idempotent workflow re-drive). Model A can be delivered atomically without new infrastructure.

3. **Error wire format.** SG-05 `ServiceSubmissionDecision` carries `field_id => list<string>` errors. The pre-TD-03 legacy JSON contract was `field_id => string`. Preserve the legacy shape at the HTTP boundary versus break external consumers.

السبب=
User Batch 2 mandate lists all three as required decisions:
- "Do not silently choose B" — Model A vs Model B must be an explicit signed decision.
- "Do not introduce SRV-001 conditional into generic controller/engine unless via existing extension contract" — forces the extension-registry approach, not a service-code branch.
- "Preserve all current legacy numeric outputs and rejection reasons" — implies API shape preservation.

الشرط=
- Runtime path must be: HTTP controller → validated request → legacy-compatible `ServiceSubmissionDecision` provider → `SubmitApplicationUseCase` → one DB transaction (persistence + version binding + provenance + snapshots + audit) → workflow transition.
- Do NOT activate `TargetSrv001SubmissionPolicy` or any `Target*` calculator.
- Do NOT publish any `RuleVersion`.
- Legacy numeric outputs (matrix min points, min depth, wells, net depth) unchanged.
- Legacy rejection messages unchanged.
- Wire format for errors unchanged (string, not list).
- `Srv001Guard::validate → $app->save` must not run on the runtime path (RES-SG06-01 closure).
- `ServiceDefinition::lockForUpdate()` publication concurrency unchanged.
- `IDEMPOTENCY_CONTRACT_STATUS` remains `ABSENT` — do not invent a contract in TD-03.

المانع=
Adding `if ($service_code === 'SRV-001')` in the generic controller/engine would fire the قادح — the mandate explicitly forbids a service-code conditional. Silently choosing Model B without infrastructure would fabricate atomicity claims that cannot be proven end-to-end. Overloading the existing guard registry with typed-decision returns would break its contract (`array<string,list<string>>`) for existing legacy consumers.

العلة=
Runtime safety + evidence-integrity. Post-TD-02, the use case is proven atomic in isolation; without wiring it into the runtime path, RES-SG06-01 stays open forever and the atomic guarantees are not delivered to production. Delivering them requires either infrastructure that does not exist (Model B) or an explicit atomic scope around use case + workflow (Model A).

القادح=
Any implementation that:
- introduces `if ($service->code === 'SRV-001')` in `ApplicationController::submit` or `WorkflowEngine`
- silently commits the use case without a signed atomicity-model decision
- claims RES-SG06-01 is closed without exercising the real HTTP submit route
- changes legacy numeric outputs to force a passing "target parity" assertion
- publishes a target-domain `RuleVersion` to make the target policy resolvable

Would fire this قادح.

الصحة=
Valid implementation:

1. **`ServiceSubmissionPolicyRegistry`** — new registry with a single `forService(string $code): ?ServiceSubmissionPolicy` method + `registeredCodes(): array` for introspection. Parallel to `ServiceSubmissionGuardRegistry`; does not replace it. Bound as a container singleton in `JeaServicesServiceProvider` with a single registration entry: `SRV-001 => LegacySrv001SubmissionPolicy`. Service-code-agnostic contract; TD-04+ will add more entries.

2. **`ApplicationController::submit`** — after platform-wide gates (schema, docs, cross-cutting, activation) and BEFORE the JORD-69 capacity gate, look up `$typedPolicy = $policyRegistry->forService($service->code)`. If null, run the existing legacy `ServiceSubmissionGuardRegistry` path (backward-compatible). If non-null, SKIP the legacy guard registry, then after the remaining platform-wide gates dispatch the typed-decision block:

   ```php
   DB::transaction(function () use (...) {
       $decision = $typedPolicy->evaluate($app);
       if (! $decision->accepted) {
           throw new ServiceSubmissionRejected($decision->errors);
       }
       $result = app(SubmitApplicationUseCase::class)->execute($app, $decision, $actor);
       if (! $result->succeeded) {
           throw new RuntimeException('use case rollback: ' . ($result->rollbackReason ?? '?'));
       }
       (new WorkflowEngine($service))->submit($app, $actor);
   });
   ```

3. **`ServiceSubmissionRejected` exception** — new `RuntimeException` carrying the SG-05 error map; raised inside the transaction so any rejection triggers rollback of anything the use case wrote (nothing on rejection because the use case short-circuits on `! $decision->accepted`), then caught in the outer scope + mapped to a 422 with the flattened wire format.

4. **`WORKFLOW_TRANSACTION_MODEL=A`** — the workflow transition runs INSIDE the same outer `DB::transaction`. `WorkflowEngine::submit` opens its own inner transaction (Laravel savepoint); its failure raises out of the outer closure, rolling back the use case's application-data merge + version binding + snapshots + audit atomically. This is explicit and signed; Model B is not chosen.

5. **Wire-format preservation** — the `ServiceSubmissionRejected` catch block flattens `field_id => list<string>` to `field_id => string` (with `implode(' ', $msgs)` when the list has more than one entry) so no existing API consumer sees a shape change.

6. **No target-domain activation** — `JeaServicesServiceProvider` binds ONLY `LegacySrv001SubmissionPolicy` + `Legacy*` calculators + `ApplicationVersionBinderContract → ApplicationVersionBinder` + `SubmissionAuditRecorderContract → SubmissionAuditRecorder`. `TargetSrv001SubmissionPolicy` and every `Target*` calculator remain unbound; the container `bound()` check proves this in the integration test.

الفساد=
Adding a temporary `if ($service->code === 'SRV-001')` "for now" would be fasid — repairable by extracting the registry later, but violates the mandate today and encourages the pattern to spread to other services (SRV-002, SRV-006).

البطلان=
Silently choosing Model B and claiming atomicity via a mechanism that does not exist would be batil — the atomicity claim would be unprovable, and RES-SG06-01 closure would be false.

الأثر=
- Two new governance files (`ServiceSubmissionPolicyRegistry`, `ServiceSubmissionRejected`).
- `JeaServicesServiceProvider` registers the new registry as a singleton + binds the three interface contracts.
- `ApplicationController::submit` gains the typed-decision dispatch block; the legacy guard-registry call is now conditional on `$typedPolicy === null`.
- One existing use-case invariant test renamed: the pre-TD-03 assertion "`SubmitApplicationUseCase` is not imported by the controller" is inverted (TD-03 explicitly imports it); replaced with a stricter invariant that `TargetSrv001SubmissionPolicy` and every `Target*` calculator remain unwired.
- Three existing feature tests updated to seed `Srv001RulesSeeder` alongside `Srv001PilotSeeder` — this mirrors the production `DatabaseSeeder` order (rules run right after the pilot) and is required because the runtime path now resolves rule versions.
- New integration test file `Srv001TransactionalSubmissionTest` (17 tests / 86 assertions) exercising the real HTTP submit route.

البقايا=
- **RES-SG06-01** — status updated to **CLOSED** for SRV-001. Closure evidence:
  1. Real HTTP submit route uses `SubmitApplicationUseCase` — proven by audit action `application.target_submission_committed` in `test_submit_writes_target_submission_committed_audit_row`.
  2. Controller submit() body contains no direct `->save(` or `::create(` — proven by source-inspection test `test_controller_submit_has_no_direct_save_or_create_calls`.
  3. `Srv001Guard::validate` performs no save on the runtime path — legacy guard registry is skipped when a typed policy is registered, proven by `test_srv001_typed_policy_is_registered_and_takes_precedence` + `test_srv001_guard_direct_save_does_not_run_on_runtime_path` combined with the rejection zero-persistence invariant.
  4. All writes atomic in one DB transaction — proven by `test_use_case_failure_rolls_back_application_and_snapshots_atomically` (inject throwing audit recorder; assert application status, version binding, data merge, snapshots all revert).
  5. Integration tests exercise the real HTTP route — every TD-03 test dispatches through `postJson("/api/v1/applications/{id}/submit")`.

  Legacy guard registry keeps its `SRV-001 → Srv001Guard` entry intact (proven by `test_srv001_still_present_in_legacy_guard_registry_for_offline_use`); TD-03 is a routing change, not a de-registration. Callers outside the HTTP submission path (offline tooling, tests) can still invoke `Srv001Guard::validate` directly if they need to.

- **RES-TD02-SUPP-01** — unchanged status (OPEN). `IDEMPOTENCY_CONTRACT_STATUS=ABSENT` verified again by grep; the runtime rewire does not add a submission-scoped idempotency key. Deferred to API-contract resolution (still TD-03 scope per TD-02-SUPP judgment; not implemented in this commit because no signed contract exists).

- **New residual: RES-TD03-01** — the typed-decision dispatch uses `DB::transaction(function() { ... })` — a nested-transaction pattern via Laravel savepoints (Postgres `SAVEPOINT` / SQLite implicit). On production Postgres this is safe (savepoints roll back atomically with the outer transaction). This is called out here for TD-06 audit-completeness but is not a defect.

التعارض=
None. The extension-registry approach explicitly satisfies "no service-code conditional." Model A is signed. Wire-format preservation is signed. TargetSrv001SubmissionPolicy remains unwired.

الجمع=
Reconciled. The runtime now delivers the atomic guarantees TD-02/TD-02-SUPP proved in isolation. Legacy numeric outputs, rejection messages, and error wire format are preserved verbatim. Target runtime remains inactive.

الترجيح=
Tier-5 (runtime safety — atomicity) + Tier-4 (target architecture — extension surface) + Tier-3 (backward compatibility — legacy wire format, guard registry preservation) all support the chosen design. Evidence-integrity honesty is preserved: no target value activated, no idempotency contract invented.

التوقف=
STOPPED on:
- publishing any target-domain `RuleVersion`
- activating `TargetSrv001SubmissionPolicy` or any `Target*` calculator
- introducing a service-code branch into the controller or engine
- changing legacy numeric outputs or rejection messages
- inventing an idempotency contract
- pushing, tagging, merging, or deploying

Continues on: transactional runtime dispatch for services with a registered typed-decision policy (currently `SRV-001` only).

READINESS_CLASSIFICATION=Compliant with TD-03 mandate. RES-SG06-01 status: **CLOSED for SRV-001** (all five closure criteria proven).

IMPLEMENTATION_ACTION=Create `ServiceSubmissionPolicyRegistry` + `ServiceSubmissionRejected`. Register the registry in `JeaServicesServiceProvider` with a `SRV-001 => LegacySrv001SubmissionPolicy` entry + bind the three interface contracts. Extend `ApplicationController::submit` with typed-decision dispatch (skip legacy guard registry when typed policy exists; wrap decision + use case + workflow in one `DB::transaction`; catch `ServiceSubmissionRejected` in the outer scope and map to 422 with flattened errors). Update three feature tests to seed `Srv001RulesSeeder`. Invert one pre-TD-03 use-case invariant test to assert `TargetSrv001SubmissionPolicy` and `Target*` calculators remain unwired. Add `Srv001TransactionalSubmissionTest` (17 tests) exercising the real HTTP submit route.

CLOSURE_EVIDENCE=
- Focused TD-03 tests: **17/17 PASS / 86 assertions / 1967 ms** on SQLite
- All use-case suites (TD-02 + SUPP + TD-03): **36/36 PASS / 185 assertions / 12360 ms** on Postgres 15-alpine
- Focused TD-03 tests on Postgres 15-alpine: **17/17 PASS / 86 assertions / 10482 ms**
- Srv001EndToEndFlowTest on Postgres 15-alpine: **1/1 PASS / 33 assertions / 2031 ms**
- Owner + Cadastral guard tests on Postgres 15-alpine: **21/21 PASS / 42 assertions / 11171 ms**
- Governance suite on Postgres 15-alpine (SG-04 snapshots + legacy policy): **39/39 PASS / 88 assertions / 3022 ms**
- Unit suite: **309/309 PASS / 722 assertions** (unchanged)
- Feature suite: **721 passed / 728 total / 7 skipped / 2718 assertions** (+17 new TD-03 tests, +0 new skips)
- Architecture suite: **17/18 / 1 skipped / 55 assertions** (unchanged)
- PHPStan: **0 errors**
- Repository grep re-confirms no idempotency contract for application submission
- Postgres data integrity verified: only `migrations` table has rows (54 — unchanged from pre-test baseline); RefreshDatabase transactions rolled back cleanly
