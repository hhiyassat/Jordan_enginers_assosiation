# TD-00 · Business Rule Register (SRV-001 target scope)

Every business rule that SRV-001 target domain will need. Each carries the four-dimensional classification per user directive.

Rules are drawn from Ground Truth §3.5 (calculations), §3.4 (paths), §3.2 (submission), §3.3 (workflow), and the SG-04 seeder `Srv001RulesSeeder`.

## BR classifications

Legend:

* **SOURCE_STATUS**: SOURCE_CONFIRMED / SOURCE_CONFLICTED / SOURCE_FLOWCHART_ONLY / SOURCE_ASSERTED_UNRESOLVED / SOURCE_MISSING
* **BUSINESS_APPROVAL_STATUS**: APPROVED (signed) / UNVERIFIED (Ground Truth claims SRS confirmation but no OD-Closure) / PROVISIONAL (meeting-minute source only) / MISSING
* **IMPLEMENTATION_AUTHORIZATION**: AUTHORIZED / STRUCTURE_ONLY / BLOCKED_UNTIL_<OD>
* **PUBLICATION_AUTHORIZATION**: AUTHORIZED / BLOCKED

## BR-CALC — Calculation rules

| BR_ID | Rule | GT anchor | Existing code | SOURCE | BUSINESS_APPROVAL | IMPLEMENTATION | PUBLICATION | Notes |
|---|---|---|---|---|---|---|---|---|
| BR-CALC-01 | Wells count = f(largest floor area); reference floor persisted in snapshot | GT §3.5 (BR-002) | `WellsCountCalculator` uses `floor_area` (single value, not per-floor max) | SOURCE_CONFIRMED | UNVERIFIED (structure) / PROVISIONAL (values) | STRUCTURE_ONLY_AUTHORIZED (need per-floor input model) | BLOCKED | numeric outputs remain unchanged for legacy |
| BR-CALC-02 | Wells count bands: 0-200→2, 201-600→3, 601-800→4, 1001-1200→6, 1201-3000→6+ceil((area-1200)/300) | GT §3.5 | `WellsCountCalculator::compute` | SOURCE_CONFIRMED (six of seven bands) | PROVISIONAL (per SG-04 seeder classification) | AUTHORIZED for confirmed bands | BLOCKED | 801-1000 blocked (CONF-01) |
| BR-CALC-03 | Wells count 801-1000: 4 or 5 (CONFLICTED) | GT §4 CONF-01 | current code uses value X (verify legacy value) | SOURCE_CONFLICTED | PROVISIONAL | **IMPLEMENTATION_BLOCKED_UNTIL_OD-07** | BLOCKED | legacy value must NOT change per user directive |
| BR-CALC-04 | Governance example: 3000m² → 12 wells | GT §3.5 | present in `WellsCountCalculator` (verify) | SOURCE_CONFIRMED | PROVISIONAL | AUTHORIZED (characterization pin) | BLOCKED | test invariant only |
| BR-CALC-05 | Above 15 floors = tower/mega project | GT §3.5 | `ExplorationRequirementMatrix` returns SPECIAL_STUDY for floors 9+ (workaround) | SOURCE_CONFIRMED_EDGE_CONFLICTED | UNVERIFIED | IMPLEMENTATION_BLOCKED_UNTIL_OD_20 | BLOCKED | edge CONF-05: 15 vs 16 threshold |
| BR-CALC-06 | Roof 50m² exactly: > vs ≥ | GT §4 CONF-06 (BR-004) | not implemented | SOURCE_CONFLICTED | MISSING | BLOCKED_UNTIL_OD_21 | BLOCKED | edge case |
| BR-CALC-07 | Min floors for soil test: 3 or 4 | GT §4 CONF-04 (BR-006) | `NetDepthTable::MIN_FLOOR_COUNT=3` (verify) | SOURCE_CONFLICTED | PROVISIONAL | BLOCKED_UNTIL_OD_11 | BLOCKED | legacy value preserved |
| BR-CALC-08 | Exploration requirement matrix (jadwal 4-1 كتاب التعليمات الفنية 2025 ص 230-231) | GT §3 (implicit) + Srv001RulesSeeder | `ExplorationRequirementMatrix::compute` — 6 rows × 5 area bands | SOURCE_CONFIRMED (JEA-signed manual) | APPROVED at source level — no OD-Closure at rule level | AUTHORIZED | BLOCKED_UNTIL_UAT_SIGNED (RES-SG00-02) | manual is signed; rule pending UAT reference |
| BR-CALC-09 | Net depth table (third/two_thirds/total) per floor count | Meeting 2026-07-26 §XI | `NetDepthTable::compute` — PROVISIONAL | SOURCE_ASSERTED_UNRESOLVED | PROVISIONAL (meeting minutes, not JEA-signed) | STRUCTURE_ONLY_AUTHORIZED | BLOCKED | invariant third+two_thirds≠total unresolved |
| BR-CALC-10 | Exploration point count ≥ minimum (matrix) enforced at submit | Srv001Guard::validate | implemented | SOURCE_CONFIRMED | UNVERIFIED (indirectly via matrix APPROVED) | AUTHORIZED (already live) | AUTHORIZED (legacy path) | preserve behaviour |

