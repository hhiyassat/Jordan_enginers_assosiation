# Batch 01 — Coverage Report

**Task:** `JEA-TECHNICAL-MANUAL-2025-CANONICAL-PROVISION-SERVICE-MAP-01`
**Batch:** 01 — Chapter 3 + directly-referenced pages
**Date:** 2026-07-27
**Session:** first

---

## SERVICE_UNIVERSE

```
ACTUAL_JEA_SERVICE_COUNT:            57  (55 workbook services + CERT-006 + MSC-014)
WORKBOOK_SERVICE_ROWS:               55  (data rows in sheet 2 "كتالوج الخدمات الفعلي")
NON_SERVICE_ROWS (in live DB):
  - 7 parent categories             (JEA-CERT, JEA-DEC, JEA-ENG, JEA-FIN, JEA-MISC, JEA-PROJ, JEA-SURV)
  - 10 legacy demo services         (JEA-BP-001, JEA-CERT-001, JEA-COMP-001, JEA-LTR-001, JEA-MEM-001/002, JEA-OFF-001/002, JEA-SOC-001, JEA-SURV-001)
CANONICAL_SERVICE_CODES:              See 01_service_catalog.csv
CATALOG_DISCREPANCIES:
  - CERT-006 + MSC-014 in seeder+DB but not in workbook
  - 17 non-canonical rows still in live DB from disabled seeders
  - MSC-013: DELIBERATELY_ABSENT (constitutional fact preserved)
```

---

## MANUAL_BATCH_SCOPE

**Pages read + extracted this session:**
- Chapter 3 header (p.33) — verified chapter start
- Chapter 3 §1 pp.34-35 — plans-audit procedures
- Chapter 3 §2 p.35 — re-certification
- Chapter 3 §3 p.35 — sketches (`الكروكيات`)
- Chapter 3 §4 p.35 — Civil Defense preliminary approval
- Chapter 3 §5 p.36 — safety code approval
- Chapter 3 §6 pp.36-38 — SITE SURVEY (primary batch focus)
- Chapter 3 §7 pp.38-39 — excavation drawing
- Chapter 3 §8 p.40 — materials testing
- Chapter 3 §9 pp.40-42 — excavation protection
- Chapter 3 §10 p.43 — plan-audit locations
- Chapter 3 §11 p.44 — income tax declarations (out of primary SRV focus; extracted but minimally mapped)
- Chapter 3 §12 p.44 — sales tax (same treatment)
- Chapter 3 §13 p.44 — large and specialized projects
- Chapter 3 §14 p.44 — plan retention
- Chapter 3 §15 p.45 — ownership of documents and plans
- Chapter 3 §16 pp.45-47 — mosque building instructions (out of SRV/DRW-P scope; NOT extracted)
- p.48 — blank (end of chapter 3, verified)
- Chapter 7 pp.92-96 — engineering fee minimums + JEA syndicate fees
- Book 2 pp.219-222 — technical report minimum content
- Book 2 pp.230-232 — exploration matrix
- Book 2 pp.239-240 — existing-building investigation
- Book 2 pp.241-242 — appendix start (context only)

**Pages NOT extracted this session (future batches):**
- pp.1-32 (front matter + chapter 1-2)
- pp.35 mosque section §16 (extracted with minimal mapping; can be deepened in future batch)
- pp.49-91 (chapters 4-6)
- pp.93-95 (fee tables continuation — some content read but not fully extracted as atomic provisions)
- pp.97-218 (chapter 7 continuation + chapter 8-10 + book 2 structural section)
- pp.223-229, 233-238 (book 2 architectural/mechanical/electrical/materials specific sections)
- pp.241-311 (book 3 appendices — national building law, offices regulation, inspection regulation, safety, etc.)

---

## PROVISION_COUNTS

