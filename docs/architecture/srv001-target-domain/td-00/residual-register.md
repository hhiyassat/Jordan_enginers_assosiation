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
| **RES-TD02-01** | TD-02 report | TD-03+ (runtime consumer) | MEDIUM | Runtime consumer of `SubmitApplicationUseCase` (either a new submission controller or a `WorkflowEngine::submit` refactor); when built, closes RES-SG06-01 | OPEN | Runtime consumer commit + integration test proving `Srv001Guard::validate`'s `$app->save()` is bypassed on the new path |
| **RES-TD02-02** | TD-02 report | TD-03+ (or provider-refactor phase) | LOW | Container binding `ApplicationVersionBinderContract → ApplicationVersionBinder` in `JeaServicesServiceProvider` (deferred — tests currently DI directly, no runtime consumer yet) | OPEN | One-line `->bind()` in provider when runtime consumer arrives |

## Explicit reaffirmation

**RES-SG06-01 remains OPEN.** TD-02 built the atomic submission use case as a callable-only orchestrator; runtime path unchanged. Per user TD-02 directive: *"Do not close unless the actual runtime direct-write path is replaced."* `Srv001Guard::validate` still calls `$app->save()` inside itself as the runtime path — that is the direct-write path referred to. It will remain the runtime path until the runtime consumer (RES-TD02-01) lands.

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
| RES-SG06-01 | Runtime swap from `Srv001Guard` to typed-decision consumer — TD-01+ natural work |
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