## BR-FEE — Fee rules

| BR_ID | Rule | GT anchor | Existing code | SOURCE | BUSINESS_APPROVAL | IMPLEMENTATION | PUBLICATION | Notes |
|---|---|---|---|---|---|---|---|---|
| BR-FEE-01 | Engineering-services value = net_depth × 10 minimum | GT §3.5 | not implemented; SiteSurveyFeesSeeder uses per_unit(length_lm, 0.15) | SOURCE_CONFIRMED_MINIMUM | UNVERIFIED | BLOCKED_UNTIL_OD_01 | BLOCKED | interacts with CONF-02, CONF-03 |
| BR-FEE-02 | Sales tax 16% | GT §3.5 | not implemented | SOURCE_CONFIRMED | UNVERIFIED | AUTHORIZED (rate configurable) | BLOCKED | tax rate is confirmed; wiring pending |
| BR-FEE-03 | Social-responsibility fee = wells × 20 × 0.008 | GT §3.5 | not implemented | SOURCE_CONFIRMED_TENTATIVE ("مبدئيًا") | UNVERIFIED | BLOCKED_UNTIL_OD_01 | BLOCKED | |
| BR-FEE-04 | Survey fee = wells × 20 × 0.01 | GT §3.5 | not implemented | SOURCE_CONFIRMED_TENTATIVE | UNVERIFIED | BLOCKED_UNTIL_OD_01 | BLOCKED | |
| BR-FEE-05 | Income tax: depth × 20 OR meters × 20 (CONFLICTED) | GT §4 CONF-02 (SRS §8.6) | not implemented | SOURCE_CONFLICTED | MISSING | BLOCKED_UNTIL_OD_01 | BLOCKED | |
| BR-FEE-06 | Contract value: wells × 20 OR meters × 20 (CONFLICTED) | GT §4 CONF-03 (SRS §8.6) | not implemented | SOURCE_CONFLICTED | MISSING | BLOCKED_UNTIL_OD_01 | BLOCKED | |
| BR-FEE-07 | Fee frozen at submit (foundation invariant) | SG-04 RISK 3 | Application::fee_amount frozen at submit | SOURCE_CONFIRMED | BUSINESS_APPROVED_BY_DESIGN | AUTHORIZED | AUTHORIZED | inherited |

## BR-ELIG — Eligibility rules

| BR_ID | Rule | GT anchor | Existing code | SOURCE | BUSINESS_APPROVAL | IMPLEMENTATION | PUBLICATION | Notes |
|---|---|---|---|---|---|---|---|---|
| BR-ELIG-01 | Auto verification (eligibility + quota + specialisation + blocking notes) BEFORE technical routing | GT §3.2 | CrossCuttingSubmissionPipeline order established | SOURCE_CONFIRMED | BUSINESS_APPROVED (SG-* invariant) | AUTHORIZED | AUTHORIZED | inherited |
| BR-ELIG-02 | Shared eligibility contract — no quota-logic replay in the service | GT §3.2 | CapacityGuard + QuotaLedger separation (DG-11) | SOURCE_CONFIRMED | BUSINESS_APPROVED | AUTHORIZED | AUTHORIZED | inherited |
| BR-ELIG-03 | Post-submit edits limited to PartialEditGrant | GT §3.2 | not implemented | SOURCE_CONFIRMED (mechanism) | UNVERIFIED (scope) | BLOCKED_UNTIL_OD_29 (definition) | BLOCKED | scope unspecified |
| BR-ELIG-04 | Super-admin return before payment only, mandatory reason | GT §3.2 | not implemented | SOURCE_CONFIRMED | UNVERIFIED | AUTHORIZED (mechanism trivial) | BLOCKED | build behind feature flag |