```
TOTAL_ATOMIC_PROVISIONS_EXTRACTED:    51
  CONFIRMED:                          46
  CONFIRMED_WITH_CONDITION:            3
  UNRESOLVED:                          0
  NEEDS_JEA_CONFIRMATION:              0  (but 14 open questions in 08_unresolved_questions.csv)
  REFERENCE_ONLY:                      2
  NOT_EXECUTABLE:                      0

EXECUTABLE_RULES_CONTRACTED:          17  (safe subset; see 06_executable_rule_contracts.csv)
  IMPLEMENTED_ALREADY:                 4  (RC-001 partial, RC-005, RC-006, RC-008, RC-014, RC-013 partial)
  NOT_IMPLEMENTED_READY:               7  (RC-002, RC-003, RC-004, RC-009, RC-010, RC-011, RC-015)
  NOT_IMPLEMENTED_BLOCKED:             6  (RC-007 blocked by UNQ-001; RC-016, RC-017 blocked by UNQ-002; RC-012 blocked by SRV-003 unbuilt)

MAPPINGS_IN_PROVISION_SERVICE_MATRIX: 62  (some provisions map to multiple services)
DISCREPANCIES_IDENTIFIED:             20
  CRITICAL:                            1  (DISC-001: 150 fils/lm vs 20 JOD/lm fee mis-interpretation)
  HIGH:                                4  (DISC-002, DISC-003, DISC-013 activation-gap, DISC-017 correctly-understood)
  MEDIUM:                              8
  LOW:                                 5
  VERIFIED_CORRECT:                    2

OPEN_QUESTIONS:                       14
  BLOCKING:                            4  (UNQ-001, UNQ-002, UNQ-003, UNQ-004)
  NON_BLOCKING:                       10
```

---

## SERVICE_COVERAGE (SRV-001..014 + directly-relevant DRW-P-*)

| service_code | direct_provisions | shared_provisions | conditional_provisions | unmapped_fields | unmapped_documents | unresolved_rules | high_risk_discrepancies |
|---|---:|---:|---:|---:|---:|---:|---|
| SRV-001 | 15 | 5 | 3 | 1 (existing_built_area for SRV-002 only) | 1 (licensed_surveyor_report NOT_PRESENT) | 3 | DISC-001 CRITICAL, DISC-002, DISC-003, DISC-004 |
| SRV-002 | 12 | 6 | 8 | ~10 (proposed_baseline, required_test_pit_count, required_borehole_count, actual_test_pit_count, actual_borehole_count, borehole_access_status, existing_building_case, floor_count derivation, floor_area adjustment, existing_built_area) | 7 (survey_contract, site_investigation_report, structural_study_nonconformance, depth_difference_contract, sensory_inspection_report, foundation_inspection_report, existing_structural_drawings, structural_test_report, structural_technical_report) | RC-016, RC-017 blocked by UNQ-002 | DISC-001 CRITICAL |
| SRV-003 | 5 | — | — | ALL (SRV-003 unbuilt) | ALL (deferred_execution_package, owner_undertaking, supervising_office_undertaking) | RC-012 blocked | DISC-013 HIGH (ACTIVATION_GAP) |
| SRV-004 | 3 | 2 | 1 | — | — | RC-015 (large-project classifier) | none critical |
| SRV-005 | — | 3 | — | — | — | — | UNQ-010 DEFERRED |
| SRV-006 | 2 | 6 | — | — | — | — | DISC-001 CRITICAL, DISC-005/006/007/008/009 MEDIUM |
| SRV-007 | 4 | 1 | — | — | 1 (materials_testing_contract cross-service) | RC-011 not implemented | DISC-018 MEDIUM |
| SRV-008 | 1 | — | — | ALL (unbuilt) | 1 | — | ACTIVATION_GAP |
| SRV-009 | 2 | — | — | ALL (unbuilt) | 1 | — | UNQ-004 boundary with SRV-002 |
| SRV-010 | — | 1 | — | ALL (unbuilt) | — | — | UNQ-003 boundary with SRV-002 |
| SRV-011 | — | — | — | ALL (unbuilt) | — | — | not-in-batch-01-focus |
| SRV-012 | 2 | 1 | — | — | 1 | RC-009 not implemented | UNQ-009 |
| SRV-013 | — | — | — | ALL (unbuilt) | — | — | not-in-batch-01-focus |
| SRV-014 | — | — | — | ALL (unbuilt) | — | — | not-in-batch-01-focus |
| DRW-P-001 | 12 | 3 | 3 (calculation_notes, structural_safety_study for existing, commercial_register) | — | — | — | DISC-014 MEDIUM, DISC-015 MEDIUM |
| DRW-P-002 | 8 | 4 | 3 | — | — | — | DISC-014, DISC-015 |
| DRW-P-003..012 | (each) 2-6 direct, 6+ shared | (bulk shared) | (as noted) | — | — | RC-015 for DRW-P-005 | DISC-014, DISC-015 |

