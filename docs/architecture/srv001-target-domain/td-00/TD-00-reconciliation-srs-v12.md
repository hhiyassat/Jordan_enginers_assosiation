# TD-00 Reconciliation · SRS v1.2 Absorption

**Baseline (TD-00 commit):** `218aa58`
**Source added:** `srs/JEA-ESP2-SRS-SITE-SURVEY-001_v1.2.md` (350 lines / 40 KB — verified authentic)
**Judgment record:** `judgment-records/JDG-TD00-03-srs-v12-reconciliation.md`

Closes **RES-TD00-01** (missing SRS body). Absorbs SRS v1.2 deltas without changing the TD-00 verdict.

## SRS v1.2 approval status (verified from the document itself)

The document contains its own approval disclaimer in two places:

* Header table (ضابط الاعتماد): *"لا تصبح مرجعًا تعاقديًا إلا بعد إغلاق القرارات الحاجبة واعتماد RTM والصلاحيات وتوقيع مالك المتطلبات على خط الأساس 2.0"*
* §الخلاصة: *"تبقى غير معتمدة للتنفيذ النهائي في الحسابات والمسار المالي والتكاملات حتى إغلاق الحاجبات وتوقيع خط الأساس 2.0. وجود متطلب أو جدول في الوثيقة ليس اعتمادًا قانونيًا أو فنيًا لقيمته."*

This CONFIRMS the user's original directive: "Do not infer formal approval from the word 'Reviewed'". SRS v1.2 is a **draft under review**, targeting a future v2.0 as the signed contractual baseline. **Every rule's BUSINESS_APPROVAL_STATUS remains UNVERIFIED** until per-rule OD-Closure attached — identical to the state before v1.2 was supplied.

## What SRS v1.2 confirms (no classification changes)

* All 7 Ground Truth CONFIRMED calculation rules (well-count bands, ExplorationRequirementMatrix source, etc.)
* All 7 Ground Truth CONFLICTED items (CONF-01..CONF-07) — v1.2 EXPLICITLY re-lists them with the SAME status labels
* All 5 Ground Truth GAPS (GAP-01..GAP-05) — v1.2 formally RATIFIES GAP-01..03 as OD-31/32/33 (per §18); GAP-04 subsumed into architecture; GAP-05 addressed as RTM correction (§17)
* Ground Truth §6 SUPERSEDED items remain SUPERSEDED

## What SRS v1.2 adds (new rows in registers)

### 10 new functional requirements (§8.2)

Added to `requirement-delta-matrix.csv` as TD-REQ-181..190. Summary:

| FR ID | Title | State | Release | Blocker |
|---|---|---|---|---|
| FR-SS-081 | Quota-expiry auto-referral to Services dept | SOURCE_CONFIRMED (fee OD-17) | R4 | OD-17 (fee) |
| FR-SS-082 | Specialist-loss triggers service block (category A) | SOURCE_CONFIRMED | R1 | none |
| FR-SS-083 | Return to First Auditor for final approval | **CONFLICTED** | R3 | **OD-34** |
| FR-SS-084 | Regional tax exemption | BLOCKED (list) | R2 | **OD-35** |
| FR-SS-085 | Paper + electronic claim (Oracle) | BLOCKED (integration) | R4 | OD-30 |
| FR-SS-086 | Minimum 2 wells when adjoining buildings merged | SOURCE_CONFIRMED | R2 | none |
| FR-SS-087 | QR reading of القوشان (registration deed) | SOURCE_CONFIRMED | R1 (Should) | OD-30 (partial) |
| FR-SS-088 | Mandatory internal notes (may Block parcel) | SOURCE_CONFIRMED | R5 (Should) | none |
| FR-SS-089 | Show unrealised-reinforcement note on later transactions | BLOCKED (Oracle) | R4 | OD-30 |
| FR-SS-090 | Unified contract-state filter (soil + materials) | SOURCE_CONFIRMED | R3 | none |

### 2 new ODs (§18)

Added to `open-decision-register.md`:

