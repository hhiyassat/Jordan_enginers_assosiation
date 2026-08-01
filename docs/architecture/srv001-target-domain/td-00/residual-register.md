# TD-00 · Residual Register (SRV-001 target-domain scope)

Residuals raised BY TD-00 (as opposed to inherited from SG-*/RC-*).

## TD-00-owned residuals

| RESIDUAL_ID | Raised by | Owner | Risk | Blocks | Status | Closure evidence |
|---|---|---|---|---|---|---|
| **RES-TD00-01** | JDG-TD00-01 | User | HIGH (for citation traceability) / LOW (for TD-00 continuation) | SRS §-level citation for any rule promotion | OPEN | User supplies actual SRS v1.1 body OR confirms Ground Truth is the authoritative citation source |
| **RES-TD00-02** | this register | JEA product | Depends on rule | Confirmation that Ground Truth §3 items marked SOURCE_CONFIRMED per SRS v1.1 are indeed BUSINESS_APPROVED at rule level (i.e., traceable OD-Closure IDs). Currently every SOURCE_CONFIRMED item is BUSINESS_APPROVAL_UNVERIFIED. | OPEN | Per-rule OD-Closure attached |
| **RES-TD00-03** | terminology-register | Code+docs+UI | LOW-MEDIUM | Enforcement that forbidden aliases (Disposal / إتلاف as workflow path / المكتب المؤلف / Sensory Inspection before excavation / etc.) do not appear anywhere in the repo | OPEN | Add architecture test grep-guard checking source + doc + seed + i18n JSON |
| **RES-TD00-04** | requirement-delta-matrix | TD-01+ | MEDIUM | Delta between current 7-state Application::STATUS_* and target 11-state chain per Ground Truth §3.3 requires state-machine extension | OPEN | TD-01 designs the extended state model + migration policy for LEGACY_UNVERSIONED applications on the old state chain |
| **RES-TD00-05** | requirement-delta-matrix | JEA product | HIGH | 15 rules currently AUTHORIZED for structural implementation but require simulation/PROVISIONAL numeric outputs — they must NEVER be published without OD-Closure | OPEN | Per-rule OD-Closure or UAT sign-off before publication |
| **RES-TD00-06** | business-rule-register | TD-01+ | LOW | `WellsCountCalculator` uses total floor_area (not per-floor max as BR-CALC-01 requires); need per-floor input model when target replaces legacy | OPEN | TD-01+ introduces per-floor collection input; legacy calculator preserved as-is |
| **RES-TD00-07** | source-register | JEA product | LOW | Verify `flowcahrt/تربة مقترح.drawio.pdf` and `تربة قائم.drawio.pdf` files exist and are accessible for future flowchart-source traceability | OPEN | File-existence check + reference table update |

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
