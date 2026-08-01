# TD-00 · Terminology Register

Canonical vocabulary for the SRV-001 target domain. Every term below is either **APPROVED** (safe to use in code + docs + UI) or **FORBIDDEN** (must not appear).

## Approved canon (Ground Truth §2 + SG-* + implemented code)

| Approved term | Domain | Definition source | Notes |
|---|---|---|---|
| **ائتلاف الكوتة** (Quota Coalition) | routing | Ground Truth §2 + SRS §9.5 as summarised | مكتب أول → مكتب ثانٍ → بيع كوتة → رسوم وضريبة |
| **المكتب المؤتلِف** | actor | Ground Truth §2 (corrected reading) | NOT "المكتب المؤلف" |
| **المخالصة** (Clearance) | financial + technical | SRS §1.4 as summarised in Ground Truth | Both financial AND technical proof; NOT "Clearance ماليًا فقط" |
| **الكشف الحسي** (Sensory Inspection) | operational | Ground Truth §2 + GAP-03 | Performed AFTER excavation (التجريف) — GAP-03 = ODوضع بوابة "اكتمال التجريف" |
| **إجازة فنية** vs **قبول** | decision | SRS §9.1 as summarised | Distinct; NOT synonyms; final definition pending OD-29 |
| **الإجراء** vs **الحالة** | modeling | SRS §9.1 | Actor actions vs transaction states — strict separation |
| **SOURCE_CONFIRMED** | classification | User directive | Rule is claimed by Ground Truth to be in SRS v1.1 §3 — DOES NOT imply BUSINESS_APPROVED / IMPLEMENTATION_AUTHORIZED / PUBLICATION_AUTHORIZED |
| **BUSINESS_APPROVED** | classification | User directive | Rule has an OD-Closure signature or equivalent JEA sign-off |
| **IMPLEMENTATION_AUTHORIZED** | classification | User directive | Rule may be built as code (contracts + tests) — may be true even when BUSINESS_APPROVAL=PENDING (for infrastructure) |
| **PUBLICATION_AUTHORIZED** | classification | User directive | Rule may be exposed as PUBLISHED (SG-01 ServicePublicationPolicy conditions met) |
| **LEGACY_PILOT_PENDING_BUSINESS_APPROVAL** | rule status | SG-06 | Current SRV-001 wiring — do not treat as canonical |
| **PROVISIONAL** | rule-version status | SG-04 | RuleVersion.business_approval_status enum value |
| **AWAITING_UAT / UAT_APPROVED / PUBLISHED / SUSPENDED / RETIRED / NOT_PUBLISHED** | service lifecycle | SG-01 ServiceLifecycleState | Governance states — NOT to be confused with application states |
| **STATUS_DRAFT / STATUS_SUBMITTED / STATUS_UNDER_REVIEW / STATUS_MODIFICATIONS_REQUESTED / STATUS_APPROVED / STATUS_REJECTED / STATUS_CERTIFICATE_ISSUED** | application state | Application::STATUS_* | Existing 7-state machine |
| **STATE_MACHINE_TARGET** (per Ground Truth §3.3) | target application flow | Draft → Office_Check → Technical_Reviewer_1 → Technical_Reviewer_2 → Approved_Technically → Pending_Payment → Paid → (Sensory_Inspection \| Financial_Clearance) → Certificate_Issuance → Completed | **DELTA vs current**: current has 7 states, target has ~11 — implementation delta enumerated in requirement-delta-matrix |
| **PartialEditGrant** | edit policy | Ground Truth §3.2 as summarised | Post-submission edits limited to declared PartialEditGrant scope |
| **RuleVersion** | governance | SG-04 | rule_versions table |
| **CalculationSnapshot** | governance | SG-04 | calculation_snapshots table |
| **ServiceSubmissionPolicy / ServiceCalculationPolicy** | domain contract | SG-05 | Typed-decision extension contracts |
| **LegacySrv001SubmissionPolicy** | boundary class | SG-06 | Parallel adapter; NOT canonical |
| **DEFERRED / المؤجل** | path variant | Ground Truth §3.4 + flowchart | Contract deferred because testing vehicle cannot reach site; two-phase (dormant → reconciliation → re-enter proposed) |
| **مشاريع كبرى** (Mega Projects) | service type | Ground Truth §3.4 | Distinct path? OR filter? See GAP-04 (unresolved) |
| **مشاريع طاقة** (Energy Projects) | service type | Ground Truth §3.4 | Committees replace first auditor — GAP-01 (unresolved) |
| **مدقق تربة أول / ثانٍ** (Soil Auditor First / Second) | actor | SurveyWorkflowsSeeder + Ground Truth §3.3 | External first auditor + internal second auditor at Survey Unit |
| **قسم الاستطلاع** (Reconnaissance Dept.) | organisation | soil_testing_srs (SUPERSEDED) + Ground Truth §3.3 | Manager role: TBD-1 (OD material) |
| **basin_number / parcel_number** | cadastral | Srv001PilotSeeder | Strings (leading zeros preserved), not integers |
| **floor_count / floor_area** | building geometry | Srv001PilotSeeder + ExplorationRequirementMatrix | Inputs to exploration matrix |
| **DLS Key** | integration | Srv001PilotSeeder L297 | Land Directorate integration key; `semantic_status: NEEDS_JEA_API` — currently field-only, no live integration |
| **ائتلاف** (Coalition contract-party) | party type | Srv001PilotSeeder L263-269 | Enum: متعاقد / ائتلاف |
| **الرقم الضريبي / الرقم الوطني** | party identity | Srv001PilotSeeder | Both required for the contract party |
| **رئيس الاختصاص** (Head of Specialization) | engineer role | Srv001PilotSeeder L344-360 | Signature requirement on `site_investigation_report` document |

## Forbidden aliases (must not appear in code, docs, seed data, tests, UI)

| Forbidden | Why | Correct term |
|---|---|---|
| **Disposal** | Ground Truth §2: "أي ترجمة سابقة بـ Disposal لاغية" | ائتلاف الكوتة (Quota Coalition) |
| **إتلاف** (as workflow path) | soil_testing_srs.md is SUPERSEDED; term was used incorrectly there | ائتلاف الكوتة |
| **المكتب المؤلف** | Ground Truth §2: parsing error correction | المكتب المؤتلِف |
| **Clearance (financial only)** | Ground Truth §2 | مخالصة (financial + technical) |
| **Sensory Inspection (before excavation)** | Ground Truth §2 + GAP-03 | كشف حسي بعد التجريف |
| **إجازة فنية = قبول** | SRS §9.1 | Distinct terms — do not synonymise |
| Any rule promoted to APPROVED without OD-Closure | User directive | Keep PROVISIONAL / PENDING |
| "Reviewed" (SRS v1.1) = APPROVED | User directive | UNVERIFIED unless OD-Closure attached |
| Application STATUS_* used interchangeably with service lifecycle | This register | Two distinct state machines |

## Reserved for TD-01+ (target-domain phases)

| Reserved | Owner | Rationale |
|---|---|---|
| `Srv001TargetSubmissionPolicy` (or similar canonical name) | TD-01+ implementation | The target replacement for `LegacySrv001SubmissionPolicy` |
| `Srv001TargetWellsCalculator` etc. | TD-01+ | Target replacements once JEA signs off (RES-SG00-02) |
| `TargetSrv001*` prefix convention | TD-01+ | Distinguish from `Legacy*` classes during transition |
| `target_rule_version_id` | TD-01+ | If schema needs to distinguish legacy-bound vs target-bound applications |
