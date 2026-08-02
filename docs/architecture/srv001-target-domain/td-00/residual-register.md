# TD-00 · Residual Register (SRV-001 target-domain scope)

Residuals raised BY TD-00 (as opposed to inherited from SG-*/RC-*).

## TD-00-owned residuals

| RESIDUAL_ID | Raised by | Owner | Risk | Blocks | Status | Closure evidence |
|---|---|---|---|---|---|---|
| **RES-TD00-01** | JDG-TD00-01 | User | HIGH (for citation traceability) / LOW (for TD-00 continuation) | SRS §-level citation for any rule promotion | **CLOSED** | User supplied `srs/JEA-ESP2-SRS-SITE-SURVEY-001_v1.2.md` (v1.2, 350 lines). Content verified as authentic SRS body. Note: v1.2 supersedes v1.1 (per SRS §version log). Reconciliation: `TD-00-reconciliation-srs-v12.md`. See `JDG-TD00-03-srs-v12-reconciliation.md`. |
| **RES-TD00-01b** | JDG-TD00-03 | User | LOW | Approval status of SRS v1.2 body | OPEN | SRS v1.2 itself explicitly declares "غير معتمدة للتنفيذ النهائي حتى إغلاق الحاجبات وتوقيع خط الأساس 2.0" — every SOURCE_CONFIRMED rule remains BUSINESS_APPROVAL_UNVERIFIED until per-rule OD-Closure attached. Same effect as RES-TD00-02. |
| **RES-TD00-01c** | JDG-TD00-03 | JEA product | HIGH | Reconciliation of Ground Truth rank-4 (soil_testing_srs.md) statements against SRS v1.2 | CLOSED (v1.2 explicitly SUPERSEDES v1.0/v1.1/0.1 per version log; ground truth §6 SUPERSEDED status of soil_testing_srs.md preserved) | Version log |
| **RES-TD00-01d** | JDG-TD00-03 | (informational) | LOW | SRS v1.2 introduces 10 new FRs (FR-SS-081..090) and 2 new ODs (OD-34, OD-35) — TD-00 registers must reflect | CLOSED by this reconciliation commit | See requirement-delta-matrix.csv + open-decision-register.md deltas |
| **RES-TD00-02** | this register | JEA product | Depends on rule | Confirmation that Ground Truth §3 items marked SOURCE_CONFIRMED per SRS v1.1 are indeed BUSINESS_APPROVED at rule level (i.e., traceable OD-Closure IDs). Currently every SOURCE_CONFIRMED item is BUSINESS_APPROVAL_UNVERIFIED. | OPEN | Per-rule OD-Closure attached |
| **RES-TD00-03** | terminology-register | Code+docs+UI | LOW-MEDIUM | Enforcement that forbidden aliases (Disposal / إتلاف as workflow path / المكتب المؤلف / Sensory Inspection before excavation / etc.) do not appear anywhere in the repo | OPEN | Add architecture test grep-guard checking source + doc + seed + i18n JSON |
| **RES-TD00-04** | requirement-delta-matrix | TD-01+ | MEDIUM | Delta between current 7-state Application::STATUS_* and target 11-state chain per Ground Truth §3.3 requires state-machine extension | OPEN | TD-01 designs the extended state model + migration policy for LEGACY_UNVERSIONED applications on the old state chain |
| **RES-TD00-05** | requirement-delta-matrix | JEA product | HIGH | 15 rules currently AUTHORIZED for structural implementation but require simulation/PROVISIONAL numeric outputs — they must NEVER be published without OD-Closure | OPEN | Per-rule OD-Closure or UAT sign-off before publication |
| **RES-TD00-06** | business-rule-register | TD-01+ | LOW | `WellsCountCalculator` uses total floor_area (not per-floor max as BR-CALC-01 requires); need per-floor input model when target replaces legacy | OPEN | TD-01+ introduces per-floor collection input; legacy calculator preserved as-is |
| **RES-TD00-07** | source-register | JEA product | LOW | Verify `flowcahrt/تربة مقترح.drawio.pdf` and `تربة قائم.drawio.pdf` files exist and are accessible for future flowchart-source traceability | OPEN | File-existence check + reference table update |

## TD-01A-owned residuals

