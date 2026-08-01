JUDGMENT_ID=JDG-SG00-02
TITLE=SRV-001 classification and terminology
SCOPE=architecture/service-governance/SG-00
OWNER=service-governance-remediation

الوضع=
Prior handoff (docs/handoff/service-architecture/01-...md §1, §11) classifies SRV-001 with statements such as "fully wired end-to-end" (FULLY_WIRED_END_TO_END=1) and "pilot" (Srv001Guard PILOT_COMPLETE). Repository evidence at HEAD confirms: (a) real fields via Srv001PilotSeeder, (b) real workflow via SurveyWorkflowsSeeder::soilProposedWorkflow, (c) service-specific guard Srv001Guard, (d) three calculators. However: (i) WellsCountCalculator + NetDepthTable are labeled PROVISIONAL in their file headers, sourcing values from meeting minutes (محضر اجتماع 2026-07-26), not from a JEA-signed technical reference; (ii) NetDepthTable acknowledges an unresolved arithmetic invariant (third + two_thirds ≠ total); (iii) no repository artefact carries a JEA UAT sign-off for the SRV-001 behaviour as a whole; (iv) the target SRS presented earlier in this program's parent conversation (JEA-ESP2-SRS-SITE-SURVEY-001) diverges from what SRV-001 currently implements — additional fields, additional attachments, borehole rules, depth rules and building rules not present in the current pilot.

تحرير_محل_النزاع=
Is SRV-001 correctly classified today as (A) `FULLY_IMPLEMENTED / SRS_COMPLETE / BUSINESS_APPROVED / PRODUCTION_READY`, or (B) `PILOT_RUNTIME_WIRING_COMPLETE / TARGET_DOMAIN_INCOMPLETE / BUSINESS_APPROVAL_PARTIAL / SRS_CONFORMANCE_INCOMPLETE`?

السبب=
This program (§2) explicitly forbids classifying SRV-001 as fully implemented. The prior handoff's phrasing "FULLY_WIRED_END_TO_END" is ambiguous; a reader may interpret it as SRS-complete.

الشرط=
"Fully implemented" would require: (i) all target-SRS fields present, (ii) all target-SRS calculators approved, (iii) UAT sign-off evidence, (iv) production activation approval evidence. None of these four is met in the repo.

المانع=
The two PROVISIONAL calculators (WellsCountCalculator, NetDepthTable) explicitly disclaim JEA approval in their own file headers.

العلة=
Protection of applicants and reviewers. If SRV-001 is classified as production-ready and business-approved while calculators are unapproved, downstream authorities may assume the numeric outputs are JEA-signed when they are not.

القادح=
File-header disclaimers on WellsCountCalculator + NetDepthTable; absence of any `uat_reference` field on service_definitions; absence of any signed source document in the repository for the wells-count bands or net-depth decomposition; explicit note in NetDepthTable that `third + two_thirds ≠ total` is an unresolved invariant.

الصحة=
Valid classification set: PILOT_RUNTIME_WIRING_COMPLETE, TARGET_DOMAIN_INCOMPLETE, BUSINESS_APPROVAL_PARTIAL, SRS_CONFORMANCE_INCOMPLETE.

الفساد=
The prior handoff's phrasing "FULLY_WIRED_END_TO_END" is technically true (the runtime wiring is complete for the pilot slice) but pragmatically defective because it invites the reader to conclude SRS completion. It must be reworded, not deleted.

البطلان=
Any classification labelling SRV-001 as SRS_COMPLETE or BUSINESS_APPROVED is invalid under the evidence available.

الأثر=
(1) All governance documents must use the four permitted classifications. (2) SG-06 (legacy boundary) will encapsulate the current pilot as LEGACY_PILOT_PENDING_BUSINESS_APPROVAL — not as canonical or approved. (3) Downstream production-activation decisions cannot presume SRV-001 is approved.

البقايا=
RES-SG00-02: Product owner must obtain JEA-signed source for the two PROVISIONAL calculators (or a decision that they should be replaced by target-SRS rules). Owner: product owner. Blocks: any migration from LEGACY_PILOT to canonical.

التعارض=
Prior handoff phrasing "FULLY_WIRED_END_TO_END" vs the mandated four-classification set.

الجمع=
Reconcilable: retain "runtime wiring complete for pilot slice" in the corrected maturity CSV column, and separately mark BUSINESS_APPROVAL_PARTIAL, SRS_CONFORMANCE_INCOMPLETE.

الترجيح=
Evidence tier 5-6 (runtime safety + repository) supports the pilot-wiring claim. Evidence tier 1-3 (signed decisions, contracts, approved technical standards) is absent → cannot promote to SRS_COMPLETE / BUSINESS_APPROVED.

التوقف=
Not stopped for the classification act. Stopped for any decision that would treat the current SRV-001 as target-SRS conformant.

EVIDENCE=
- backend/modules/JeaServices/Engine/WellsCountCalculator.php file header (PROVISIONAL, source: meeting minutes)
- backend/modules/JeaServices/Engine/NetDepthTable.php file header (PROVISIONAL, unresolved invariant)
- backend/modules/JeaServices/Engine/Srv001Guard.php (real service-specific runtime wiring — pilot slice)
- backend/modules/JeaServices/Database/Seeders/Srv001PilotSeeder.php (28 fields, 2 documents)
- Absence of any `uat_*` column on service_definitions
- Absence of any signed technical reference file in the repository for wells-count or net-depth tables

DECISION=
Adopt the four-classification set for SRV-001:
    SRV001_RUNTIME_WIRING=PILOT_COMPLETE
    SRV001_DOMAIN_REQUIREMENTS=PARTIAL
    SRV001_BUSINESS_APPROVAL=PARTIAL_OR_UNCONFIRMED
    SRV001_TARGET_SRS_CONFORMANCE=INCOMPLETE
Retire the phrasing "FULLY_WIRED_END_TO_END" for SRV-001 wherever it implies SRS completion.

IMPLEMENTATION_EFFECT=
SG-00-handoff-reconciliation.md restates SRV-001 classification. SG-00-corrected-maturity-model.csv uses these four classifications instead of the single ACTIVATION_STATUS column previously used for SRV-001.

MIGRATION_EFFECT=
None (documentation).

TEST_EVIDENCE=
Not applicable.

OPEN_RESIDUALS=
- RES-SG00-02 (SRV-001 calculator business approval — decided by product owner, not by this program).
