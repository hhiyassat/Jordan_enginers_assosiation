JUDGMENT_ID=JDG-TD05-01
TITLE=SRV-001 eligibility gates + external-system ports (fail-closed by construction; no production integration)
OWNER=TD-05
PHASE=TD-05 (Batch 3 · eligibility + external ports)

الوضع=
TD-03 (`792b0aa`) closed RES-SG06-01 for SRV-001 by wiring the transactional submission path. TD-04 (`0877865`) added typed calculator outcomes + publication guard + rule matrix. Both preserved `TARGET_RUNTIME_STATUS=INACTIVE`. TD-05 must build the boundary between SRV-001 domain logic and the external systems it eventually consults (office eligibility, quota, engineering ceiling, specialization suspension, correction status, Oracle decision, cadastral/DLS data, prior transactions, mandatory notes, quota-increase referrals, title-deed QR, etc.) — with **zero** production integration, **zero** production credentials, **zero** production endpoints, and a fail-closed default at every port.

تحرير_محل_النزاع=
1. **Where does fail-closed live?** In the aggregator (Srv001EligibilityGate), in each port implementation, or as a compile-time property of the outcome enum. If it's only in the aggregator, a new port bypasses the guard. If it's only in each port, a new outcome added tomorrow may inadvertently default to permissive.
2. **How does the audit envelope avoid leaking credentials / vendor payloads?** By schema (a fixed value-object shape), by validation (construction-time refusal of credential-shaped strings), or by convention (docstring + reviewer discipline).
3. **How much of FR-SS-081 / FR-SS-088 lands here?** Full Eloquent models + migrations, or immutable value objects with no persistence, or nothing at all (defer to later phases).

السبب=
The Batch 3 mandate makes fail-closed non-negotiable: "critical external uncertainty must fail closed"; "no external outage may silently become ELIGIBLE". It also forbids production integration ("no production endpoint activated"; "no production credentials"; "no production Oracle/DLS/BURA traffic") and forbids fabricated success responses. And it lists 12 typed decisions + 15+ port responsibilities + structural support for FR-SS-081/082/087/088/089 + two new entities (QuotaIncreaseReferral, InternalMandatoryNote).

الشرط=
- `TARGET_DOMAIN_DEPENDS_ON_HTTP=NO` — no `Illuminate\Http\*` import in `Domain/Srv001/`.
- `TARGET_DOMAIN_DEPENDS_ON_ELOQUENT=NO` — no `Modules\JeaServices\Models\*` import except the two documented crossings (`Application`, `RuleVersion`).
- `TARGET_DOMAIN_DEPENDS_ON_EXTERNAL_CLIENT=NO` — no `GuzzleHttp\*` (or equivalent vendor HTTP client) import in `Domain/Srv001/`.
- `TARGET_DOMAIN_DEPENDS_ON_VENDOR_PAYLOAD=NO` — vendor payloads translated to internal DTOs at the adapter boundary.
- `GENERIC_ENGINE_SRV001_SPECIFIC_BRANCHES=0` — no new `=== 'SRV-001'` conditional in generic controllers/engines.
- Every port MUST return a `Srv001PortDecision`; every decision MUST carry a `Srv001PortAuditEnvelope`.
- Every non-permissive outcome MUST block; only `ELIGIBLE` + `NOT_APPLICABLE` permit continuation.
- No production credentials, tokens, or endpoint URLs in source.
- No `RuleVersion` promoted. No new runtime consumer wired.
- `TARGET_RUNTIME_STATUS=INACTIVE` must hold.

المانع=
Registering ports as container singletons AND letting `ApplicationController::submit` resolve them would activate the eligibility pipeline in the runtime — a scope violation. Adding a live Oracle client would fire the `PRODUCTION_INTEGRATION_PERMITTED=NO` قادح. Making `permissiveOutcomes()` include an "unknown-yet-treat-as-ok" state would break fail-closed by construction.

العلة=
Runtime safety + evidence-integrity + supply-chain isolation. External systems ARE the highest-risk failure mode for a submission pipeline; failing open on any one of them is an authorisation defect that never surfaces until a real outage. Building the boundary with typed decisions + audit envelopes + fake adapters lets us prove the pipeline is safe BEFORE any real integration lands.

القادح=
Any implementation that:
- adds an `Illuminate\Http\*` import to `Domain/Srv001/`
- adds a vendor HTTP client import to `Domain/Srv001/`
- adds a production URL / credential / token to source
- makes `permissiveOutcomes()` return anything other than `[ELIGIBLE, NOT_APPLICABLE]`
- wires the eligibility gate into `ApplicationController::submit`
- registers a live Oracle / DLS / BURA adapter as the default binding
- introduces a new hardcoded `SRV-001` conditional in a generic controller/engine

Would fire this قادح.

الصحة=
Valid implementation:

1. **`Srv001EligibilityOutcome` enum** (Domain/Srv001/Contracts/) — 12 states covering the mandate's list. `permissiveOutcomes()` returns exactly `[ELIGIBLE, NOT_APPLICABLE]`; everything else is blocking by construction.
2. **`Srv001PortAuditEnvelope` value object** (Domain/Srv001/ValueObjects/) — carries correlationId, providerId, sourceKind, responseClassification, timestamp, sourceStatus, blockingOd, reasonCodes. Construction-time guard rejects credential-shaped strings in `reasonCodes`. Fixed-shape `toAuditExtras()` returns the same field set every time.
3. **`Srv001PortDecision` value object** (Domain/Srv001/ValueObjects/) — immutable pairing of outcome + envelope + payload. `isPermissive()` reads from `Srv001EligibilityOutcome::permissiveOutcomes()` — a new outcome added tomorrow defaults to blocking. Provides `eligible()`, `ineligible()`, `externalUnavailable()`, `contractMissing()` factory methods.
4. **10 port interfaces** in `Domain/Srv001/Contracts/`:
   - `OfficeEligibilityPort`
   - `OfficeQuotaPort`
   - `SpecializationEligibilityPort`
   - `EngineeringCeilingPort`
   - `OfficeCorrectionStatusPort`
   - `OracleDecisionPort`
   - `PriorTransactionPort`
   - `MandatoryNotesPort`
   - `QuotaIncreaseReferralPort`
   - `TitleDeedQrPort`