---

## REPOSITORY_ALIGNMENT

### Existing correct mappings

- `DrawingFeeMatrixSeeder` (JORD-63) — page 92 fee matrix — **VERIFIED aligned**.
- `SolarFeeSeeder` (JORD-64-solar) — page 92 §1.solar — **VERIFIED aligned**.
- `ExcavationFeeSeeder` for SRV-007 3.5 JOD/m² — page 40 §9 — **VERIFIED aligned**.
- `FeeSurchargesSeeder` for 40 fils/m² on DRW-P-* — page 96 §4.أ.2 first half — **VERIFIED aligned**.
- `FeeSurchargesSeeder` for 1% syndicate on DRW-P-* + SRV-001..006 — page 96 §4.أ.1 — **VERIFIED aligned for those services**.
- `Srv001PilotSeeder` + `ManualReferenceLinksSeeder` linking JORD-89 (cadastral) to SRV-001 fields — page 34 §1.ي — **VERIFIED aligned**.
- `Srv001PilotSeeder` + JORD-85-srv (survey_contract) linked to SRV-001 — page 36 §6.ج — **VERIFIED for SRV-001 (UNDERGENERALIZED to only SRV-001, see DISC-005)**.
- `Srv001PilotSeeder` + JORD-86 (direct submission) — page 36 §6.و — **VERIFIED for SRV-001**.
- `Srv001PilotSeeder` + JORD-87 (signature+responsibility) — page 37 §6.ح — **VERIFIED for SRV-001**.
- `Srv001PilotSeeder` + JORD-88 (10-day retention) — page 37 §6.ز — **VERIFIED, but missing 2-day exception (DISC-008)**.
- `Srv001PilotSeeder` + JORD-90 (11-section technical content) — page 219 — **VERIFIED for SRV-001**.
- `Srv001PilotSeeder` + JORD-91 (exploration matrix) — pages 230-232 — **VERIFIED for the covered floor/area cells, partial coverage (DISC-003, DISC-004)**.
- Case A trigger correctness (SRV-002 Phase 0B audit correctly identified REPORT_DESIGN_NONCONFORMANCE, not exploration shortfall) — **VERIFIED**.

### Missing mappings

- JORD-85-srv / JORD-86 / JORD-87 / JORD-88 / JORD-90 not extended to SRV-002..006 (DISC-005..009).
- JORD-89 (cadastral) not extended to DRW-P-* schema fields (DISC-010).
- No JEA-approved-materials-office validation rule.
- No routing rule for plan-audit branch selection (DISC-015).
- No large-project auto-classifier (DISC-014).
- No SRV-003 activation (DISC-013).
- No SRV-008/009/010/011/013/014 activation.
- No 2000 JOD shoring deposit calculator.
- No 500 fils/m² capped-5000 audit committee fee calculator.
- No SRV-012 supervision fee (2.5 JOD/m² + salaries) formula.
- No sub-foundation depth adjustment (+1 lm per borehole) on the exploration matrix.
- No extra-borehole calculation for area >1200 m² (currently returns SPECIAL_STUDY when it should be CALCULATED).

### Wrong mappings

- **DISC-001 CRITICAL**: `SiteSurveyFeesSeeder` mis-interprets the 150 fils/lm fee on p.96 (which is a JEA syndicate practice fee) as THE site-survey office fee. The office minimum per p.93 §1.هـ is **20 JOD/lm**. Current fee schedule under-charges by ~130×.

### Invented behavior

- None identified in Batch 01. All existing repository rules trace to real provisions.

### Activation gaps

- SRV-003 (deferred survey) — service exists as row-only placeholder, no schema/workflow/guard. Provisions on pp.37-38 cannot be filed today.
- SRV-004, SRV-005, SRV-008, SRV-009, SRV-010, SRV-011, SRV-012, SRV-013, SRV-014 — all row-only.

---

## IMPLEMENTATION_READINESS

