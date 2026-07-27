# Methodology — JEA Technical Manual 2025 Canonical Provision→Service Map (Batch 01)

**Task ID:** `JEA-TECHNICAL-MANUAL-2025-CANONICAL-PROVISION-SERVICE-MAP-01`
**Batch:** 01 (chapter 3 + directly-referenced pages)
**Date:** 2026-07-27
**Author:** Claude Opus 4.7 (1M context)

---

## 1. Authoritative inputs (fixed at start of batch)

| Input | Path | Role | Priority |
|---|---|---|---|
| A. JEA 2025 Technical Manual | `docs/كتاب_التعليمات_الفنية_2025.pdf` (311 pages) | Primary legal / technical source | 1 |
| B. Field / Service Matrix | `docs/JEA_screen_field_service_matrix_v2_audited_RTL_fixed.xlsx` (4 sheets) | Service and field mapping source | 2 (superseded by A on conflict) |
| C. Repository service catalog | `backend/modules/JeaServices/Database/Seeders/ServicePlan2026Seeder.php` + live sqlite DB + workflow / fee seeders | Actual canonical service codes in production | 3 |
| D. Prior consumed-manual infrastructure | `ManualReferencesSeeder.php`, `ManualReferenceLinksSeeder.php`, `docs/manual-summary.md`, existing `JORD-*` tickets | Evidence of prior implementation — NOT authoritative | 4 |

Source-of-truth priority when they conflict: **A > C > B > D**. Existing repository mappings are treated as **evidence of prior implementation**, never as the correct interpretation of the manual.

---

## 2. Independent-pass discipline

Every provision is extracted from the manual **first**, without consulting any prior audit (Qiyas or otherwise), any prior JORD-* mapping, or the ChatGPT persona's previous conclusions. The prior reads earlier in this repository (SRV-001 pilot, SRV-002 Phase 0B audit) were performed in a different framing (per-service, forward-implementing) and their conclusions are cross-checked against — not adopted by — this canonical map.

The extraction order for each provision is fixed:

```
read source text
  → identify exact chapter / section / page
  → extract the smallest independently-applicable rule
  → identify: trigger / condition / effect / exception
  → identify whether it is executable
  → then (last step) determine which services it applies to
```

Any deviation from this order (e.g. "which service does this fit?" asked before the rule is fully decomposed) is treated as a methodology failure and re-done.

---

## 3. Service universe resolution — result

Reconciled from three sources:

