JUDGMENT_ID=JDG-TD02-SUPP-01
TITLE=Audit persistence inside submission transaction + honest idempotency reconciliation
OWNER=TD-02-SUPP
PHASE=TD-02 (supplemental closure)

الوضع=
TD-02 (commit `272b9d6`) delivered `SubmitApplicationUseCase` composing SG-03 binding + SG-04 snapshot writes + data merge inside one `DB::transaction`. The new user directive (TD-02-SUPP) identifies three gaps against the full TD-02 mandate:

1. Audit-event persistence must occur inside the same submission transaction; any audit-write failure must roll back application + version binding + provenance + snapshots.
2. Idempotency behaviour must be reconciled honestly — if a contract exists, prove it; if absent, report `ABSENT` and record a residual, not invent one.
3. Any duplicate-attempt behaviour must leave zero partial persistence.

تحرير_محل_النزاع=
Should the audit call be:
(a) added to the use case as a static `AuditLog::record(...)` inside the transaction closure, or
(b) mediated by a small port so a test-double can inject a failure, proving rollback semantics on the audit step specifically?

And separately: does an application-submission idempotency contract exist in the codebase?

السبب=
User TD-02-SUPP directive lists both requirements explicitly. Without the audit port, the audit-failure rollback test cannot be built without monkey-patching the `AuditLog` facade. Without honest idempotency reconciliation, TD-02 would either understate the current state (by claiming idempotency works) or overstate it (by inventing a contract).

الشرط=
- Audit persistence must occur INSIDE `DB::transaction(...)` — same closure as data merge + binding + save + snapshots.
- Audit failure must roll back every prior write.
- Contract for idempotency must be verified via repository grep, not assumed.
- Duplicate-attempt behaviour must be tested regardless of contract presence (SG-04 unique-constraint invariant).
- No change to legacy runtime path. No new RuleVersion promotion.

المانع=
Adding the audit call via a static facade would prevent injecting a failure without brittle mock-facade hacks. Silently choosing an idempotency semantic (e.g., "return the first successful result on retry") would fabricate a contract not present in the API surface.

العلة=
Transactional integrity + honest source classification. Every audit event tied to the submission is worthless as evidence if it exists in `audit_logs` while the corresponding application state was rolled back (or vice-versa).

القادح=
Any implementation that:
- writes audit AFTER `DB::commit`
- fabricates an idempotency contract (e.g., adds an `IdempotencyKey` middleware without evidence of business acceptance)
- treats duplicate-attempt SQL failures as success

Would fire this قادح.

الصحة=
Valid implementation:

1. Small port `SubmissionAuditRecorderContract` in Governance/. One production implementation `SubmissionAuditRecorder` wrapping `AuditLog::record` with the fixed action `application.target_submission_committed` (distinct from legacy `application.submitted`).
2. Constructor injection into `SubmitApplicationUseCase` — 3-argument (binder + snapshot writer + audit recorder). Audit call is the 5th step INSIDE the transaction, referencing the version + snapshot ids + rule identifiers + derived-value keys + target-domain-provisional flag.
3. Audit-event id surfaced on `SubmitApplicationResult` (nullable — null on rejection/rollback, non-null on success).
4. Idempotency reconciled honestly: repository-wide grep proves no idempotency-key middleware, no `IdempotencyKey` table, no request-scoped idempotency guard for application submission. `IDEMPOTENCY_CONTRACT_STATUS=ABSENT` recorded as residual (RES-TD02-SUPP-01) for TD-03 API-contract resolution.
5. Duplicate-attempt behaviour tested via SG-04 unique constraint on `(application_id, rule_version_id, purpose='SUBMIT')` — second attempt fails atomically; zero additional snapshots, zero additional audit rows, zero partial writes.

الفساد=
Skipping the audit port and calling the facade directly would be fasid — repairable by extracting the port later, but blocks the audit-rollback test now.

البطلان=
Inventing an idempotency mechanism that consumes an unsanctioned key + returns the first successful result would be batil — no signed contract supports it; API consumers would depend on non-existent behaviour.

الأثر=
(1) Two new governance classes (contract + concrete recorder). (2) `SubmitApplicationUseCase` constructor gains a 3rd dependency; existing test's `setUp` + `test_version_binding_failure_rolls_back_application` updated (2 constructor call sites — smallest possible edit). (3) `SubmitApplicationResult` gains `auditEventId` field (nullable, backward-compatible default). (4) New test file `SubmitApplicationAuditAndIdempotencyTest` with 7 tests. (5) One `RES-TD02-SUPP-01` residual recorded for the absent idempotency contract.

البقايا=
- RES-TD02-SUPP-01 (OPEN): application-submission idempotency contract does not exist. Owner: TD-03 API-contract resolution. Blocks: retry-idempotent submission behaviour. Closure evidence: signed idempotency-key spec + middleware + tests OR explicit documented decision to leave submission non-idempotent.
- RES-SG06-01: unchanged status — direct-write runtime path (`Srv001Guard::validate` → `$app->save`) still in place. Use case remains unwired. Per user directive: "Do not close RES-SG06-01 unless the actual production submission route now passes through the transactional use case."

التعارض=
None between the audit-port approach and the mandate. The idempotency-absent classification honestly reports the current state without inventing a contract.

الجمع=
Reconciled — the use case now performs all four canonical writes atomically (data merge + version bind + snapshots + audit) inside one transaction; the honest idempotency classification directs the missing contract to TD-03 rather than silently substituting.

الترجيح=
Tier-4 (target architecture) + Tier-5 (runtime safety — atomicity) + evidence-integrity honesty (reporting `ABSENT`) all support the chosen design.

التوقف=
STOPPED on:
- runtime wiring for the use case
- inventing an idempotency contract
- closing RES-SG06-01

Continues on: audit port + tests + idempotency residual documentation.

READINESS_CLASSIFICATION=Compliant with TD-02-SUPP mandate. RES-SG06-01 status: PARTIALLY_REMEDIATED (use case exists with atomic audit; runtime path unchanged).

IMPLEMENTATION_ACTION=Create SubmissionAuditRecorderContract + SubmissionAuditRecorder. Extend use case to invoke inside transaction. Extend result with auditEventId. Add 7 new tests (audit-inside-tx, audit-failure rollback of application/version-binding/snapshots, rejected → no audit, duplicate → atomic rollback, idempotency-absent structural check). Update 2 constructor call sites in the existing test.

CLOSURE_EVIDENCE=
- 19/19 focused TD-02 tests pass on SQLite (12 original + 7 SUPP) / 92 assertions / 528 ms
- 26/26 (19 use-case + 7 concurrency) pass on real Postgres 15-alpine after `migrate:fresh` / 111 assertions / 11.9s
- Unit suite: 309/309 unchanged
- Feature suite: 704/711/7 skipped (+7 tests, +0 new skips)
- Architecture suite: 17/18/1 skipped unchanged
- PHPStan: 0 errors
- Repository grep confirms no idempotency contract for application submission
- Test `test_committed_result_writes_audit_event_inside_the_same_transaction` asserts audit row references version + snapshots + rule identifiers
- Tests `test_audit_persistence_failure_rolls_back_application` + `test_audit_failure_rolls_back_version_binding_specifically` + `test_audit_failure_rolls_back_snapshots_and_provenance_specifically` prove atomicity per side effect
