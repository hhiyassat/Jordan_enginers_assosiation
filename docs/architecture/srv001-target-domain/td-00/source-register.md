# TD-00 · Source Register

**Program:** `ESP_V2_SRV001_TARGET_DOMAIN_IMPLEMENTATION`
**Phase:** TD-00 (readiness + reconciliation, read-only)
**Baseline HEAD:** `5ab40e14e605ee27fdb728e2f7165b3a7b6ebc9d`

Every source consulted by this program, its precedence rank, its availability, its version, its approval status, its content-check status.

## Precedence table (per user directive + Ground Truth §1)

| Rank | Source | Path | Version | Availability | Content match to label | Approval status | Notes |
|---|---|---|---|---|---|---|---|
| 1 | Signed OD-Closure decisions | (none provided) | — | NONE_IN_REPO | N/A | N/A | User directive: "Do not manufacture UAT approval or resolve stopped JEA business decisions" |
| 2 | `JEA-ESP2-SRS-SITE-SURVEY-001` **v1.2** | `srs/JEA-ESP2-SRS-SITE-SURVEY-001_v1.2.md` | **v1.2** (2026-08-01) supersedes v1.1, v1.0, v0.1 | **AVAILABLE** (350 lines / 40 KB, verified UTF-8 markdown) — closes RES-TD00-01 | Match — Arabic SRS body, JEA-ESP2-SRS-SITE-SURVEY-001, unified extended review draft | See "SRS v1.2 authority classification" table below | Content reconciled in `TD-00-reconciliation-srs-v12.md` + `td-01a/TD-01A-report.md` |
| 2-prev | `JEA-ESP2-SRS-SITE-SURVEY-001` v1.1 (Reviewed) — historic reference | (was asserted at `srs/JEA_Site_Survey_SRS_ESP_v2_AR_v1.1_Reviewed.txt` — file contains bash log from unrelated project, NOT SRS body) | v1.1 | NOT_IN_REPO — but explicitly superseded by v1.2 per SRS v1.2 version log | MISMATCH (original file) | SUPERSEDED by v1.2 | RES-TD00-01 CLOSED by user supplying v1.2 |
| 3 | Site-survey process flowchart | `srs/flowchart.webp` | undated | AVAILABLE (webp image) | Match (Arabic process map for soil testing) | UNAPPROVED — supplementary source; user directive: "lower-precedence source" | Used only where higher-rank sources do not contradict |
| 4 | Draft analytical SRS | `srs/soil_testing_srs.md` | 0.1 draft | AVAILABLE | Match | **SUPERSEDED — HISTORICAL_ONLY** per Ground Truth §6 and user directive | Do NOT implement business rules from this document |
| — | Ground Truth reconciliation register | `srs/ground_truth_site_survey.md` | 0.1 (2026-08-01) | AVAILABLE | Match | DRAFT — labelled by user as "DRAFT Evidence Reconciliation Register" | Rank-2-substitute during initial TD-00; after SRS v1.2 supplied, remains the reconciliation register but SRS v1.2 becomes the primary rank-2 citation source |

## SRS v1.2 authority classification (per JDG-TD01A-01)

Formal authority tiers of the SRS v1.2 baseline. Every dimension `NO` per user directive + SRS v1.2 §self-declaration.

| Dimension | Value |
|---|---|
| `SOURCE_BASELINE_STATUS` | `CURRENT_DRAFT_TARGET_BASELINE` |
| `DOCUMENT_STATUS` | `DRAFT_REVIEW` |
| `CONTRACTUAL_AUTHORITY` | `NO` |
| `BUSINESS_APPROVAL_AUTHORITY` | `NO` |
| `FINAL_IMPLEMENTATION_AUTHORITY` | `NO` |
| `PUBLICATION_AUTHORITY` | `NO` |
| `PRODUCTION_AUTHORITY` | `NO` |
| `REQUIRES_SIGNED_BASELINE_2_0` | `YES` |