| RESIDUAL_ID | Raised by | Owner | Risk | Blocks | Status | Closure evidence |
|---|---|---|---|---|---|---|
| **RES-TD01A-01** | JDG-TD01A-01 | User / JEA | LOW (informational) | Every SOURCE_CONFIRMED rule per SRS v1.2 remains BUSINESS_APPROVAL_UNVERIFIED (SRS v1.2 self-declares non-contractual until baseline 2.0) | OPEN | Signed OD-Closure per rule OR baseline-2.0 signature by requirements owner. Identical semantics to RES-TD00-02 / RES-TD00-01b — recorded here for TD-01A traceability. |
| **RES-TD01A-02** | JDG-TD01A-02 | closed by TD-01A commit | HIGH (was) | Domain\\Srv001 layer directly imported Governance\\Srv001\\Legacy* — boundary violation | **CLOSED** | TD-01A refactored to port + adapter pair (Srv001{ExplorationMatrix,WellsCount,NetDepth}Rule ports in Domain\\Srv001\\Contracts\\ + LegacyBridge* adapters in Modules\\JeaServices\\Adapters\\Srv001\\). Architecture test `Srv001DomainBoundariesTest` enforces the boundary going forward. |
| **RES-TD01A-03** | this register | (post-target-canonical) | LOW | LegacyBridge* adapters continue delegating to Legacy* pilot calculators. When approved target rules land, adapters retire (or a native Domain rule replaces them). | OPEN | Approved target rule per calculator (linked to OD-Closures OD-07 / OD-11 / OD-19 / OD-20 / OD-22 / OD-23 / OD-DEPTH) |
| **RES-TD01A-04** | TD-01A report | (informational) | LOW | Two Governance-namespace imports remain in Domain\\Srv001 by design: `ServiceCalculationPolicy` / `ServiceCalculationResult` / `ServiceSubmissionPolicy` / `ServiceSubmissionDecision`. These are SG-05 contract interfaces + value objects — dependency-inversion-correct (Domain implements outer contracts). NOT boundary violations. | ACCEPTED_BY_DESIGN | Documented so future audits do not attempt to remove them |
| **RES-TD01A-05** | TD-01A report | TD-02+ (state machine) | MEDIUM | SRS v1.2 §19 release-allocation corrections: FR-SS-057..061 → R4 exclusively; FR-SS-062 → R5. Current 7-state Application machine does not yet distinguish R4/R5 stages. | OPEN | TD-02+ state-machine extension per RES-TD00-04, honouring SRS §19 R-allocation |
| **RES-TD01A-06** | TD-01A report | TD-02+ (entities) | MEDIUM | SRS v1.2 §10 introduces two new entities: `QuotaIncreaseReferral` (application + requested quota + fee + decision) and `InternalMandatoryNote` (scope=office|parcel + decision + session + effect=Block|warning). Not implemented. | OPEN | Two additive migrations + Eloquent models when TD-02+ requires; RBAC additions (SRS §13) accompany |
| **RES-TD01A-07** | TD-01A report | TD-02+ (RTM) | LOW | SRS v1.2 §17 RTM grouping corrections (fix GAP-05 attribution) — editorial correction against SG-* / TD-00 RTM references | OPEN | Emit an RTM cross-reference document at TD-N summary time |

## TD-02-owned residuals

| RESIDUAL_ID | Raised by | Owner | Risk | Blocks | Status | Closure evidence |
|---|---|---|---|---|---|---|
| **RES-TD02-01** | TD-02 report | TD-03+ (runtime consumer) | MEDIUM | Runtime consumer of `SubmitApplicationUseCase` (either a new submission controller or a `WorkflowEngine::submit` refactor); when built, closes RES-SG06-01 | **CLOSED** | TD-03 wires `ApplicationController::submit` through `ServiceSubmissionPolicyRegistry → LegacySrv001SubmissionPolicy → SubmitApplicationUseCase` inside one `DB::transaction` with `WorkflowEngine::submit` as the final in-transaction step (WORKFLOW_TRANSACTION_MODEL=A). 17 integration tests exercise the real HTTP submit route. |
| **RES-TD02-02** | TD-02 report | TD-03+ (or provider-refactor phase) | LOW | Container binding `ApplicationVersionBinderContract → ApplicationVersionBinder` + `SubmissionAuditRecorderContract → SubmissionAuditRecorder` in `JeaServicesServiceProvider` (deferred — tests currently DI directly, no runtime consumer yet) | **CLOSED** | TD-03 adds both bindings to `JeaServicesServiceProvider` alongside the new `ServiceSubmissionPolicyRegistry` singleton. |
| **RES-TD02-SUPP-01** | JDG-TD02-SUPP-01 | TD-03 (API-contract) | MEDIUM | Application-submission idempotency contract does NOT exist. Verified by repository-wide grep — no idempotency-key middleware, no `IdempotencyKey` table, no submission-scoped idempotency guard. Duplicate-attempt behaviour today: SG-04 unique constraint on `(application_id, rule_version_id, purpose='SUBMIT')` rolls back the second attempt atomically (`PARTIAL_PERSISTENCE=0`), but the caller receives a `rolledBack` result rather than the first-committed result. | OPEN | Signed idempotency-key spec + middleware + tests OR explicit documented decision to leave submission non-idempotent. TD-03 explicitly did NOT introduce a contract (per mandate `IDEMPOTENCY_CONTRACT_STATUS=ABSENT`). |

