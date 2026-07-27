# Batch 01 Addendum — Cross-Cutting Stakeholder Validations

**Date received:** 2026-07-27 10:10-10:11 AM
**Source:** Abdullah Abu Haiba (عبدالله ابو هيبة) — WhatsApp business-rules input
**Provenance:** STAKEHOLDER_INPUT (NOT extracted from the 2025 technical manual pages read in Batch 01)
**Applies to:** ALL 55 canonical services (or a defined subset per §4 below), evaluated at submit-time BEFORE any per-service rule

---

## Why this is an addendum, not part of `02_manual_provisions.csv`

The manual pages extracted in Batch 01 (chapter 3 pp.33-48 + chapter 7 pp.92-96 + book 2 pp.219-222/230-232/239-240) do **not** contain textual grounding for these four rules. They are:

- **Operational validation gates** established by JEA business practice, or
- Codified in unread sections of the manual (chapters 4 / 8-9 / book 2 other sections), or
- New proposed rules the stakeholder is requesting be enforced.

Provenance discipline (methodology §1 source-priority rule) prevents me from silently merging them with manual-derived provisions. If later manual reading finds textual grounding for any of these, the provision moves into `02_manual_provisions.csv` and its provenance is upgraded.

Two directly-related codebase touchpoints DO exist and are noted per rule:
- Cadastral fields (basin_number, parcel_number, basin_or_location_name, contract_owner_name) — present on SRV-001 schema via Srv001PilotSeeder.
- Quota infrastructure — CapacityGuard + QuotaLedger + EngineerDisciplineQuota + OfficeCeiling — implements yearly quota checks for area_m2 on drawing services but does NOT implement per-office cadastral-conflict or clearance-required workflows.

---

## Verbatim source (Abdullah's WhatsApp messages)

> محددات المجموعة الأولى لمنع التعارض: الفحص التلقائي الثلاثي (رقم القطعة + رقم الحوض + اسم الحوض). يمنع التقديم فوراً إذا كانت المعاملة سُجلت سابقاً لمكتب آخر.
>
> شرط اسم المالك والمخالصة: يُستخدم اسم المالك كبند إضافي؛ وعند تطابق (القطعة + الحوض + اسم الحوض + اسم المالك)، يلزم النظام بإرفاق مخالصة وبراءة ذمة رسمية من المكتب الهندسي السابق.
>
> الكوتا والسقوف الهندسية: رفض التقديم وتوجيه الطلب لقسم المكاتب عند تجاوز الكوتا، مع طلب API استعلام كوتا وAPI تعديل رصيد كوتا.
>
> نقاط التحقق والملاحظات: ملاحظات تحول المعاملة لقسم المكاتب، وملاحظات تنبيهية تسمح بمرور المعاملة مع إظهارها لـ "المدقق الثاني" الحصري في اتخاذ الإجراء.
>
> هاي التحققات لازم لكل خدمة بحيث لو افتقدلها المكتب ممكن تتحول لقسم المكاتب او المعاملة ما تنقبل من

---

## Atomic decomposition — 4 cross-cutting provisions

Each provision uses the same 32-column shape as `02_manual_provisions.csv`. The `provision_id` uses a distinct prefix (`STK-2026-07-27-CC-{seq}`) so it never collides with manual provisions.

### STK-2026-07-27-CC-001 — Cadastral triple-check conflict prevention

