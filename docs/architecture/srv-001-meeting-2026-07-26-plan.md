# SRV-001 — Post-Meeting Gap Analysis & Workstream Plan

> Source: [Meeting minutes 2026-07-26](../meetings/2026-07-26-jea-soil-testing.md) — JEA Soil Testing Department (قسم فحص التربة).
>
> Purpose: Map each business requirement in the minutes to (a) what already exists in the codebase and (b) what has to be built or extended.

Legend:
- ✅ **Exists** — implemented; may need extension only
- 🟡 **Partial** — some scaffolding, gaps to fill
- ❌ **New** — nothing yet, needs full build

---

## §I — Organizational scheme (المخطط التنظيمي)

| Requirement | Status | Location |
|---|---|---|
| Min 3 floors in-zone, 2+roof out-of-zone (soil-test trigger) | ❌ | Add rule to `Srv001Guard` and/or a new `SoilTestEligibilityGuard` |
| Upload → OCR auto-fill; manual fallback | ❌ | Attachment ingestion + optional OCR service (can be stubbed via interface for demo, like `JeaMembershipVerifier`) |
| Fields carried: building types, floor count, height, setbacks | 🟡 | Extend SRV-001 schema (see §IX gap) |

## §II — Duplicate transaction check (مخالصة, screen `burrs14`)

| Requirement | Status | Location |
|---|---|---|
| Detect duplicate by plot + basin + basin name (factor 1) | ✅ | `CadastralConflictGuard` (STK-CC-001) |
| Detect duplicate by owner name (factor 2) | ✅ | `OwnerMatchClearanceGuard` (STK-CC-002) |
| PDF clearance upload with reason letter (2-projects, deceased owner, etc.) | 🟡 | Backend guards accept a `clearance_document_id` — needs frontend upload UI + reason field |
| Phase 4 formal "مخالصة" service | ✅ | Already seeded as `MSC-010` — just needs its schema wired up |
| Screen code `burrs14` | ❌ | Frontend screen not yet built |
| `Check` gate at soil-test entry | ✅ | Guards run in `CrossCuttingSubmissionPipeline` before submit |

## §III — Soil test validity (5 years, dynamic)

| Requirement | Status | Location |
|---|---|---|
| 5-year validity, configurable | ❌ | New setting (`platform_settings.soil_test_validity_years`), applied in submission guard |
| On expiry → re-approve or new office | ✅ (services) / ❌ (routing) | `SRV-010`/`SRV-011` (Re-approval with/without additions) exist as catalog rows, but no eligibility rule routes an expired report to them |

## §IV — Required APIs

| API | Purpose | Status |
|---|---|---|
| #1 GET/POST — JEA Oracle | External certify/query | ❌ New interface (`JeaOracleClient`) + Fake for demo |
| #2 GET — engineer quota | Read per-engineer quota | ❌ New (`EngineerQuotaClient`), pairs with §VII engine |
| #3 UPDATE — office quota | Write per-office quota | ❌ New |
| #4 GET — office quota | Read per-office quota | ❌ New |

## §V + §VI — Ranks (رئيس اختصاص) + office ownership

| Requirement | Status | Location |
|---|---|---|
| Engineer ranks A / B | ❌ | Add `rank` enum to Engineer model + migration |
| Rank B eligibility: 7 yrs graduated + 3 yrs practice | ❌ | `EngineerRankEligibility` engine |
| رئيس اختصاص = A + B | ❌ | Same |
| Office owner must be رئيس اختصاص + founder | ❌ | Enforce in `OfficeRegistrationValidator` (currently only checks JEA membership) |
| Founder withdraws → route office to قسم المكاتب (تصويب وضع) | ❌ | New department role + workflow lane |
| Engineer over quota → route to قسم المكاتب | ❌ | Depends on §VII engine + department |

## §VII — Engineering quotas table

| Requirement | Status | Location |
|---|---|---|
| Table: cert × discipline × experience-type × years, w/ 15-yr cap | ❌ | New seeder `EngineerQuotaScheduleSeeder` + `EngineerQuotaCalculator` engine |
| Bachelor / Master / PhD tiers | ❌ | Same |
| Max design ceiling per discipline (18750 / 28125 / 56250 / 56250 / 1188 / 118750) | ❌ | Enforced in `EngineerQuotaCalculator` |
| Design + Supervision have separate quotas (equal per row) | ❌ | Two ledgers per engineer |
| Soil-mechanics scope isolated (soil practice only counts if soil) | ❌ | Same |
| PM quota = 50% of structural | ❌ | Same |

## §VIII — Notes taxonomy

| Requirement | Status | Location |
|---|---|---|
| Mandatory internal notes on office/plot (can `Block`) | ❌ | Pending CC-004 (typed notes taxonomy for `WorkflowEngine`) |
| Community notes visible to Second Reviewer | ❌ | Same |

## §IX — Electronic soil-test request form fields (SRV-001 schema)

Currently in `SurveyWorkflowsSeeder`; needs expansion to include:

**Project info:**
- `contract_party_type` (متعاقد / ائتلاف)
- `tax_number`
- `contract_class` (خاص / حكومي)
- `first_team_data`, `second_team_data` (structured team blocks)
- `contract_number` (auto-generated, unique)
- `office_city`
- `contract_signed_at`
- `project_area`
- `national_number`

**Project area:**
- `dls_key` (generated from title deed + parcel data)
- `applicant_proof_document` (conditional on first-team ≠ applicant)