**Do not infer authorisation from**: SOURCE_CONFIRMED, presence of a formula, presence of a requirement, the word "current", numeric parity with legacy code, or the word "Reviewed".

## Downstream sources cited by Ground Truth or by current code

| Source | Referenced by | Availability | Notes |
|---|---|---|---|
| `flowcahrt/تربة مقترح.drawio.pdf` | `SurveyWorkflowsSeeder::soilProposedWorkflow` | assumed exists in prior repo state | Only source cited for SRV-001 workflow layout — NOT verified during TD-00 |
| `flowcahrt/تربة قائم.drawio.pdf` | `SurveyWorkflowsSeeder::soilExistingWorkflow` | assumed exists | SRV-002 workflow — out of TD scope but noted |
| `كتاب التعليمات الفنية 2025 ص 34/36/37/230/231` | `Srv001PilotSeeder`, `ExplorationRequirementMatrix` | JEA reference manual — not in repo | JEA-signed technical reference; APPROVED source for the exploration matrix and cadastral field layout |
| `محضر اجتماع 2026-07-26` §X / §XI | `WellsCountCalculator`, `NetDepthTable` | not in repo | Meeting minutes — PROVISIONAL per SG-04; NOT JEA-signed |
| `docs/meetings/2026-07-26-jea-soil-testing.md` | `Srv001PilotSeeder` L253 | may exist in repo | Meeting notes source; not verified during TD-00 |

## Sources introduced by prior governance foundation (SG-* / RC-*)

| Source | Path | Status | Relevance |
|---|---|---|---|
| Governance decision ledger | `docs/architecture/service-governance/service-governance-decision-ledger.md` | AUTHORITATIVE (post-RC-07) | 14 JDG records + closure classifications |
| Residual register | `docs/architecture/service-governance/service-governance-residual-register.md` | AUTHORITATIVE | 12 open residuals; 4 CLOSED |
| Migration register | `docs/architecture/service-governance/migration-register.md` | AUTHORITATIVE | 4 migrations applied |
| Corrected maturity CSV | `docs/architecture/service-governance/SG-00-corrected-maturity-model.csv` | AUTHORITATIVE | Per-service classification |
| Foundation final verification | `docs/architecture/service-governance/final-verification-report.md` | AUTHORITATIVE (with RC-07 amendment) | Baseline for this program |

## What TD-00 uses vs cannot use

| For classifying | Uses | Cannot use |
|---|---|---|
| SOURCE_STATUS | Ground Truth §3 (CONFIRMED), §4 (CONFLICTED), §5 (GAPS), §6 (SUPERSEDED), §7 (BLOCKED) + flowchart + Srv001PilotSeeder for existing fields | SRS body citation (missing file) |
| BUSINESS_APPROVAL_STATUS | Ground Truth's SOURCE_CONFIRMED label PLUS explicit note that "SOURCE_CONFIRMED ≠ BUSINESS_APPROVED" (user directive); no rule promoted to APPROVED without an OD-Closure | Any rule for BUSINESS_APPROVED classification (no OD closures) |
| IMPLEMENTATION_AUTHORIZATION | Governance foundation (SG-05 + SG-06) allows implementation of typed-decision-contract policies; SG-04 allows CalculationSnapshot writes; RES-SG02-02 CLOSED means activation gate is wired | Any rule that requires OD closure or SRS body citation for its business meaning |
| PUBLICATION_AUTHORIZATION | Requires per-service `uat_status='APPROVED'` + `uat_reference` + `publication_reason` per ServicePublicationPolicy | Any rule pending JEA sign-off |

## RES-TD00-01 escalation

The mismatch of `JEA_Site_Survey_SRS_ESP_v2_AR_v1.1_Reviewed.txt` with SRS content is raised as `RES-TD00-01` in the TD-00 residual register. It does NOT block TD-00 continuation but does block any PROMOTION of individual rules to APPROVED that would need SRS §-level citation. See `judgment-records/JDG-TD00-01-srs-source-file-mislabelled.md`.