| field | value |
|---|---|
| provision_id | STK-2026-07-27-CC-001 |
| source | Abdullah Abu Haiba WhatsApp 2026-07-27 10:10 AM |
| chapter/page | n/a (stakeholder input) |
| source_text_ar | الفحص التلقائي الثلاثي (رقم القطعة + رقم الحوض + اسم الحوض). يمنع التقديم فوراً إذا كانت المعاملة سُجلت سابقاً لمكتب آخر |
| normalized_rule_ar | عند تقديم معاملة بحقول (basin_number, parcel_number, basin_or_location_name) المطابقة تماماً لمعاملة أخرى نشطة/سابقة لمكتب مختلف: يُرفض التقديم فوراً |
| rule_subject | platform / submit-time cadastral duplicate check |
| trigger | applicant submits an application with cadastral triple filled |
| preconditions | (basin_number, parcel_number, basin_or_location_name) all present in submitted data |
| required_conditions | no active/previously-registered application exists for a different office with the same triple |
| prohibitions | submission when triple matches another office's prior transaction |
| exceptions | none stated (needs clarification whether "same office" matches count) |
| effect | REJECT submission at submit endpoint with clear reason |
| responsible_actor | platform / SchemaValidator-like gate (new: PreSubmitCadastralConflictGuard) |
| affected_actor | applicant office (rejected) + potentially the prior office (informational) |
| unit | n/a — boolean gate |
| formula | `conflict = exists(Application where basin_number=X and parcel_number=Y and basin_or_location_name=Z and organization_id!=current_org_id and status not in ['draft','rejected','cancelled'])` — assumed status-set NEEDS_CONFIRMATION |
| rounding_rule | n/a |
| evidence_level | STAKEHOLDER_INPUT (no manual page yet) |
| interpretation_status | CONFIRMED (rule is clear) but NEEDS_JEA_CONFIRMATION on which application statuses count as "previously registered" and whether name normalization applies |
| classification | VALIDATION_RULE + PROHIBITION |

### STK-2026-07-27-CC-002 — Owner-match → clearance-and-discharge required

| field | value |
|---|---|
| provision_id | STK-2026-07-27-CC-002 |
| source | Abdullah Abu Haiba WhatsApp 2026-07-27 10:10 AM |
| chapter/page | n/a |
| source_text_ar | يُستخدم اسم المالك كبند إضافي؛ وعند تطابق (القطعة + الحوض + اسم الحوض + اسم المالك)، يلزم النظام بإرفاق مخالصة وبراءة ذمة رسمية من المكتب الهندسي السابق |
| normalized_rule_ar | عند تطابق الرباعية (basin_number, parcel_number, basin_or_location_name, contract_owner_name) مع معاملة مكتب سابق: يُطلب إرفاق مخالصة + براءة ذمة رسمية من المكتب السابق قبل قبول التقديم |
| rule_subject | platform / owner-match conditional document requirement |
| trigger | STK-2026-07-27-CC-001 triple matches AND `contract_owner_name` also matches a prior application |
| preconditions | cadastral triple + owner_name all present |
| required_conditions | attachment of BOTH: مخالصة (clearance) + براءة ذمة رسمية (official discharge) from the previous engineering office |
| exceptions | none stated |
| alternative_path | withdraw application; or resolve conflict outside platform |
| effect | REQUIRE two conditional documents before submission is accepted; do NOT reject outright |
| responsible_actor | platform (surfaces requirement) + previous office (must issue the documents) + applicant (uploads them) |
| affected_actor | applicant office |
| formula | `owner_match = exists(Application where triple_matches and contract_owner_name=W and organization_id!=current_org_id)` |
| rounding_rule | n/a |
| interpretation_status | CONFIRMED as a policy, but NEEDS_JEA_CONFIRMATION on: (a) is owner_name matched as exact string or normalized (Arabic diacritics, spacing, honorifics)? (b) which application statuses trigger this? (c) does the previous office issue via the platform or off-platform? |
| classification | CONDITIONAL_DOCUMENT + VALIDATION_RULE |

### STK-2026-07-27-CC-003 — Quota over-limit → route to Offices Department + API integration