**Buildings/floors:**
- `building_count` (required, drives building/floor UI)
- `partial_basement` block with `basement_m2`, `floor_m2`, `roof_m2`
- Floor-area completion toggle when actual < required

**Engineer + special use:**
- `head_of_specialization` → engineer name + auto-filled `engineer_number` via API #2
- `is_house_of_worship` + name + proof
- `is_charity` + name + proof

**Totals (calculated, read-only display):**
- `total_wells`, `total_wells_depth`, `total_floor_area`
- `total_fees`, `tax_areas`

**Exemption:**
- `exemption_flag` yes/no → conditional `exemption_type` + attachment

## §X — Wells calculation

| Requirement | Status | Location |
|---|---|---|
| Table: 0–200 → 2 wells, …, +1 per 300 m² up to 3000, then +1 per 400 m² | 🟡 | `ExplorationRequirementMatrix` exists but doesn't cover this table yet — extend it |

## §XI — Net depth by floor count

| Requirement | Status | Location |
|---|---|---|
| Table: floors 3→9 with third/two-thirds/total | ❌ | New engine or extend `ExplorationRequirementMatrix`; also feeds financial equations (§XIII) |
| Exemption toggle + attachment | ❌ | Schema field (§IX) |

## §XII — Special cases

| Requirement | Status | Location |
|---|---|---|
| Grid System (divide land into squares, calc per-square) | ❌ | New engine step; feeds §X wells calc |
| Multi-building on same plot: adjacent OR distance < 2× setback → merge | ❌ | New engine step; feeds §X wells calc |

## §XIII — Financial equations

| Requirement | Status | Location |
|---|---|---|
| Tax equations (min × 10 sales tax, ×16% VAT, income tax = meters × 20) | 🟡 | Extend `FeeCalculator` — new formula module |
| Fees on total wells: practice 0.15قرش, social 20×0.008, site-survey 20×0.01 | 🟡 | Same |
| Office can raise engineering-services value, not lower it | ❌ | Validation rule during submit |

---

## Proposed workstreams (execution order)

Grouped so each PR is independently reviewable and shippable. Order minimises rework: pure engines first, then schema/UI, then integrations.

1. **W1 — Docs (this PR)**: minutes + gap analysis. ✅ (this doc)
2. **W2 — Wells + NetDepth engines** (§X, §XI, §XII). Pure functions; TDD, no DB. Extends `ExplorationRequirementMatrix` or splits into `WellsCountCalculator` + `NetDepthCalculator` + `MultiBuildingResolver` + `GridSystemResolver`.
3. **W3 — SRV-001 schema refresh** (§I zoning fields, §IX all form fields, §III validity display). Update `SurveyWorkflowsSeeder` + frontend renderer. Depends on W2 for computed-field wiring.
4. **W4 — Financial equations** (§XIII). Extend `FeeCalculator` with the specific formulas + tax module. Depends on W2 (needs net-depth + wells totals).
5. **W5 — 5-year validity gate** (§III). Setting + `SoilTestValidityGuard` + routing hint to `SRV-010`/`SRV-011`.
6. **W6 — Clearance UI** (§II frontend). Backend guards already exist; needs the `burrs14` screen with PDF+reason upload.
7. **W7 — Engineer quotas engine** (§VII). Table seeder + calculator + per-engineer + per-office ledgers. Standalone; feeds W8.
8. **W8 — Ranks + office ownership** (§V, §VI). Engineer.rank migration + eligibility engine + `OfficeRegistrationValidator` extension + قسم المكاتب department/routing.
9. **W9 — Notes taxonomy (CC-004)** (§VIII). Was already pending pre-meeting; unblocks Second-Reviewer surface and the plot-Block flow.
10. **W10 — External APIs** (§IV). Interfaces + Fakes for JEA Oracle + engineer/office quota. Real HTTP later.
11. **W11 — OCR ingest** (§I). Interface + Fake; real OCR later.

Workstream sizing (rough, PHPUnit + Vitest included):

| WS | Backend LOC | Frontend LOC | Tests |
|---|---|---|---|
| W2 | ~400 | 0 | ~20 |
| W3 | ~150 (seeder) | ~500 (form) | ~15 |
| W4 | ~200 | ~100 (breakdown) | ~15 |
| W5 | ~150 | ~30 (badge) | ~8 |
| W6 | ~100 | ~250 | ~10 |
| W7 | ~600 | ~200 (report) | ~30 |
| W8 | ~400 | ~150 | ~15 |
| W9 | ~350 | ~150 | ~15 |
| W10 | ~250 (interfaces + fakes) | 0 | ~10 |
| W11 | ~150 | ~80 | ~5 |

Total: ~2750 backend + ~1460 frontend + ~140 tests. Realistically 2–3 focused weeks.

## Open questions to raise with JEA before implementing

- Wells table §X — for the "زيادة عن 1200" band (1201–3000): is the wells count "6 + ⌈(area-1200)/300⌉" or "⌈area/300⌉ starting from row 6"? The minutes are ambiguous.
- §XI depths — the "third / two-thirds" columns: are those depth partitions of the total, or independent well depths for two well types?
- §XIII "office can raise, not lower" — is the floor the calculated engineering-services value, or a JEA-fixed minimum per soil-mechanics linear meter?
- §VII quotas — are years capped at 15 total across all rows, or per-row? The table says "حد أقصى 15 سنة" on multiple rows.
- §V ranks A/B — where do current engineers get their initial rank from? Is there an existing NPS/Oracle field we should sync from?