| Source | Rows | Real services | Notes |
|---|---:|---:|---|
| Workbook sheet 2 (`كتالوج الخدمات الفعلي`) | 57 total (title + header + 55 data) | **55** | This sheet is the audited catalog; treated as the strongest signal for "what is a real electronic service in the current scope" |
| `ServicePlan2026Seeder::services()` | 58 code entries | **57** | 55 workbook services + `CERT-006` + `MSC-014` — both seeded but absent from workbook. `JEA-SURV` in this seeder is a parent registration, not a service. |
| Live sqlite DB `service_definitions` | 74 rows | 57 canonical + 7 parents + 10 legacy demo | Legacy rows come from `JeaServicesSeeder` (no longer in `DatabaseSeeder`'s ordered call list) but survived past re-seeds because seeder was previously wired. |

**Canonical service count for this batch = 57** (55 workbook + CERT-006 + MSC-014). The 7 parent categories (`JEA-CERT`, `JEA-DEC`, `JEA-ENG`, `JEA-FIN`, `JEA-MISC`, `JEA-PROJ`, `JEA-SURV`) and the 10 legacy demo services (`JEA-BP-001`, `JEA-CERT-001`, `JEA-COMP-001`, `JEA-LTR-001`, `JEA-MEM-001/002`, `JEA-OFF-001/002`, `JEA-SOC-001`, `JEA-SURV-001`) are documented in `01_service_catalog.csv` under `catalog_status = PARENT_CATEGORY_NOT_SERVICE` and `LEGACY_DEMO_NOT_CANONICAL` respectively. **They are excluded from all downstream mappings.**

`MSC-013` is documented as `DELIBERATELY_ABSENT` (constitutional fact from the SRV-001 pilot governance).

---

## 4. Batch 01 scope — pages

Chapter 3 of Book 1: **pages 33 through 48** (chapter 4 starts at page 49 per `docs/manual-summary.md` TOC).

Directly-referenced pages outside chapter 3 (per §15 of the task brief):

| Pages | Section | Reason for inclusion in Batch 01 |
|---|---|---|
| 92 | Book 1 · chapter 7 | Fee matrix for drawings — referenced by drawing-service provisions in ch3 |
| 96 | Book 1 · chapter 7 | Site-survey per-linear-meter fee — referenced by ch3 survey provisions |
| 219–222 | Book 2 · site-survey minimum-report requirements | Content requirements for the report deliverable of ch3 survey provisions |
| 230–232 | Book 2 · exploration matrix | The pp.230-231 exploration-borehole-count table + p.232 high-rise depths — referenced by ch3 §6 |
| 239–240 | Book 2 · existing-building investigation | Referenced by ch3 §6.م (existing-building nonconformance) |

Pages **NOT** covered in Batch 01: 1–32, 49–91, 93–95, 97–218, 223–229, 233–238, 241–311. These are for future batches.

---

## 5. Provision ID convention

Format: `JEA-TM2025-CH{chapter:02}-P{page:03}-{sequence:03}`

Example: `JEA-TM2025-CH03-P239-001`

Sequence starts at `001` per page and increments in reading order (top-of-page → bottom-of-page). One-based.

Where a provision straddles pages, the ID uses the **first** page and a note in `notes` records the continuation page(s).

`JORD-*` identifiers from the repository are **secondary reference identifiers only** and appear in the `repository_evidence` column of the crosswalk tables. They never appear as the primary `provision_id`.

---

## 6. Atomic-provision test

A provision is atomic when **removing any one of its parts materially changes the effect**. A paragraph that contains multiple parallel obligations (e.g. "attach contract, drill boreholes, retain samples 10 days") becomes **N separate provisions**.

Signs a provision is NOT yet atomic and should be split further:

- The Arabic connectors "و" / "أو" / "بالإضافة إلى" join independent rulings.
- The provision contains a conditional inside a general obligation ("... إلا في حالة ...") — the exception may be its own provision.
- Multiple actors are named (applicant + reviewer + JEA staff) with different obligations.

---

## 7. Interpretation-status legend

| Status | Meaning |
|---|---|
| `CONFIRMED` | The source text is unambiguous, all parts of the rule are present, and no external clarification is needed. |
| `CONFIRMED_WITH_CONDITION` | The rule is clear but its application depends on a defined condition. |
| `UNRESOLVED` | The source text is ambiguous OR incomplete (e.g. references "بالسعر المعتمد" without a fee schedule; specifies a fraction without a rounding rule). |
| `NEEDS_JEA_CONFIRMATION` | External clarification is required from JEA before the rule can be executed. Distinct from `UNRESOLVED`: the text is clear but there is a governance question (e.g. which of several enum values applies). |
| `REFERENCE_ONLY` | The provision is contextual / declarative and cannot be reduced to an executable check on any surface. Not a validation. Not a workflow. |
| `NOT_EXECUTABLE` | The provision describes a physical, procedural or human obligation that cannot be enforced by the electronic service — only surfaced as guidance. |

**A provision may only enter `06_executable_rule_contracts.csv` if its `interpretation_status` is `CONFIRMED` or `CONFIRMED_WITH_CONDITION`.** UNRESOLVED / NEEDS_JEA_CONFIRMATION / REFERENCE_ONLY / NOT_EXECUTABLE never enter the executable contracts file. This is enforced by construction.

---

## 8. Prohibited actions in this batch

Per the task brief §19:

- No implementation of SRV-002 or any other service.
- No modification of service schemas, workflow seeders, fee seeders, guards, calculators.
- No modification of `ManualReferencesSeeder` / `ManualReferenceLinksSeeder`.
- No mutation of the live database.
- No new `JORD-*` tickets created.
- No commit / tag / push.

Only the 10 documentation artifacts under `docs/manual-canonicalization/2025/batch-01/` are created.

---

## 9. Rounding-safety rule (applied strictly)

For any fraction, percentage or integer split (e.g. `⅔ × baseline` on p.239): the calculator emits the **rational value**. If the source text does not define a rounding method, the executable-rule contract records:

```
rounding_rule = ROUNDING_UNRESOLVED
```

and the final integer allocation is routed to `TECHNICAL_REVIEW_REQUIRED`. No CEILING / FLOOR / NEAREST / distribution-preserving choice is made without explicit textual evidence.

---

## 10. Crosswalk-vs-invention discipline

Fields, documents and executable rules only appear in the crosswalk files if:

- The provision that motivates them is `CONFIRMED` or `CONFIRMED_WITH_CONDITION`.
- The exact source text supports the field's role (input / derived / reviewer-owned).
- Existing repository fields are documented as `existing_repository_field` even when the crosswalk proposes changes.

Fields **derived from engineering intuition** (e.g. "SRV-001 should probably have a floor-count field") without direct manual textual evidence are marked `derived_from = NOT_IN_MANUAL_BATCH_01` and excluded from the executable-rule contracts.

---

## 11. Deterministic output ordering

- Provisions: sorted by `(chapter, page, paragraph_sequence)` ascending.
- Service mappings: sorted by `(service_code, provision_id)` ascending.
- Fields: sorted by `(service_code, matrix_row, field_key)` ascending.
- Documents: sorted by `(service_code, document_id)` ascending.
- Discrepancies: sorted by `(severity DESC, service_code, provision_id)`.

All CSVs are UTF-8 without BOM. One header row. Arabic preserved in-cell (no transliteration). No merged cells. No spreadsheet-dependent formulas. Line endings are LF.

---

## 12. Quality gates run before batch completion

Per §18 of the task brief, before declaring the batch complete:

1. Every provision has an exact page number.
2. Every executable rule has direct textual evidence.
3. Every mapped service code exists in `01_service_catalog.csv` with `catalog_status = CANONICAL_SERVICE` or `CANONICAL_SERVICE_SEEDER_ONLY`.
4. Every formula has explicit units.
5. Every numeric range has defined boundaries (or is marked `BOUNDARY_UNRESOLVED`).
6. Every conditional document has an explicit trigger provision.
7. Every reviewer-owned rule is `applicant_or_reviewer_owned = REVIEWER` and NOT surfaced as an applicant checkbox.
8. Every reference-only rule has `executable = NO`.
9. No source provision is duplicated in `02_manual_provisions.csv` (uniqueness = `provision_id`).
10. `MSC-013` does not appear as a mapped service anywhere.
11. `git status` shows no changes to code, seeders, migrations, tests, or the DB — only new files under `docs/manual-canonicalization/2025/batch-01/`.
12. No commit / tag / push occurred.

If any of these fail, the batch is `PARTIAL` or `BLOCKED` and the failure is documented in `09_coverage_report.md`.

---

## 13. Session-boundary discipline

If Batch 01 cannot be completed in a single session (probable given ~24 manual pages × ~10 atomic provisions/page = ~240 provisions × 30 columns), each session writes what it produced and updates `09_coverage_report.md` with:

- Pages read that session.
- Provisions extracted that session.
- Cumulative totals.
- Which quality gates are currently passing / failing.

The next session resumes at the next unread page. No provision is re-extracted from scratch unless a discrepancy is discovered.