## TD-03-owned residuals

| RESIDUAL_ID | Raised by | Owner | Risk | Blocks | Status | Closure evidence |
|---|---|---|---|---|---|---|
| **RES-TD03-01** | JDG-TD03-01 | TD-06 (audit-completeness) | LOW (informational) | Typed-decision dispatch in `ApplicationController::submit` uses `DB::transaction(function() { ... })` around a use case that itself opens a nested `DB::transaction` (Laravel savepoint). On production Postgres this is safe (SAVEPOINT rolls back atomically with the outer transaction); on SQLite the same nesting is preserved via Laravel's driver-level savepoint emulation. | OPEN (informational) | TD-06 audit-completeness review confirms the nested-transaction pattern is acceptable, or refactors the use case to skip its inner transaction when a caller-managed outer transaction is active. |

## TD-09-owned residuals

| RESIDUAL_ID | Raised by | Owner | Risk | Blocks | Status | Closure evidence |
|---|---|---|---|---|---|---|
| **RES-TD09-01** | JDG-TD09-01 | JEA product | MEDIUM | RTM is comprehensive (90/90 FR-SS rows with valid dispositions) but dispositions have not been ratified by JEA product. | OPEN | JEA product signs off on each row's disposition. |
| **RES-TD09-02** | JDG-TD09-01 | JEA IT + product | HIGH | UAT-dedicated environment not provisioned. Test roles + permissions matrix documented but not assigned. | OPEN | Dedicated env + role assignments + published fixtures. |

## TD-08-owned residuals

| RESIDUAL_ID | Raised by | Owner | Risk | Blocks | Status | Closure evidence |
|---|---|---|---|---|---|---|
| **RES-TD08-01** | JDG-TD08-01 | JEA product + integration owners | HIGH | No financial rule is bound at runtime. Every FeeQuote/TaxQuote is constructed with a `FinancialRuleVersion` VO whose lifecycle prevents runtime selection. Closure requires signed OD-01/OD-10/OD-17/OD-19/OD-35 + published rule version + integration with a signed payment gateway contract. | OPEN | Signed ODs + published `FinancialRuleVersion` (lifecycle=PUBLISHED) + signed payment gateway contract + integration test. |
| **RES-TD08-02** | JDG-TD08-01 | Payment integration owner | HIGH | No production payment adapter wired. `PaymentCallbackReplayGuard` is in-memory; production requires a DB-backed store + unique constraint on `(payment_intent_id, callback_signature)`. | OPEN | Sandbox adapter + migration (unique constraint) + contract test + integration test. |
| **RES-TD08-03** | JDG-TD08-01 | TD-08+ (persistence) | MEDIUM | `ReceiptIssuanceRequest` is a VO. Persistence + rendering + serial-number allocation land when receipts + certificates go to production (chain: TD-06 storage + TD-07 certificate ports + TD-08 receipt VO). | OPEN | Additive migration + Eloquent model + persistence adapter + rendering adapter + serial allocation contract. |
| **RES-TD08-04** | JDG-TD08-01 | TD-08+ (workflow consumer) | MEDIUM | `FinancialCorrectionRequest` has no workflow consumer. The distinction between "pre-payment return via PartialEditGrant" and "post-payment correction via FinancialCorrectionRequest" is documented but not enforced at runtime. | OPEN | Controller / use case + integration test proving post-payment edits route through this VO. |

## TD-07-owned residuals