| field | value |
|---|---|
| provision_id | STK-2026-07-27-CC-003 |
| source | Abdullah Abu Haiba WhatsApp 2026-07-27 10:10 AM |
| chapter/page | n/a — plausibly grounded in chapter 9 pp.123-131 (quotas), unread in Batch 01 |
| source_text_ar | الكوتا والسقوف الهندسية: رفض التقديم وتوجيه الطلب لقسم المكاتب عند تجاوز الكوتا، مع طلب API استعلام كوتا وAPI تعديل رصيد كوتا |
| normalized_rule_ar | عند تجاوز حصة/سقف المكتب: يُرفض التقديم للمكتب ويُحوَّل الطلب لقسم المكاتب. النظام يستهلك: API استعلام كوتا + API تعديل رصيد الكوتا |
| rule_subject | quota-guard + workflow-routing to Offices Department + API surface |
| trigger | applicant submits, submission's quota-consuming input (area_m2 / length_lm / capacity_kw) would push office over its yearly ceiling |
| required_conditions | office's remaining quota >= submission's consumption OR routed to Offices Dept for override |
| effect | REJECT applicant submission at submit endpoint + create a workflow item routed to `قسم المكاتب` (Offices Department) with the details |
| responsible_actor | platform (existing CapacityGuard + QuotaLedger) + Offices Department (human reviewer) |
| APIs required | (a) `GET /quota?office_id&year&discipline` — read remaining balance; (b) `PATCH /quota/{office}/{year}/{discipline}` — apply override / manual credit |
| interpretation_status | CONFIRMED as concept. NEEDS_JEA_CONFIRMATION on: (a) is Offices Department a role in the workflow or an external system? (b) what's the SLA for Offices Dept decision? (c) API auth — who is authorized to modify quota balance? |
| classification | VALIDATION_RULE + WORKFLOW_TRANSITION + ROUTING_RULE |

### STK-2026-07-27-CC-004 — Two-tier notes: blocking vs advisory

| field | value |
|---|---|
| provision_id | STK-2026-07-27-CC-004 |
| source | Abdullah Abu Haiba WhatsApp 2026-07-27 10:10 AM |
| chapter/page | n/a |
| source_text_ar | نقاط التحقق والملاحظات: ملاحظات تحول المعاملة لقسم المكاتب، وملاحظات تنبيهية تسمح بمرور المعاملة مع إظهارها لـ "المدقق الثاني" الحصري في اتخاذ الإجراء |
| normalized_rule_ar | نوعان من الملاحظات: (أ) ملاحظات حاجبة تحول المعاملة إلى قسم المكاتب؛ (ب) ملاحظات تنبيهية تسمح بالمرور مع إظهارها للمدقق الثاني الذي له صلاحية حصرية في اتخاذ الإجراء |
| rule_subject | validation-result taxonomy for downstream workflow routing |
| trigger | each per-service validation rule produces a result; result carries a severity tag |
| required_conditions | validators emit typed results: `BLOCKING → route to Offices Dept`, `ADVISORY → pass but surface to Second Auditor` |
| effect | BLOCKING notes stop the applicant flow and route to Offices Dept; ADVISORY notes let the application proceed but are pinned to the Second Auditor's review UI with exclusive-action semantics |
| responsible_actor | platform (validator infrastructure) + Second Auditor (exclusive action right) + Offices Department (for blocking) |
| interpretation_status | CONFIRMED taxonomy. NEEDS_JEA_CONFIRMATION on: (a) is Second Auditor role identical to existing `second_auditor_review` stage in SurveyWorkflowsSeeder? (b) can advisory notes be dismissed by the Second Auditor, or are they permanent to the audit trail? (c) can First Auditor add notes of either type, or is this Second-Auditor-exclusive? |
| classification | WORKFLOW_TRANSITION + REVIEWER_FINDING taxonomy |

---

## Cross-service applicability

Abdullah explicitly stated: **"هاي التحققات لازم لكل خدمة"** (these validations are required for every service). Interpretation:

| provision | applies to |
|---|---|
| STK-2026-07-27-CC-001 (cadastral triple check) | Every service with cadastral fields — SRV-001..006, SRV-008..014 if they carry basin_number/parcel_number, DRW-P-001..012 (if we extend JORD-89 per DISC-010). Does NOT apply to services without cadastral fields — CERT-*, DEC-*, ENG-*, FIN-*, MSC-*. |
| STK-2026-07-27-CC-002 (owner-match clearance) | Same set as CC-001, PLUS requires `contract_owner_name` field. Currently only SRV-001 has this. |
| STK-2026-07-27-CC-003 (quota over-limit) | Every service with a quota-consuming input: DRW-P-* (area_m2), SRV-001..006 (length_lm), SRV-006 (length_lm government), SRV-008/009 (area_m2 materials), SRV-007/012 (area_m2 excavation), DRW-P-006 (capacity_kw solar). Does NOT apply to CERT-*, DEC-*, ENG-*, MSC-* which have no quota input. |
| STK-2026-07-27-CC-004 (notes taxonomy) | Every service — universal cross-cutting concern of the workflow engine. |

