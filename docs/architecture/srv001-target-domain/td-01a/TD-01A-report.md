# TD-01A · SRS v1.2 Baseline Reconciliation + Domain-Legacy Boundary Fix

**Program:** `ESP_V2_SRV001_TARGET_DOMAIN_IMPLEMENTATION`
**Phase:** TD-01A (supplemental reconciliation — NOT a TD-00 restart nor a TD-01 rewrite)
**Baseline HEAD (start):** `f6dd8f4` (post TD-01 skeleton)
**Judgment records:** `judgment-records/JDG-TD01A-01-srs-v12-baseline-classification.md` + `JDG-TD01A-02-domain-legacy-boundary-remediation.md`

## Purpose

1. Reconcile the newly-supplied SRS v1.2 into the source register with formal authority classification.
2. Cross-check that TD-00R already covered the deltas the user directive enumerates (§8.2 FR-SS-081..090; §18 OD-34/35).
3. Reconcile the RTM (§17) and release-allocation (§19) corrections.
4. Reconcile the two new entities (`QuotaIncreaseReferral`, `InternalMandatoryNote` from §10).
5. Update residual + blocker classifications.
6. Reassess every TD-01 provisional rule against SRS v1.2 with the four-dimensional classification.
7. Execute the mandated architectural review of TD-01's Legacy delegation and remediate.

## SRS v1.2 authority classification (JDG-TD01A-01)

Per user directive + SRS §self-declaration:

```
SOURCE_BASELINE_STATUS         = CURRENT_DRAFT_TARGET_BASELINE
DOCUMENT_STATUS                = DRAFT_REVIEW
CONTRACTUAL_AUTHORITY          = NO
BUSINESS_APPROVAL_AUTHORITY    = NO
FINAL_IMPLEMENTATION_AUTHORITY = NO
PUBLICATION_AUTHORITY          = NO
PRODUCTION_AUTHORITY           = NO
REQUIRES_SIGNED_BASELINE_2_0   = YES
```

v1.1 preserved as historical (superseded by v1.2 per SRS §version log). No prior TD classification is promoted by SRS v1.2 presence.

## User-directive checklist reconciliation

| # | Item | Status | Where |
|---|---|---|---|
| 1 | Update source register with SRS v1.2 + authority classification | **DONE (TD-01A)** | `td-00/source-register.md` — new v1.2 row + authority-table subsection |
| 2 | Update requirement-delta matrix for FR-SS-081..090 | **DONE (TD-00R)** — reaffirmed | `td-00/requirement-delta-matrix.csv` TD-REQ-181..190 |
| 3 | Extend open-decision register through OD-35 | **DONE (TD-00R)** | `td-00/open-decision-register.md` |
| 4 | Add OD-34 (final first-reviewer approval loop conflict) | **DONE (TD-00R)** | `td-00/open-decision-register.md` under "ODs added in SRS v1.2 (§18)" |
| 5 | Add OD-35 (final tax-exempt governorate list + effective date; merges OD-05) | **DONE (TD-00R)** | same section |
| 6 | Update RTM grouping corrections (SRS §17) | **DONE (TD-01A)** | see §"RTM + release allocation" below + `RES-TD01A-07` |
| 7 | Update release allocation corrections (SRS §19, FR-SS-057..061 R4, FR-SS-062 R5) | **DONE (TD-01A)** | same section + `RES-TD01A-05` |
| 8 | Reconcile `QuotaIncreaseReferral` + `InternalMandatoryNote` entities | **DONE (TD-01A)** | see §"New entities" below + `RES-TD01A-06` |
| 9 | Update residual + blocker classifications | **DONE (TD-01A)** | `td-00/residual-register.md` new section "TD-01A-owned residuals" (RES-TD01A-01..07) |
| 10 | Reassess every TD-01 provisional rule against SRS v1.2 | **DONE (TD-01A)** | see §"TD-01 rule reassessment" below |
| 11 | Ensure every relevant rule records the 4 dimensions | **DONE** — every row in `requirement-delta-matrix.csv` already carries the 4 columns | (verified) |
| 12 | Ensure CONFLICTED + BLOCKED rules remain excluded from production behavior | **VERIFIED** | Runtime path unchanged (`Srv001Guard` still the only SRV-001 runtime); Target* classes not wired; ServiceAvailabilityPolicy + ServicePublicationPolicy still refuse any unapproved service |