| RESIDUAL_ID | Raised by | Owner | Risk | Blocks | Status | Closure evidence |
|---|---|---|---|---|---|---|
| **RES-TD07-01** | JDG-TD07-01 | TD-07+ (workflow consumer) | LOW | `Srv001WorkflowGraph` + `WorkflowTransitionEvaluator` not container-bound; no runtime consumer. When wired, MUST fail-closed on `NOT_FOUND` / `BLOCKED_BY_OD` and MUST NOT be reachable by any existing controller until publication authorization lands. | OPEN | Container binding + runtime integration test proving BLOCKED_BY_OD short-circuits every downstream side effect. |
| **RES-TD07-02** | JDG-TD07-01 | TD-07+ (grant persistence) | MEDIUM | PartialEditGrant use cases have no repository / audit-writer dependencies. When persistence + audit land, `ConsumePartialEditGrantUseCase` MUST commit decide-then-persist in one transaction. | OPEN | Repository + audit writer + `DB::transaction` wrapper + integration test proving the two writes commit or roll back together. |
| **RES-TD07-03** | JDG-TD07-01 | External integration owners | HIGH | BURA + Map port adapters absent. Per-port closure requires signed integration contract + fake adapter promotion + contract test. | OPEN | Per-port: adapter + contract test. |
| **RES-TD07-04** | JDG-TD07-01 | Publication authority | HIGH | Certificate rendering + signing adapters absent. Production issuance remains BLOCKED. Closure requires publication authorization + production configuration + verified adapter (5-step control axis). | OPEN | Signed publication authorization + adapters + production configuration + production verification. |
| **RES-TD07-05** | JDG-TD07-01 | JEA product | HIGH | OD-18 special path resumption + reinforcement effects, OD-29 final action/state dictionary, OD-31/32/33/34 all unchanged. TD-07 preserves the block by NOT enumerating alternate paths. | OPEN | Per-OD signed closure. |

## TD-06-owned residuals

| RESIDUAL_ID | Raised by | Owner | Risk | Blocks | Status | Closure evidence |
|---|---|---|---|---|---|---|
| **RES-TD06-01** | JDG-TD06-01 | External integration owners | HIGH | No production storage / AV / quarantine adapter wired. Every port is interface-only. Per-provider closure requires the full 5-step control axis (CONTROL_MODELLED / ADAPTER_IMPLEMENTED / ADAPTER_TESTED / PRODUCTION_CONFIGURED / PRODUCTION_VERIFIED). | OPEN | Per-port: adapter + integration test + production config + production verification. |
| **RES-TD06-02** | JDG-TD06-01 | JEA product | HIGH | OD-24 unresolved. `AttachmentLimitPolicy` returns `CONFIGURATION_BLOCKED` for every category today. No file-upload UAT can complete until signed per-category limits publish. | OPEN | Signed OD-24 closure + limits registered via `AttachmentLimitPolicy::withPublishedLimit()` + integration test proving the limit is enforced at upload. |
| **RES-TD06-03** | JDG-TD06-01 | TD-06+ (partial-edit controller) | MEDIUM | `PartialEditGrantEnforcementPolicy` is a pure Domain function; no runtime consumer wired. A partial-edit controller endpoint must resolve the policy from the container and enforce it before any mutation. | OPEN | Controller + integration test proving edits outside grant scope + edits on legally-locked fields are rejected. |
| **RES-TD06-04** | JDG-TD06-01 | TD-06+ (persistence) | MEDIUM | `DocumentMetadata` + `PartialEditGrant` + `QuotaIncreaseReferral` + `InternalMandatoryNote` are VOs, not Eloquent models. When runtime consumers need persistence, additive migrations + models must land (VOs remain the payload shape). The existing `documents` table has some columns; a migration to align with the `DocumentMetadata` shape is out of TD-06 scope. | OPEN | Additive migrations + Eloquent models + persistence adapters. |

## TD-05-owned residuals

| RESIDUAL_ID | Raised by | Owner | Risk | Blocks | Status | Closure evidence |
|---|---|---|---|---|---|---|
| **RES-TD05-01** | JDG-TD05-01 | External integration owners | MEDIUM | No SRV-001 port has a real production adapter today. Every port has either an in-memory fake or a `ContractMissing*` default. Per-port closure requires: signed integration contract + adapter + contract test + observability. Blocking ODs per port: OD-30 (Oracle), OD-31 (DLS), (BURA / engineering ceiling / mandatory-notes / correction-status / quota / specialization / title-deed QR — per-provider). | OPEN | Per-port: signed integration contract + adapter registration + green contract test. |
| **RES-TD05-02** | JDG-TD05-01 | TD-05+ (submission pipeline extension) | LOW | `Srv001EligibilityGate` is not container-bound; `ApplicationController::submit` does not resolve it today. When a runtime consumer lands, the eligibility gate must be inserted BEFORE the typed-decision policy (SG-06 legacy or target) so that eligibility failures short-circuit before any calculation runs. | OPEN | Container binding + integration test proving the gate fires ahead of the calculation phase. |
| **RES-TD05-03** | JDG-TD05-01 | TD-05+ (persistence) | LOW | `QuotaIncreaseReferral` + `InternalMandatoryNote` are immutable VOs. When a runtime consumer needs to persist them, additive migrations + Eloquent models must land (VOs remain the payload shape). | OPEN | Migrations + models + persistence adapter. |