* **OD-34** — Final approval loop: does the transaction return to First Auditor after Second Auditor? Blocks state matrix + FR-SS-083.
* **OD-35** — Final signed list of tax-exempted governorates (currently Karak/Ma'an/Tafileh from Unified Report + Aqaba question from prior OD-05). Merges OD-05.

### OD-05 → merged into OD-35

Explicit merge stated in SRS §18. Any inherited reference to OD-05 elsewhere in the program should redirect to OD-35.

### Refined depth tables (§4.3)

Ground Truth §3.5 had bands 3-9 floors from `NetDepthTable`. SRS v1.2 §4.3 provides the FULL table:

| Floors | Third (1/3) | Two-thirds (2/3) |
|---|---|---|
| 3 | 9 | 6 |
| 4 | 10 | 7 |
| 5 | 12 | 8 |
| 6 | 13 | 9 |
| 7 | 14 | 10 |
| 8 | 15 | 10 |
| 9 | 16 | 11 |
| 10 | 17 | 12 |
| 11 | 18 | 13 |
| 12 | 19 | 14 |
| 13 | 20 | 15 |
| 14 | 21 | 16 |

Plus aggregated ranges for ≥15 floors:

| Floors | Third | Two-thirds |
|---|---|---|
| 15–19 | 22 | 19 |
| 20–24 | 28 | 24 |
| 25–29 | 30 | 29 |
| 30–34 | 34 | 30 |

**Selection rule** (when to pick third vs. two-thirds, and how to combine across differing-area floors) remains **BLOCKED** per SRS §4.3 explicit note.

**Implication for TD-01+**: `NetDepthTable` can be extended structurally to include the additional rows (floors 10-14 explicit + 15-34 ranges) WITHOUT changing legacy numeric outputs (which cover only 3-9 today). But the SELECTION RULE must remain unimplemented — target class returns the numeric row lookup only, not the "which value to pick" decision.

### Refined wells count table (§4.1)

Same as Ground Truth §3.5 for 0-3000m² bands (with 801-1000 CONFLICTED per OD-07). Adds:

* **>3000m²**: 1 well per 400m² additional — BLOCKED (transition point + credit rule per OD-22)
* **≥15 floors**: 1 well per 200m² regardless of area band — BLOCKED (priority of application per OD-20)

### Additional SRS-v1.2 items not in Ground Truth

Added to `requirement-delta-matrix.csv` as TD-REQ-200..300:

* Depth-table expansion (TD-REQ-200)
* Wells count >3000m² (TD-REQ-201)
* Wells count ≥15 floors (TD-REQ-202)
* Office categories A/B (TD-REQ-210) — Category B ambiguity noted
* Annual specialty quotas (TD-REQ-211) — VALUES not delivered
* Three tax collection paths (TD-REQ-220)
* Reinforcement path (TD-REQ-230)
* Exemption "once in lifetime" invariant + race lock (TD-REQ-240)
* Donation campaigns admin (TD-REQ-250)
* 8-step application form model (TD-REQ-260)
* Duplicate/clearance identity refinement (TD-REQ-270) — symmetric-type-only + BURA514 fallback
* New entities QuotaIncreaseReferral + InternalMandatoryNote (TD-REQ-280)
* RBAC additions Services Dept + Internal-note recording (TD-REQ-290)
* New reports Quota+Referrals + Campaigns (TD-REQ-300)

## What the reconciliation does NOT change

* **JDG-TD00-02 verdict**: READY_WITH_NON_BLOCKING_RESIDUALS — unchanged.
* **10-item AUTHORIZED TD-01 scope**: unchanged. None of the SRS v1.2 additions unlock a new AUTHORIZED item; none block an existing one.
* **Legacy behaviour preservation invariants**: unchanged.
* **Every FORBIDDEN item from JDG-TD00-02**: still FORBIDDEN.

## Residual register net effect

| Residual | Pre-reconciliation | Post-reconciliation |
|---|---|---|
| RES-TD00-01 (SRS body missing) | OPEN | **CLOSED** (file supplied) |
| RES-TD00-01b (SRS non-approval — new) | (n/a) | OPEN (informational; identical semantics to RES-TD00-02) |
| RES-TD00-01c (soil_testing_srs.md reconciliation) | (implicit) | CLOSED (SRS §version log supersedes v1.0/v1.1/v0.1) |
| RES-TD00-01d (v1.2 delta absorbed) | (n/a) | CLOSED by this commit |
| RES-TD00-02..07 | OPEN | Unchanged (OPEN) |
| Inherited SG-*/RC-* residuals | Unchanged | Unchanged |

## Files touched by this reconciliation

**In-place updates**:

* `td-00/source-register.md` — SRS v1.2 row added at rank 2; prior v1.1 row demoted to historic-reference note
* `td-00/residual-register.md` — RES-TD00-01 marked CLOSED + 3 sub-residuals added
* `td-00/requirement-delta-matrix.csv` — 26 new TD-REQ rows (TD-REQ-181..190 for FRs; TD-REQ-200..300 for SRS-native additions)
* `td-00/open-decision-register.md` — OD-34, OD-35 added; OD-31/32/33 reclassified from "proposed" to "formally adopted"; OD-05 CLOSED-BY-MERGE noted

**New files**:

* `td-00/TD-00-reconciliation-srs-v12.md` (this document)
* `judgment-records/JDG-TD00-03-srs-v12-reconciliation.md`

**Preserved**:

* All original TD-00 files retained; nothing deleted or renamed
* Every existing residual/conflict/gap/stopped-decision preserved (per user directive)
* Legacy code unchanged (this is a doc-only phase)

## Gates for TD-00-Reconciliation

| Gate | Result |
|---|---|
| Documentation-only phase | PASS (no code changes) |
| RES-TD00-01 closure evidence attached | PASS (SRS file verified authentic, cited in judgment record) |
| Existing residuals preserved | PASS (RES-TD00-02..07 unchanged; only RES-TD00-01 transitioned OPEN → CLOSED per its own closure evidence definition) |
| No target-start blocker introduced | PASS |
| Verdict unchanged | PASS (READY_WITH_NON_BLOCKING_RESIDUALS) |

## Next phase

Per JDG-TD00-02 authorized scope, TD-01 = target-domain skeleton classes parallel to `Legacy*`. Reconciliation does NOT introduce any TD-01 scope change.