## BR-ROUTE — Routing rules

| BR_ID | Rule | GT anchor | Existing code | SOURCE | BUSINESS_APPROVAL | IMPLEMENTATION | PUBLICATION | Notes |
|---|---|---|---|---|---|---|---|---|
| BR-ROUTE-01 | project_sector=حكومي → route to SRV-006 | Srv001PilotSeeder::attachRouting + Srv001Guard::checkRouting | implemented | SOURCE_CONFIRMED | UNVERIFIED | AUTHORIZED (legacy behaviour) | AUTHORIZED (legacy path) | preserve |
| BR-ROUTE-02 | Committees replace first auditor for energy projects (GAP-01) | GT §5 GAP-01 | not implemented | SOURCE_FLOWCHART_ONLY | UNVERIFIED | BLOCKED_UNTIL_OD_31 (proposed) | BLOCKED | flowchart-only source |
| BR-ROUTE-03 | Second auditor substitution for first (GAP-02) | GT §5 GAP-02 | not implemented | SOURCE_FLOWCHART_ONLY | UNVERIFIED | BLOCKED_UNTIL_OD_32 (proposed) | BLOCKED | flowchart-only source |
| BR-ROUTE-04 | Excavation-completed gate before sensory inspection (GAP-03) | GT §5 GAP-03 | not implemented | SOURCE_FLOWCHART_ONLY | UNVERIFIED | BLOCKED_UNTIL_OD_33 (proposed) | BLOCKED | flowchart-only source |
| BR-ROUTE-05 | After return to originating office prevent reassignment (FR-SS-049) | GT §3.3 | not implemented | SOURCE_CONFIRMED | UNVERIFIED | AUTHORIZED (mechanism trivial) | BLOCKED | data-constraint implementable |

## BR-DUP — Duplicate/Clearance identity

| BR_ID | Rule | GT anchor | Existing code | SOURCE | BUSINESS_APPROVAL | IMPLEMENTATION | PUBLICATION | Notes |
|---|---|---|---|---|---|---|---|---|
| BR-DUP-01 | Duplicate identity = (basin+parcel+basin_name) + owner + symmetric type + within 5 years | GT §3.4 | OwnerMatchClearanceGuard exists (cross-cutting) but does not implement the 5-year window | PARTIAL | UNVERIFIED (structure) | AUTHORIZED (extend guard) | BLOCKED | 5-year window is configurable |
| BR-DUP-02 | Exceptions: different owner OR 5-year expiry | GT §3.4 | not implemented | SOURCE_CONFIRMED | UNVERIFIED | AUTHORIZED (extends guard) | BLOCKED | requires historical query index |
| BR-DUP-03 | Two-project state: justification letter OR clearance PDF | GT §3.4 | Srv001PilotSeeder has `previous_office_clearance` + `previous_office_discharge` docs conditional | PARTIAL | UNVERIFIED | AUTHORIZED (already in schema) | BLOCKED | conditional_required_when semantics enforceable |
| BR-DUP-04 | Testing certificate validity = default 5 years (dynamic config) | GT §3.4 (FR-SS-059) | not implemented | SOURCE_CONFIRMED | UNVERIFIED | AUTHORIZED (mechanism) | BLOCKED | config-only rule |

## BR-DOC — Document rules