## TD-04-owned residuals

| RESIDUAL_ID | Raised by | Owner | Risk | Blocks | Status | Closure evidence |
|---|---|---|---|---|---|---|
| **RES-TD04-01** | JDG-TD04-01 | TD-05+ (target publisher) | LOW | `TargetRuleVersionPublicationPolicy` exists in source but is NOT bound in `JeaServicesServiceProvider` (intentional — no consumer today). When a `TargetRuleVersionPublisher` lands, it must (a) bind the policy, (b) consume it before every `APPROVED` transition. | OPEN | Container binding + publisher invocation + test proving the guard blocks a non-CALCULATED promotion attempt. |
| **RES-TD04-02** | JDG-TD04-01 | TD-05+ (calculator wiring) | LOW | `Srv001CalculatorOutcomeClassifier` runs today only in unit tests; no `Target*` calculator wraps its own `ServiceCalculationResult` through the classifier at runtime. When TD-05+ activates the target calculator path, either the calculators or the `TargetSrv001SubmissionPolicy` composition boundary must produce `Srv001TypedCalculationResult` and persist `srv001_calculation_outcome` in the `calculation_snapshots.intermediate_values` payload. | OPEN | Runtime classifier invocation + snapshot payload assertion. |

## Explicit reaffirmation

**RES-SG06-01 is CLOSED for SRV-001.** TD-03 wires the real production HTTP submit route through the transactional `SubmitApplicationUseCase`. `Srv001Guard::validate → $app->save` no longer runs on the runtime path (the legacy `ServiceSubmissionGuardRegistry` entry is skipped when a typed-decision policy is registered — which is the case for SRV-001). Closure evidence: five criteria proven, each by named integration test — see `../judgment-records/JDG-TD03-01-runtime-submission-integration.md` and `../td-03/TD-03-report.md`. `Srv001Guard.php` still exists and is still callable directly (offline tooling / non-HTTP tests) — that is intentional. RES-SG06-01 scope was closure of the runtime direct-write path, which is now delivered.

## Inherited residuals (foundation SG-* + RC-*)

Reference `docs/architecture/service-governance/service-governance-residual-register.md` for the full list. Summary of relevance to TD-01+:

| Inherited | Impact on target-domain |
|---|---|
| RES-SG00-02 | Blocks calculator PROMOTION to APPROVED; does not block structural build |
| RES-SG00-03 | Blocks per-service publication; does not block structural build |
| RES-SG01-01 | Legacy `status` column cleanup — future hygiene |
| RES-SG02-01 | Ops dashboard for AVAIL_LEGACY_STATUS_FALLBACK counter — observability |
| RES-SG02-02 | CLOSED by RC-02 |
| RES-SG03-01 | Extension-declaration snapshotting — post-canonical work |
| RES-SG03-02 / RES-SG03-03 | UX/ops follow-ups |
| RES-SG04-01 / RES-SG04-02 | Per-service onboarding pattern; manual recalc UX |
| RES-SG05-01 | 4 deferred contracts (Eligibility/StageAction/FeeStrategy/IntegrationContributor) — extract on 2nd consumer |
| RES-SG06-01 | Runtime swap from `Srv001Guard` to typed-decision consumer — **CLOSED by TD-03** (2026-08-01) |
| RES-SG00-04 | CLOSED |
| RES-SG01-02 | CLOSED |
| RES-SG00-01 | CLOSED |
| E2E-FLAKE-01 | Non-blocking test flake |

## Business-decisions inventory (STOPPED)

| DECISION_ID | Disputed rule | Missing authority | Safe work that continues | Blocks target-start? | Blocks rule implementation? | Blocks UAT? | Blocks publication? |
|---|---|---|---|---|---|---|---|
| BD-01 | SRV-001 provisional calculator values (WellsCount 801-1000 band; NetDepth third+two_thirds invariant; fee formula base) | JEA product | Structure/contract build under `Legacy*` and `Target*` parallel classes; simulation harness | NO | YES for the specific rule | YES for the specific rule | YES for the specific rule |
| BD-02 | Per-service UAT sign-off + publication_reason | JEA product | Every governance mechanism already in place (ServicePublicationPolicy + ServiceVersionPublisher); admin flow to trigger these is pending | NO | NO | YES | YES |

## Overall TD-00 residual verdict

* **7 TD-00-owned residuals** raised, all classified.
* **RES-TD00-01** is the highest-severity — but does NOT block TD-00 progression per the closure judgment (JDG-TD00-01).
* **Zero residuals block target-domain start (TD-01+).**
* **Every publication path is blocked** by at least one OD or business-decision-stopped item.