| service | readiness | reason |
|---|---|---|
| SRV-001 | **READY_WITH_DEFERRED_ITEMS** | Base pilot works. Deferred items: extend matrix per DISC-003/004, add floor_count derivation per DISC-002, resolve fee-rate conflict per DISC-001 before any real production use. |
| SRV-002 | **BLOCKED** | Blocked by UNQ-001 (fee rate), UNQ-002 (rounding), UNQ-003 (scope vs SRV-010), UNQ-004 (scope vs SRV-009). Cannot start Phase 1 implementation until these are resolved with JEA. |
| SRV-003 | **NOT_READY** | Activation gap. Requires dedicated pilot. Not in Batch 01 scope. |
| SRV-004 | **NOT_READY** | Large-project classifier not built. Row exists but no schema. |
| SRV-005 | **NOT_READY** | Needs chapter-5 provisions (renewable energy) not yet in a batch. |
| SRV-006 | **BLOCKED** | Blocked by DISC-001 (same fee-rate issue as SRV-001..005) + DISC-005..009 (shared provision extensions). |
| SRV-007 | **NOT_READY** | Requires deposit + audit-committee fee calculators + supervision workflow. |
| SRV-008 | **NOT_READY** | Row-only. No pilot. Materials-testing contract flow not implemented. |
| SRV-009 | **NOT_READY** | Row-only. UNQ-004 boundary vs SRV-002 pending. |
| SRV-010 | **BLOCKED** | UNQ-003 boundary vs SRV-002 pending. |
| SRV-011..014 | **NOT_READY** | Row-only. No Batch 01 scope activity. |
| DRW-P-001..012 | **READY_WITH_DEFERRED_ITEMS** | Base flows work. Deferred: routing rule (DISC-015), calculation_notes conditional trigger, large-project auto-classifier, extending JORD-89 to inner-page validation. |

---

## KNOWN_CASE_A_RESULT (§14 case verification)

**Trigger for page 38 §6.م (structural study + depth-difference contract)**:

```
CANONICAL_TRIGGER:  REPORT_DESIGN_NONCONFORMANCE
CANONICAL_SCOPE:    existing buildings only (§6.م explicitly says الأبنية القائمة)
CANONICAL_ACTOR:    reviewer (the nonconformance is a review-time finding after
                     comparing the site-survey report against the design drawings)
NOT_TRIGGERED_BY:   exploration-point shortfall (that is a separate rule on
                     p.38 §6.ل applying to PROPOSED buildings which mandates
                     drilling additional boreholes per code — a different remediation)
```

The SRV-002 Phase 0B audit already reached this same conclusion; this canonical extraction independently confirms it.

## KNOWN_CASE_B_RESULT (page 239 decomposition)

Six atomic provisions extracted:
- P239-001: baseline applicability — existing-building study mandatory
- P239-002: baseline is the proposed-building matrix
- P239-003: 2/3 rule for test pits (ROUNDING_UNRESOLVED)
- P239-004: 1/3 rule for boreholes + substitution when access blocked (ROUNDING_UNRESOLVED)
- P239-005: purpose of boreholes (REFERENCE_ONLY)
- P239-006: JEA site inspection (WORKFLOW_CONDITION — missing from existing SRV-002 workflow)

Plus §ب additional conditionals (P239-007 through P240-008) — 8 more provisions covering sensory inspection, foundation inspection, sub-foundation drilling, structural-element mapping, concrete strength, rebar sections, technical report, JEA submission, plan-matching, lab-source validation.

## KNOWN_CASE_C_RESULT (page 92 fee-matrix scope)

Page 92 §1.أ (design fees):
- **APPLIES TO**: DRW-P-001 through DRW-P-012 (drawing services with matrix fee).
- **NOT APPLIES TO**: SRV-001 through SRV-014 (survey services have separate fee on p.93 = 20 JOD/lm).
- **Distinguishes**: governorate (Amman vs rest) × building class (4 rows: green+commercial / private / residential C+D / popular-rural-agricultural-industrial) × building area × design vs supervision.

Existing DrawingFeeMatrixSeeder implements this correctly.

## KNOWN_CASE_D_RESULT (manual-guidance icon classification)

