# TD-00 · Source Register

**Program:** `ESP_V2_SRV001_TARGET_DOMAIN_IMPLEMENTATION`
**Phase:** TD-00 (readiness + reconciliation, read-only)
**Baseline HEAD:** `5ab40e14e605ee27fdb728e2f7165b3a7b6ebc9d`

Every source consulted by this program, its precedence rank, its availability, its version, its approval status, its content-check status.

## Precedence table (per user directive + Ground Truth §1)

| Rank | Source | Path | Version | Availability | Content match to label | Approval status | Notes |
|---|---|---|---|---|---|---|---|
| 1 | Signed OD-Closure decisions | (none provided) | — | NONE_IN_REPO | N/A | N/A | User directive: "Do not manufacture UAT approval or resolve stopped JEA business decisions" |
| 2 | `JEA-ESP2-SRS-SITE-SURVEY-001` v1.1 (Reviewed) | (asserted at `srs/JEA_Site_Survey_SRS_ESP_v2_AR_v1.1_Reviewed.txt`) | v1.1 | **NOT_IN_REPO** — see JDG-TD00-01 | **MISMATCH — the file at the asserted path contains bash test log content from an unrelated project (Saleh/Qiyas), NOT SRS content** | UNVERIFIED — "Reviewed" ≠ APPROVED; formal approval status of v1.1 unknown even conceptually | Escalated as RES-TD00-01 |
| 3 | Site-survey process flowchart | `srs/flowchart.webp` | undated | AVAILABLE (webp image) | Match (Arabic process map for soil testing) | UNAPPROVED — supplementary source; user directive: "lower-precedence source" | Used only where higher-rank sources do not contradict |
| 4 | Draft analytical SRS | `srs/soil_testing_srs.md` | 0.1 draft | AVAILABLE | Match | **SUPERSEDED — HISTORICAL_ONLY** per Ground Truth §6 and user directive | Do NOT implement business rules from this document |
| — | Ground Truth reconciliation register | `srs/ground_truth_site_survey.md` | 0.1 (2026-08-01) | AVAILABLE | Match | DRAFT — labelled by user as "DRAFT Evidence Reconciliation Register" | **Primary authoritative source for THIS TD-00** per user directive; supersedes lower ranks; below rank 2 in the abstract precedence but currently the only accessible substitute for rank 2 |

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
