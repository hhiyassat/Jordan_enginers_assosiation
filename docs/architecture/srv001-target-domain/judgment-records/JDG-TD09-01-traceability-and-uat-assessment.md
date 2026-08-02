JUDGMENT_ID=JDG-TD09-01
TITLE=SRV-001 Requirements Traceability + Acceptance Evidence + UAT Readiness Assessment (NOT_UAT_READY)
OWNER=TD-09
PHASE=TD-09 (Batch 6 · traceability, acceptance evidence, UAT assessment)

الوضع=
TD-03..TD-08 delivered runtime, calculation, external-port, workflow, document, and financial foundations. TD-09 must produce a complete, machine-checkable requirements-traceability + acceptance-evidence package, and return an honest UAT readiness verdict — while preserving every unresolved OD, every unimplemented adapter, and every publication/production blocker.

تحرير_محل_النزاع=
1. **What does "100% Must disposition coverage" mean?** That every Must requirement has an RTM row with an approved disposition — NOT that every Must is IMPLEMENTED_AND_TESTED.
2. **What does UAT_READY require?** Every entry criterion satisfied. Absence of any: verdict is NOT_UAT_READY.
3. **How is a characterization test distinguished from an EXECUTABLE_ACCEPTANCE?** Characterization asserts current behaviour without claiming business approval; EXECUTABLE_ACCEPTANCE requires the requirement itself to be business-approved AND the test to exercise that requirement end-to-end.

السبب=
The mandate is explicit: `RTM_DISPOSITION_COVERAGE=100%` for Must requirements ≠ `MUST_IMPLEMENTATION_COMPLETE=100%`. Blocked requirements must reference a blocker. UAT_READY is forbidden unless every entry criterion is satisfied. Characterization tests must not be labelled owner-approved acceptance.

الشرط=
- Every FR-SS-001..090 has an RTM row (proven by validation test).
- Every RTM row uses a known enum for `runtime_status` and `evidence_status`.
- Every IMPLEMENTED_AND_TESTED row references implementation evidence AND at least one test file.
- Every BLOCKED_* row references a blocker (OD / contract / residual).
- No row claims `runtime_status=IMPLEMENTED_AND_TESTED` while referencing a `Target*` file.
- Every acceptance-scenario `classification` uses the approved enum; no `BLOCKED_PENDING_*` is `EXECUTABLE_ACCEPTANCE`.
- Every blocking OD referenced by RTM exists in `registers/open-ods.csv` AND is marked UNRESOLVED.
- Every residual id referenced by RTM exists in `td-00/residual-register.md`.
- `UAT_READINESS_VERDICT=NOT_UAT_READY`.

المانع=
Claiming any Target* rule as IMPLEMENTED_AND_TESTED would falsify the entire program's TARGET_RUNTIME_STATUS=INACTIVE invariant. Labeling any characterization test as owner-approved acceptance would create false UAT-readiness signal. Returning `UAT_READY` without signed baseline 2.0 would misrepresent authorization state.

العلة=
Evidence integrity + governance auditability. The RTM is the artefact JEA product uses to decide publication + UAT + cutover — any misclassification pollutes those decisions.

القادح=
Any implementation that:
- adds a row missing from the FR-SS-001..090 range
- marks a `Target*` file as IMPLEMENTED_AND_TESTED
- returns UAT_READY while any entry criterion is unmet
- silently closes an OD the program did not close
- labels a `BLOCKED_PENDING_*` scenario as EXECUTABLE

Would fire this قادح.

الصحة=
Valid implementation:

1. **`registers/rtm.csv`** — 90 rows, one per FR-SS-001..090. Columns cover source (reference + status), authorization (business + implementation + publication), release, component, file lists (domain / application / adapter / migration / test), acceptance scenarios, runtime status, blocking ODs, blocking contracts, residual ids, evidence status, notes.
2. **`registers/open-ods.csv`** — 22 open ODs enumerated (OD-01 through OD-35 subset), all UNRESOLVED.
3. **`registers/external-contracts.csv`** — 13 external contract rows; all NOT_OPERATIONAL.
4. **`registers/publication-blockers.csv`** — 9 explicit publication blockers.
5. **`acceptance/scenarios.csv`** — 50 acceptance scenarios classified across 8 states.
6. **`uat-assessment.md`** — entry criteria checklist + proposed UAT scope + test-role matrix + environment checklist. Verdict = `NOT_UAT_READY`.
7. **`test-data.md`** — deterministic UAT fixtures (organizations, users, rule seeds, application drafts).
8. **`tests/Feature/Traceability/Srv001RtmValidationTest.php`** — 14 automated invariants covering the mandate's 17 required validations:
   - RTM has all 90 FR-SS rows
   - Exactly 90 rows
   - Unique requirement ids
   - Valid runtime + evidence enums
   - IMPLEMENTED rows reference tests + implementation files
   - BLOCKED rows reference blockers
   - No BLOCKED row claims production-active
   - All blocking ODs exist in open-ods.csv
   - All referenced residual ids exist in residual-register.md
   - No IMPLEMENTED_AND_TESTED row references a Target* file
   - Acceptance classifications use approved enum + no BLOCKED_PENDING_* is EXECUTABLE
   - No orphan publication_authorization=PUBLISHED claim
   - Scenario catalog populated (≥ 30)
   - Every referenced OD still UNRESOLVED

الفساد=
Producing an RTM with tolerant enums (e.g., "unknown" as a valid disposition) would be fasid — the RTM's purpose is to force honest classification.

البطلان=
Silently marking any Target* rule as production-active would be batil — every downstream decision (publication, UAT scope, cutover) would carry that false claim.

الأثر=
- 5 new documentation artefacts (rtm.csv, open-ods.csv, external-contracts.csv, publication-blockers.csv, scenarios.csv).
- 2 new documentation md files (uat-assessment.md, test-data.md).
- 1 new test file (Srv001RtmValidationTest — 14 tests / 839 assertions).
- 0 modifications to existing source files.
- 0 changes to runtime.

البقايا=
- **RES-TD09-01** (OPEN) — RTM is comprehensive but its dispositions have not been ratified by JEA product. Closure: JEA product signs off on each row's disposition.
- **RES-TD09-02** (OPEN) — UAT-dedicated environment not provisioned. Closure: dedicated env + role assignments + published fixtures.
- Every OD carried forward from prior phases remains OPEN (this program did NOT close any OD).

التعارض=
None.

الجمع=
Reconciled. TD-09 produces the traceability + acceptance evidence + UAT assessment package with construction-time invariants (14 machine-checkable tests) that make honest classification the only way to pass CI.

الترجيح=
Tier-5 (governance-auditability) + Tier-4 (target architecture — RTM anchors every downstream decision) + evidence integrity.

التوقف=
STOPPED on: silently closing any OD; claiming UAT_READY; misclassifying blocked requirements as production-active.

Continues on: honest disposition + evidence package.

READINESS_CLASSIFICATION=Compliant with TD-09 mandate. UAT_READINESS_VERDICT=NOT_UAT_READY.

CLOSURE_EVIDENCE=
- Focused TD-09 tests: **14/14 PASS / 839 assertions / 19 ms** on SQLite
- Full test suites all green (Unit 427, Feature 775/782/7 skipped, Architecture 26/27/1 skipped, PHPStan 0 errors)
- RTM validation covers all 17 mandate items
- All 90 FR-SS rows enumerated
- No OD closed