---

## Repository discrepancies (append to `07_repository_discrepancies.csv`)

| discrepancy_id | provision_id | service_code | repository_location | existing_behavior | canonical_behavior | discrepancy_type | severity | blocking | status |
|---|---|---|---|---|---|---|---:|---|---|
| DISC-021 | STK-2026-07-27-CC-001 | ALL_CADASTRAL_SERVICES | ApplicationController::submit + SchemaValidator | No cadastral conflict check. Two offices can submit the same parcel/basin simultaneously. | Reject second submission if triple matches an active/prior transaction of another office | MISSED_PROVISION | HIGH | YES for STK-CC-001 activation | OPEN |
| DISC-022 | STK-2026-07-27-CC-002 | ALL_CADASTRAL_SERVICES | ApplicationController::submit + SchemaValidator | No owner-match check; no clearance/discharge document flow. | Require clearance + discharge from previous office when owner also matches | MISSED_PROVISION | HIGH | YES for STK-CC-002 activation | OPEN |
| DISC-023 | STK-2026-07-27-CC-003 | ALL_QUOTA_SERVICES | CapacityGuard.php + QuotaLedger.php | CapacityGuard rejects with 422 when quota exceeded. Does NOT create an Offices-Department workflow item. Does NOT expose quota-modification API. | Reject applicant + route to Offices Dept + expose GET/PATCH quota APIs | UNDERGENERALIZATION | HIGH | YES for full STK-CC-003 | OPEN |
| DISC-024 | STK-2026-07-27-CC-004 | ALL_SERVICES | WorkflowEngine.php + ApplicationReview model | Reviews carry `decision` (approved/rejected/modifications_requested) + `notes` free-text. No typed severity, no auto-routing based on note class. | Two-tier notes: BLOCKING routes to Offices Dept; ADVISORY passes but surfaces to Second Auditor exclusively. | MISSED_PROVISION | MEDIUM | YES for STK-CC-004 activation | OPEN |
| DISC-025 | STK-2026-07-27-CC-002 | ALL_CADASTRAL_SERVICES | 05_document_crosswalk.csv + all seeders | No `مخالصة` (clearance) or `براءة ذمة` (discharge) document type defined for any service. | Add `previous_office_clearance` + `previous_office_discharge` conditional documents; category=CONTRACT (or new CLEARANCE if convention allows) | MISSED_PROVISION | MEDIUM | YES for STK-CC-002 activation | OPEN |
| DISC-026 | STK-2026-07-27-CC-003 | ALL_QUOTA_SERVICES | No `قسم المكاتب` (Offices Department) role exists in RBAC | User roles: admin, superuser, staff, auditor, applicant. No "offices-department" role. | Add offices_department role + workflow stage. Currently unclear whether Offices Dept is a new role or an existing admin/staff group. | UNRESOLVED_ROLE_MODEL | MEDIUM | YES until RBAC clarified | OPEN |

---

## Open questions (append to `08_unresolved_questions.csv`)