Manual references attached to SRV-001 fields via `manual_reference_id` are currently:
- **INFORMATIONAL** in all cases (the `ManualReferenceIcon` component displays the provision text; it does not enforce a validation).
- The frontend `DynamicForm.tsx` reads `field.manual_reference_id` and renders a `(?)` icon that fetches `/manual-references/{id}` on click — **read-only display**.
- **NONE** of the manual-reference icons currently trigger executable validation, calculation, or workflow behavior.

Executable behavior is separately owned by:
- `SchemaValidator` (field required + type + range + pattern)
- `Srv001Guard` (SRV-001-specific submit gates)
- `ExplorationRequirementMatrix` (matrix calculation)
- `FeeCalculator` (fee computation)

**Conclusion**: the `(?)` icons are purely informational. Any provision surfaced only via `manual_reference_id` on a field is REFERENCE_ONLY. Provisions requiring executable enforcement must ALSO be wired into the appropriate engine (validator, guard, matrix, calculator).

---

## Quality gates status

| gate | status | notes |
|---|---|---|
| Every provision has exact page | ✓ | all 51 provisions carry page_number |
| Every executable rule has direct evidence | ✓ | all 17 RC-* rows carry provision_id link |
| Every mapped service exists | ✓ | all mappings target service codes in 01_service_catalog.csv |
| Every formula has units | ✓ | RC-* rows include input_units |
| Every range has boundaries | ✓ | ROUNDING_UNRESOLVED marked explicitly where applicable |
| Every conditional document has trigger | ✓ | see 05_document_crosswalk.csv `trigger` column |
| Every reviewer-owned rule is not applicant input | ✓ | see `editable_by` / `applicant_or_reviewer_owned` |
| Every reference-only rule is not executable | ✓ | REFERENCE_ONLY excluded from 06_executable_rule_contracts.csv |
| No duplicated provisions | ✓ | provision_id unique |
| No MSC-013 mapped | ✓ | verified absent |
| No implementation files changed | ✓ | `git status` shows only new files under `docs/manual-canonicalization/2025/batch-01/` |
| No seeders changed | ✓ | none |
| No DB mutation | ✓ | none |
| No commit / tag / push | ✓ | none |

---

## BATCH_01_RECOMMENDATION

```
BATCH_01_RECOMMENDATION: NEEDS_CORRECTION
```

**Why NEEDS_CORRECTION and not READY_FOR_DUAL_REVIEW:**

1. **The 4 blocking open questions (UNQ-001..004)** must be resolved with JEA before any SRV-002 implementation can proceed. Answering these is a governance step, not a code change.

2. **DISC-001 (CRITICAL fee-rate mis-interpretation)** requires an immediate governance ruling: is the current 0.15 JOD/lm on SRV-001..006 the correct fee, or is the manual's 20 JOD/lm the correct fee? This is not an ambiguous rule — the manual clearly states both fees serve different purposes on different pages. But **changing the running fee from 0.15 to 20 JOD/lm is a 130× increase in customer-visible charge** and cannot be done without explicit product-owner + JEA authorization. Until this is resolved, no further SRV-001..006 pilot work should proceed.

3. **Batch 01 coverage is FOCUSED-PARTIAL**: chapter 3 SRV/DRW-P-critical sections are fully extracted, but §16 mosque provisions (pp.45-47) and some fee-continuation pages (pp.93-95 detail) were not extracted to atomic-provision granularity in this session. Any dual reviewer should confirm this scope is acceptable OR request a Batch-01B continuation covering those gaps.

**What is READY for dual review**:
- Service universe reconciliation (57 canonical + 17 non-canonical classified)
- The 51 atomic provisions with full 32-column detail
- The 62 provision-service mappings
- The DISC-001 finding (independently verified from primary manual text)
- Case A / B / C / D verifications
- The 17 executable rule contracts (safe subset with UNRESOLVED items explicitly marked)

**What needs the 4 blocking answers before proceeding**:
- Fee-rate correction path (UNQ-001)
- SRV-002 rounding rule (UNQ-002)
- SRV-002 vs SRV-010 boundary (UNQ-003)
- SRV-002 vs SRV-009 boundary (UNQ-004)

**Session-boundary discipline**: Batch 01B (future session) will cover the deferred sections listed under "Pages NOT extracted this session" above. No provision will be re-extracted from scratch unless a discrepancy is discovered during dual review.