## RTM + release allocation (SRS §17 + §19)

**§17 grouping correction** (fixes Ground-Truth GAP-05):

| Group (SRS v1.2 §17) | FR range | Component | AC references |
|---|---|---|---|
| الطلب والأهلية | 001–010, 073, 082 | Application + Office Eligibility Contract | 01/02/06/16 |
| المرفقات والوثائق والقراءة الآلية | 011, 012, 032–036, 058, 075, 087 | Document Service | 01/07/14/17/27 |
| الأبنية والحسابات | 013–023, 086 | Rules & Calculation Engine | 03/04/05/13/22/23 |
| الإعفاءات والمالية والضرائب | 024–031, 060–061, 077–080, 084–085 | Payment & Tax Contract + Rules | 05/09/13/18/29 |
| التدقيق وسير العمل | 037–051, 074, 083, 090 | Workflow Engine + Review | 06/07/11/21 |
| المسارات الخاصة والتكرار | 052–059, 062 | Workflow + Duplicate Check | 08/12 |
| السجل والإدارة والإشعارات | 063–067, 029 | Audit + Admin Config | 06/13 |
| التكاملات | 068–072, 089 | Integration Adapters | 16/18/20/25 |
| الكوتة والإحالات | 081 | Services Referral + Eligibility | 02/06 |
| الملاحظات الداخلية والمجتمعية | 072, 088 | Notes/Observations | 06/15 |

Recorded as informational — RES-TD01A-07 tracks producing an authoritative RTM cross-reference at TD-N summary time.

**§19 release-allocation correction** (fixes CONF-07):

* FR-SS-057..061 → **R4 exclusively**
* FR-SS-062 (certificate) → **R5** (depends on R5 path closure)
* FR-SS-082/087 → R1
* FR-SS-084/086 → R2
* FR-SS-083/090 → R3 (083 blocked on OD-34)
* FR-SS-081/085/089 → R4
* FR-SS-088 → R5

Recorded in RES-TD01A-05. Not implemented in TD-01A — waits for the state-machine extension in a later TD phase (per RES-TD00-04).

## New entities (SRS §10)

**`QuotaIncreaseReferral`** — captures the Services-dept referral triggered by FR-SS-081 (quota expiry). Fields: application_id (FK), requested_quota (int + unit), fee (decimal), decision (enum), decided_at, decided_by (FK).

**`InternalMandatoryNote`** — captures FR-SS-088 mandatory internal notes. Fields: scope (enum: office | parcel), scope_id, decision (text), session_reference, effect (enum: block | warning), created_by (FK), created_at.

Not implemented in TD-01A. Two additive migrations + Eloquent models scoped to TD-02+ per RES-TD01A-06. RBAC additions (SRS §13) — new roles "Services Dept extra-quota decision" and "Internal-note recording" — accompany the entity implementation.

## TD-01 rule reassessment against SRS v1.2

Every TD-01 target-domain component reassessed under the 4-dimensional classification (per user directive item 11):