| question_id | area | question_ar | question_en | blocks | severity | status |
|---|---|---|---|---|---|---|
| UNQ-015 | cadastral-normalization | كيف يُطابق النظام أسماء الحوض والمالك؟ نص حرفي أم بعد تطبيع الحروف والمسافات وعلامات التشكيل؟ | Is basin_name / owner_name matched as exact text, or after Arabic normalization (diacritics, alef variants, spacing)? Which app statuses count as "previously registered"? | STK-CC-001 + STK-CC-002 | HIGH | BLOCKING |
| UNQ-016 | rbac | ما هو "قسم المكاتب" — دور جديد في RBAC، أم مجموعة موظفين موجودة، أم نظام خارجي؟ | Is "Offices Department" a new RBAC role, an existing staff group, or an external system? | STK-CC-003 + STK-CC-004 | HIGH | BLOCKING |
| UNQ-017 | api-integration | هل API استعلام الكوتا وAPI تعديل الرصيد داخلية (Laravel routes) أم تكامل مع نظام خارجي؟ من يُخوَّل تعديل الرصيد؟ | Are the quota query/modify APIs internal Laravel routes or integrations with an external quota system? Who is authorized to modify balance? | STK-CC-003 | HIGH | BLOCKING |
| UNQ-018 | workflow-second-auditor | هل "المدقق الثاني" هنا هو نفس مرحلة second_auditor_review في soilProposedWorkflow؟ هل يستطيع رفض ملاحظات تنبيهية؟ | Is "Second Auditor" here the same as the existing `second_auditor_review` stage in SurveyWorkflowsSeeder? Can advisory notes be dismissed? | STK-CC-004 | MEDIUM | non-blocking |
| UNQ-019 | previous-office-issuance | كيف يُصدر المكتب السابق المخالصة وبراءة الذمة — عبر النظام نفسه (خدمة جديدة) أم خارجه؟ | How does the previous office issue the clearance/discharge — via the platform (new service) or off-platform? | STK-CC-002 | HIGH | BLOCKING |
| UNQ-020 | service-scope | هل STK-CC-* تشمل CERT-*, DEC-*, ENG-*, FIN-*, MSC-* أم مقتصرة على الخدمات ذات الحقول الكادسترالية والحصص؟ | Do STK-CC-* rules apply to CERT-*, DEC-*, ENG-*, FIN-*, MSC-*, or only to services with cadastral fields + quotas? | STK-CC-001..004 scope | MEDIUM | affects scoping |

---

## Impact on Batch 01 recommendation

Batch 01 was already `NEEDS_CORRECTION` for DISC-001 (fee rate) + UNQ-001..004 (SRV-002 governance). This addendum adds:

- **6 new HIGH-severity discrepancies** (DISC-021..026) — all cross-cutting, none currently implemented.
- **6 new open questions** (UNQ-015..020) — mostly BLOCKING for STK-CC-001..004 activation.
- **New architectural surface required**: `PreSubmitCadastralConflictGuard`, `OwnerMatchClearanceRule`, `OfficesDepartment` role + workflow stage, typed-notes taxonomy for the WorkflowEngine.

The Batch 01 recommendation therefore strengthens to:

```
UPDATED_BATCH_01_RECOMMENDATION: NEEDS_CORRECTION
BLOCKING_TOTAL: 4 (from manual) + 4 (from stakeholder addendum) = 8 open questions
                requiring JEA + product-owner answers before any Phase 1 implementation
CRITICAL_ITEMS_UNRESOLVED: 1 (DISC-001 fee rate) + implicit-critical STK-CC-001
                            (cadastral conflict is a data-integrity issue that could
                             corrupt multi-office attribution today)
```

Recommend before ANY per-service Phase 1 implementation (SRV-002 pilot, SRV-003 activation, etc.):
1. Resolve DISC-001 fee-rate governance question (UNQ-001).
2. Resolve UNQ-015..020 for the cross-cutting stakeholder rules.
3. Implement STK-CC-* as platform-wide gates FIRST (they apply to every service).
4. Then resume per-service pilot work under the corrected platform baseline.

---

## Traceability

If any of STK-CC-* is later found to be textually grounded in a manual page (likely candidates: chapter 8 pp.101-122 office registration + classification; chapter 9 pp.123-131 quotas + ceilings; chapter 10 pp.133-136 office coalitions), the provision migrates from `11_addendum_...md` → `02_manual_provisions.csv` with:

- New provision_id in `JEA-TM2025-CH{cc}-P{ppp}-{sss}` format.
- `notes` column records: `originally documented via STAKEHOLDER_INPUT 2026-07-27; textually grounded in {chapter/page/section}`.
- The stakeholder-input entry stays in this addendum for historical audit.