5. **3 fake adapters** in `Adapters/Srv001/`:
   - `InMemoryOfficeEligibilityAdapter` — deterministic override table; fails closed with `CONTRACT_MISSING` when no override is set.
   - `InMemoryOfficeQuotaAdapter` — remaining-m2 lookup; returns `QUOTA_EXHAUSTED` when the request exceeds remaining; `CONTRACT_MISSING` when the org has no recorded remaining.
   - `ContractMissingOracleDecisionAdapter` — returns `CONTRACT_MISSING` on every call (OD-30 blocker). Registered as the DEFAULT Oracle-port binding so any accidental runtime invocation fails closed.
6. **`Srv001EligibilityGate` aggregator** (Domain/Srv001/) — composes the five office-level ports (eligibility → correction → notes → quota → ceiling) in a signed priority order. First blocking decision wins. Preserves the full audit trail. Not wired to runtime.
7. **`QuotaIncreaseReferral` + `InternalMandatoryNote` value objects** (Domain/Srv001/ValueObjects/) — structural support for FR-SS-081 + FR-SS-088. Immutable; no migration. Runtime consumer arrives in a later phase.
8. **Architecture test `Srv001PortBoundariesTest`** enforces the four boundary invariants above (no Illuminate\Http, no unlisted Models, no Guzzle, no hardcoded SRV-001 conditional in the controller, no production URLs).

الفساد=
Registering the eligibility gate as a container singleton with the fake adapters as defaults would be fasid — the gate would resolve at runtime with fake data as if it were real. Repairable by not binding it; not done in TD-05.

البطلان=
Wiring a live Oracle client with hardcoded credentials would be batil — every subsequent decision would leak the credential + fake a passing test. Not attempted.

الأثر=
- 13 new source files (1 enum + 2 VOs + 2 entity VOs + 10 ports + 1 aggregator, minus the two entities that are VOs = actually: enum + 2 VOs + 10 ports + 1 aggregator + 2 entity VOs + 3 adapters = 19 new source files).
- 4 new test files (33 focused tests total, all pass).
- 0 modifications to existing source files.
- 0 new migrations.
- 0 changes to seeders.
- 0 changes to controllers.
- 0 changes to `JeaServicesServiceProvider` (no container bindings added — the eligibility gate is NOT registered).

البقايا=
- **RES-TD05-01** (OPEN) — no adapter is wired to a real external system. Every port has one or more in-memory fakes (or a `ContractMissingXAdapter`). Real adapters land per-provider when integration contracts sign: Oracle (OD-30), DLS (OD-31), BURA, engineering ceiling backing store, mandatory-notes backing store, correction-status backing store, quota backing store, specialization suspension backing store, title-deed QR client.
- **RES-TD05-02** (OPEN) — `Srv001EligibilityGate` is not container-bound. When TD-05+ wires the gate at the controller boundary, the container binding + a runtime integration test must land together.
- **RES-TD05-03** (OPEN) — `QuotaIncreaseReferral` + `InternalMandatoryNote` are VOs, not Eloquent models. When TD-05+ needs to persist them, additive migrations + models must be added (structural VOs remain the payload shape).

التعارض=
None. Every prohibition is honoured: no production integration, no runtime activation of the gate, no fabricated positive responses, no service-code conditional in generic engines.

الجمع=
Reconciled. TD-05 delivers the fail-closed boundary between SRV-001 domain and its external systems as pure structural code, with typed decisions, audit envelopes, deterministic fakes, and architecture tests that enforce the boundary going forward.

الترجيح=
Tier-5 (runtime safety — fail-closed authorisation) + Tier-4 (target architecture — external-boundary structure) + Tier-3 (supply-chain isolation — no vendor payloads in Domain) all support the chosen design.

التوقف=
STOPPED on:
- promoting any target `RuleVersion`
- wiring the eligibility gate into any current runtime path
- activating any external integration
- adding production credentials / URLs
- pushing, tagging, merging, or deploying

Continues on: honest architectural build for the SRV-001 external boundary (12-state outcome enum + audit envelope + typed decisions + 10 ports + 3 fake adapters + aggregator + 2 structural VOs).

READINESS_CLASSIFICATION=Compliant with TD-05 mandate. TARGET_RUNTIME_STATUS=INACTIVE maintained. External integration status: PRODUCTION_INTEGRATIONS_ACTIVE=NO across every port.

CLOSURE_EVIDENCE=
- Focused TD-05 tests: **33/33 PASS / 874 assertions / 44 ms** on SQLite
- Domain + Governance + Architecture on Postgres 15-alpine: **126/126 PASS / 1130 assertions / 4560 ms**
- Unit suite: **344/344 PASS / 849 assertions** (+28 vs TD-04 baseline)
- Feature suite: **738/745 / 7 skipped / 2747 assertions** (unchanged — TD-05 is pure Unit / Architecture)
- Architecture suite: **22/23 / 1 skipped / 852 assertions** (+5 vs TD-04)
- PHPStan: **0 errors**
- Postgres data integrity: only `migrations` populated (54 rows, unchanged)