| Component | SOURCE_STATUS | BUSINESS_APPROVAL_STATUS | IMPLEMENTATION_AUTHORIZATION | PUBLICATION_AUTHORIZATION | Notes |
|---|---|---|---|---|---|
| `Srv001SubmissionInputs` (value object) | SOURCE_CONFIRMED (form-field mapping matches Srv001PilotSeeder + SRS §3) | UNVERIFIED | AUTHORIZED | AUTHORIZED (VO carries no rule) | TD-01 |
| `Srv001DerivedValues` (value object) | SOURCE_CONFIRMED | UNVERIFIED | AUTHORIZED | AUTHORIZED (VO) | TD-01 |
| `Srv001CalculationEvidence` (VO) | SOURCE_CONFIRMED | UNVERIFIED | AUTHORIZED | AUTHORIZED | TD-01 |
| `Srv001ValidationErrors` (VO) | SOURCE_CONFIRMED | UNVERIFIED | AUTHORIZED | AUTHORIZED | TD-01 |
| `EngineerRegistryPort` / `DlsLookupPort` (interfaces) | SOURCE_CONFIRMED (SRS §12 integrations enumerated) | UNVERIFIED | AUTHORIZED (interface only) | BLOCKED_UNTIL_OD_30 (production adapter) | TD-01 |
| `Srv001ExplorationMatrixRule` / `WellsCountRule` / `NetDepthRule` (interfaces, NEW in TD-01A) | SOURCE_CONFIRMED (rules exist in SRS §4.1/§4.3 + Ground Truth §3.5) | UNVERIFIED | AUTHORIZED (interfaces only) | BLOCKED | TD-01A |
| `Srv001ExplorationStatus` (constant class, NEW in TD-01A) | SOURCE_CONFIRMED (three-state enum matches Engine output) | UNVERIFIED | AUTHORIZED | AUTHORIZED (VO) | TD-01A |
| `TargetExplorationRequirementMatrixCalculator` | SOURCE_CONFIRMED_EDGE_CONFLICTED (CONF-01/OD-07; CONF-05/OD-20; §4.1 >3000 OD-22) | UNVERIFIED (structure) / APPROVED at source-reference level (كتاب التعليمات الفنية 2025) | AUTHORIZED (structural via port + adapter) | BLOCKED | TD-01A refactor |
| `TargetWellsCountCalculator` | SOURCE_CONFIRMED (bands 0-3000) + BLOCKED (>3000, ≥15 floors) | PROVISIONAL (inherits from legacy) | AUTHORIZED (structural) | BLOCKED | TD-01A refactor |
| `TargetNetDepthTableCalculator` | SOURCE_CONFIRMED (§4.3 explicit rows for floors 3-14 + ranges 15-34; SELECTION RULE blocked) | PROVISIONAL | AUTHORIZED (structural) | BLOCKED | TD-01A refactor — SRS §4.3 additional rows NOT integrated (legacy-numeric-output preservation) |
| `TargetSrv001SubmissionPolicy` | SOURCE_CONFIRMED (mirrors legacy behaviour under injected ports) | UNVERIFIED | AUTHORIZED (contract satisfied) | BLOCKED | TD-01A minor edit (Engine constant → Domain constant) |
| `LegacyBridgeExplorationMatrixRule` / `WellsCountRule` / `NetDepthRule` (adapters, NEW in TD-01A) | SOURCE_CONFIRMED (delegates verbatim) | inherits legacy (APPROVED-source / PROVISIONAL) | AUTHORIZED (adapter only) | BLOCKED | TD-01A |

**No rule promoted to `BUSINESS_APPROVED`. No rule promoted to `IMPLEMENTATION_AUTHORIZED_FOR_PRODUCTION_TARGET_RULE`. No RuleVersion published.**

## Architectural remediation (JDG-TD01A-02)

### Before (TD-01)

`Modules\JeaServices\Domain\Srv001\Calculators\Target*` directly imported `Governance\Srv001\Legacy*` — Domain-layer boundary violation.

### After (TD-01A)

Port-and-adapter pair:

* **Domain layer** (`Modules\JeaServices\Domain\Srv001\`):
  * `Contracts\Srv001ExplorationMatrixRule` (NEW port)
  * `Contracts\Srv001WellsCountRule` (NEW port)
  * `Contracts\Srv001NetDepthRule` (NEW port)
  * `Contracts\Srv001ExplorationStatus` (NEW constant class — replaces `Engine\ExplorationRequirementMatrix::STATUS_*`)
  * `Calculators\Target*` — REFACTORED: depend on port only; zero Legacy or Engine imports
  * `TargetSrv001SubmissionPolicy` — MINOR EDIT: uses `Srv001ExplorationStatus::SPECIAL_STUDY_REQUIRED` instead of Engine constant

* **Adapters layer** (`Modules\JeaServices\Adapters\Srv001\` — OUTSIDE Domain):
  * `LegacyBridgeExplorationMatrixRule` (NEW adapter, delegates to Legacy)
  * `LegacyBridgeWellsCountRule` (NEW adapter)
  * `LegacyBridgeNetDepthRule` (NEW adapter)

### Enforcement

New architecture test `tests/Feature/Architecture/Srv001DomainBoundariesTest.php`:

* `test_domain_srv001_files_do_not_import_forbidden_prefixes` — scans every `.php` in `Domain\Srv001\` for `use` statements matching any Legacy/Engine prefix; asserts empty violation list.
* `test_domain_srv001_calculators_declare_only_port_dependencies` — narrow whitelist of allowed imports for `Calculators\*` (Contracts + SG-05 governance value-object interfaces only).

Grep confirmation after refactor:

```
$ grep -rn "Governance.*Srv001.*Legacy\|Modules\\JeaServices\\Engine\\Wells\|
           Modules\\JeaServices\\Engine\\NetDepth\|
           Modules\\JeaServices\\Engine\\Exploration" \
       backend/modules/JeaServices/Domain/
(no matches — only documentation strings remain, filtered by test)
```

## Test evidence

| Suite | Tests | Passed | Assertions | Duration |
|---|---|---|---|---|
| `tests/Unit/Domain/Srv001/*` (TD-01 focused + parity) | 30 | 30 | (updated) | ~0.5s |
| `tests/Feature/Architecture/Srv001DomainBoundariesTest.php` (NEW) | 2 | 2 | — | ~0.05s |
| **TD-01A total** | **32** | **32** | **136** | **499ms** |
| Regression sweep (Governance / Srv001 / Workflow / ServiceCatalog / Domain / Boundaries / Architecture) | 224 | 223 (1 skipped) | 1190 | 5.7s |
| PHPStan (full, `--memory-limit=1G`) | — | — | 0 errors | — |

**Numeric parity** re-verified after refactor: every Target* calculator output matches Legacy* verbatim across all directive §8 examples.

## Runtime + publication invariants (verified unchanged)

| Invariant | Status |
|---|---|
| `Srv001Guard` remains the sole SRV-001 runtime path | UNCHANGED — no controller/registry edits |
| `LegacySrv001SubmissionPolicy` remains a parallel-only path | UNCHANGED |
| Target* / LegacyBridge* / new ports are NOT wired to runtime | UNCHANGED |
| No RuleVersion promoted to APPROVED | UNCHANGED (Srv001RulesSeeder classifications frozen) |
| ServiceAvailabilityPolicy still gates every operation | UNCHANGED |
| ServicePublicationPolicy still refuses unapproved services | UNCHANGED |
| No payment / workflow / certificate wiring change | UNCHANGED |
| User-owned untracked files preserved | UNCHANGED |

## Files touched

**Refactored (Domain)**:

* `backend/modules/JeaServices/Domain/Srv001/Calculators/TargetExplorationRequirementMatrixCalculator.php`
* `backend/modules/JeaServices/Domain/Srv001/Calculators/TargetWellsCountCalculator.php`
* `backend/modules/JeaServices/Domain/Srv001/Calculators/TargetNetDepthTableCalculator.php`
* `backend/modules/JeaServices/Domain/Srv001/TargetSrv001SubmissionPolicy.php`

**Added (Domain contracts)**:

* `backend/modules/JeaServices/Domain/Srv001/Contracts/Srv001ExplorationMatrixRule.php`
* `backend/modules/JeaServices/Domain/Srv001/Contracts/Srv001WellsCountRule.php`
* `backend/modules/JeaServices/Domain/Srv001/Contracts/Srv001NetDepthRule.php`
* `backend/modules/JeaServices/Domain/Srv001/Contracts/Srv001ExplorationStatus.php`

**Added (Adapters, outside Domain)**:

* `backend/modules/JeaServices/Adapters/Srv001/LegacyBridgeExplorationMatrixRule.php`
* `backend/modules/JeaServices/Adapters/Srv001/LegacyBridgeWellsCountRule.php`
* `backend/modules/JeaServices/Adapters/Srv001/LegacyBridgeNetDepthRule.php`

**Test updates**:

* `backend/tests/Unit/Domain/Srv001/TargetCalculatorsParityTest.php` — construct via adapter; align string-match assertion
* `backend/tests/Unit/Domain/Srv001/TargetSrv001SubmissionPolicyTest.php` — construct via adapter
* `backend/tests/Feature/Architecture/Srv001DomainBoundariesTest.php` — NEW boundary test

**Docs**:

* `docs/architecture/srv001-target-domain/td-00/source-register.md` — v1.2 authority-classification table
* `docs/architecture/srv001-target-domain/td-00/residual-register.md` — TD-01A residuals section (RES-TD01A-01..07)
* `docs/architecture/srv001-target-domain/judgment-records/JDG-TD01A-01-srs-v12-baseline-classification.md` (NEW)
* `docs/architecture/srv001-target-domain/judgment-records/JDG-TD01A-02-domain-legacy-boundary-remediation.md` (NEW)
* `docs/architecture/srv001-target-domain/td-01a/TD-01A-report.md` (this)

## Final report

```
START_HEAD=f6dd8f4c785d63ba1265d74f31f9c86545626420
END_HEAD=<recorded post-commit>
SRS_V1_2_REGISTERED=YES (source-register.md rank-2 row + authority-classification table)
V1_1_TARGET_STATUS=PRESERVED_AS_HISTORICAL (SRS §version log confirms supersession by v1.2)
FR_081_090_RECONCILED=YES (10 TD-REQ-181..190 rows in requirement-delta-matrix.csv)
OD_34_35_REGISTERED=YES (open-decision-register.md "ODs added in SRS v1.2 (§18)" section)
TARGET_LEGACY_DEPENDENCY_STATUS=RESOLVED_VIA_PORT_AND_ADAPTER (3 ports in Domain\Srv001\Contracts + 3 LegacyBridge* adapters in Adapters\Srv001; architecture test enforces boundary)
TARGET_RUNTIME_WIRING=NONE (Srv001Guard remains the sole SRV-001 runtime)
RULE_VERSION_PUBLICATION_STATUS=NONE_PUBLISHED (Srv001RulesSeeder classifications frozen; no RuleVersion promoted)
TEST_RESULTS=PASS (TD-01A: 32/32/136 assertions/499ms; regression: 223/224/1 skipped/1190 assertions; architecture boundary tests: 2/2)
PHPSTAN_STATUS=PASS (0 errors, --memory-limit=1G)
USER_UNTRACKED_FILES_STATUS=PRESERVED
PUSH_STATUS=NOT_PERFORMED (user directive: local commit only)
NEXT_PHASE_RECOMMENDATION=TD-02 rule-version + snapshot writer end-to-end wiring (JDG-TD00-02 authorised scope item #2; closes RES-SG06-01) — introduces a submission use case consuming ServiceSubmissionDecision + calling CalculationSnapshotWriter::writeForSubmit inside a single transaction. Legacy numeric outputs remain unchanged; runtime consumer swap remains BLOCKED_UNTIL_TARGET_CANONICAL_PROMOTION per JDG-TD00-02.
```