| BR_ID | Rule | GT anchor | Existing code | SOURCE | BUSINESS_APPROVAL | IMPLEMENTATION | PUBLICATION | Notes |
|---|---|---|---|---|---|---|---|---|
| BR-DOC-01 | Two photos per well + one general site photo + PDF report + topographic (PDF/DWG/image) | GT §3.6 | Srv001PilotSeeder has `site_investigation_report` (PDF 25MB) only; per-well photos + topographic missing | PARTIAL | UNVERIFIED | AUTHORIZED | BLOCKED (OD-24: file sizes) | new field types + count-per-well constraint |
| BR-DOC-02 | Chunked resumable upload | GT §3.6 | not verified | UNKNOWN | UNVERIFIED | AUTHORIZED | BLOCKED (OD-24, OD-27) | needs verification |
| BR-DOC-03 | MIME + magic-byte verification + quarantine | GT §3.6 | PdfOrDwgFile rule enforces; quarantine flow not verified | PARTIAL | UNVERIFIED | AUTHORIZED | AUTHORIZED for existing PDF-only paths | extends to new attachment types |
| BR-DOC-04 | Signed URLs | GT §3.6 | Certificate PDFs use signed qr_token URL; document downloads use per-request auth | PARTIAL | UNVERIFIED | AUTHORIZED | AUTHORIZED for existing paths | extend to attachment downloads |
| BR-DOC-05 | Attachment versioning | GT §3.6 | not implemented | MISSING | UNVERIFIED | AUTHORIZED | BLOCKED | new column on application_documents |
| BR-DOC-06 | Signature-role: HEAD_OF_SPECIALIZATION on site_investigation_report | Srv001PilotSeeder L508 | schema-only declaration; no enforcement | STRUCTURE_ONLY | UNVERIFIED | AUTHORIZED (add validator) | BLOCKED (OD-30 for engineer registry integration) | signature enforcement requires engineer identity source |

## BR-STATE — State machine

| BR_ID | Rule | GT anchor | Existing code | SOURCE | BUSINESS_APPROVAL | IMPLEMENTATION | PUBLICATION | Notes |
|---|---|---|---|---|---|---|---|---|
| BR-STATE-01 | Target state chain (11 states) | GT §3.3 | 7 states in Application::STATUS_* | PARTIAL | UNVERIFIED | AUTHORIZED (extension) | BLOCKED (OD-18) | schema-driven state addition; do not silently change existing behaviour |
| BR-STATE-02 | All paths terminate with conformance certificate (FR-SS-062) | GT §3.3 | CertificatesController::issue exists; end-of-path not audited | PARTIAL | UNVERIFIED | BLOCKED_UNTIL_BR-STATE-01 | BLOCKED | dependency chain |
| BR-STATE-03 | Rejection note mandatory; acceptance note optional (FR-SS-051) | GT §3.3 | not enforced | MISSING | UNVERIFIED | AUTHORIZED (trivial) | BLOCKED | field validator |

## Rule-level counts

* BR-CALC: 10 rules — 1 AUTHORIZED-and-live (BR-CALC-10), 3 STRUCTURE-only, 6 BLOCKED
* BR-FEE: 7 rules — 1 AUTHORIZED-and-live (BR-FEE-07), 1 AUTHORIZED (BR-FEE-02 rate config), 5 BLOCKED
* BR-ELIG: 4 rules — 2 AUTHORIZED-and-live, 1 AUTHORIZED-mechanism, 1 BLOCKED
* BR-ROUTE: 5 rules — 1 AUTHORIZED-and-live, 1 AUTHORIZED-mechanism, 3 BLOCKED
* BR-DUP: 4 rules — 0 AUTHORIZED-and-live, 4 AUTHORIZED-extension, 0 fully BLOCKED (all publication-blocked)
* BR-DOC: 6 rules — 2 partially live, 4 AUTHORIZED-extension
* BR-STATE: 3 rules — all partial or blocked

**Aggregate**: 39 rules. 6 fully live-and-authorized. ~15 AUTHORIZED for structural implementation without changing numerics. ~18 BLOCKED_UNTIL_OD-*.

## Numeric behaviour preservation

Per user directive "SRV001_NUMERIC_BEHAVIOR_CHANGED=NO / SRV001_WORKFLOW_BEHAVIOR_CHANGED=NO / SRV001_FEE_BEHAVIOR_CHANGED=NO", the following are FROZEN during target-domain build:

* Current output of `WellsCountCalculator::compute` for every input
* Current output of `NetDepthTable::compute` for every input
* Current output of `ExplorationRequirementMatrix::compute` for every input
* Current SiteSurveyFeesSeeder fee (per_unit length_lm × 0.15)
* Current 7-state application state machine transitions
* Current routing behaviour (project_sector=حكومي → SRV-006)

Any target-domain class that changes any of these outputs on the legacy code path is FORBIDDEN by this program. New target classes may exist in parallel and be selected by service-definition-version binding (SG-03 mechanism).
