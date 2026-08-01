JUDGMENT_ID=JDG-SG04-01
TITLE=RuleDefinition / RuleVersion / CalculationSnapshot granularity
SCOPE=architecture/service-governance/SG-04
OWNER=service-governance-remediation

الوضع=
The SRV-001 pilot contains three distinct calculators — `ExplorationRequirementMatrix` (cites كتاب التعليمات الفنية 2025), `WellsCountCalculator` (PROVISIONAL, cites 2026-07-26 meeting minutes §X), `NetDepthTable` (PROVISIONAL, cites §XI). Each produces derived values persisted into `applications.data`. No table tracks which calculator produced which value or which version of the calculator was used.

تحرير_محل_النزاع=
Should `RuleDefinition` model be granular per FORMULA (three rows for SRV-001 — one per calculator), per SERVICE (one row for SRV-001), or per SPECIFIC BUSINESS RULE (potentially many rows if a calculator implements multiple rules)?

السبب=
Program §Phase SG-04 mandates rule versioning + snapshotting without changing SRV-001 numeric behaviour. The choice of granularity determines how many rule rows exist and how snapshots are keyed.

الشرط=
Choice must (i) let a single derived value be traced to a single rule version, (ii) allow one calculator to be replaced without affecting the versioning of the others, (iii) keep table row count manageable (per-calculator gives 3 rules for SRV-001, per-business-rule could give 10+), (iv) allow the identity string of the implementing PHP class to be stored as a `implementation_identity` field.

المانع=
Per-SERVICE granularity would fuse three calculators with three different provenances into a single rule — hides the fact that `ExplorationRequirementMatrix` is cited from a signed technical reference while `WellsCountCalculator` cites unsigned meeting minutes.

Per-BUSINESS-RULE granularity would multiply rows unnecessarily; e.g. `ExplorationRequirementMatrix` contains six row-formulas (per floor band) — creating six rules for one calculator adds no evidence not already present in the calculator's file header.

العلة=
Trace clarity + operational manageability. The per-calculator level matches how the code is organised (one file per calculator) and how the business documents cite them (one manual section per calculator).

القادح=
None.

الصحة=
Valid granularity: per-calculator. `RuleDefinition` rows for `SRV001_EXPLORATION_MATRIX`, `SRV001_WELLS_COUNT`, `SRV001_NET_DEPTH`. Each has its own `RuleVersion` chain.

الفساد=
Fine-grained per-formula split would be fasid — the extra rows fabricate provenance that doesn't exist in the source documents.

البطلان=
Per-SERVICE fusion is batil — it defeats the purpose of the mechanism.

الأثر=
(1) Three `RuleDefinition` rows seeded at SG-04 migration time for the SRV-001 calculators. (2) Each has a `RuleVersion` row with the current implementation's identity + `business_approval_status`. (3) `CalculationSnapshot` rows reference the rule_version_id, application_id, inputs, outputs, intermediate values, warnings, `calculated_at`.

البقايا=
RES-SG04-01: Future services may need their own rule definitions. The seeder that inserts SRV-001 rules doubles as the pattern. Owner: per-service onboarding.

التعارض=
None.

الجمع=
Not needed.

الترجيح=
Tier-4 (target architecture) + Tier-6 (current implementation organisation) both support per-calculator.

التوقف=
Not stopped.

EVIDENCE=
- backend/modules/JeaServices/Engine/ExplorationRequirementMatrix.php (header cites JEA-signed manual)
- backend/modules/JeaServices/Engine/WellsCountCalculator.php header (PROVISIONAL — meeting minutes)
- backend/modules/JeaServices/Engine/NetDepthTable.php header (PROVISIONAL — meeting minutes + unresolved invariant)

DECISION=
Per-calculator granularity. Three `RuleDefinition` rows for SRV-001: `SRV001_EXPLORATION_MATRIX`, `SRV001_WELLS_COUNT`, `SRV001_NET_DEPTH`. Each with own `RuleVersion` and `business_approval_status` (APPROVED for the manual-cited matrix, PROVISIONAL for wells count and net depth).

IMPLEMENTATION_EFFECT=
Three tables (`rule_definitions`, `rule_versions`, `calculation_snapshots`). Seeder inserts the three SRV-001 rules. `Srv001Guard` writes snapshots alongside its existing derived-value persistence.

MIGRATION_EFFECT=
Additive. No changes to SRV-001 numeric outputs — validated by re-running `Srv001GuardTest` and `WorkflowEngineTest` after the snapshot writer is wired.

TEST_EVIDENCE=
Tests: snapshot row is created per calculator execution; inputs + outputs match; rule_version reference is correct; SRV-001 numeric outputs unchanged.

OPEN_RESIDUALS=
- RES-SG04-01 (future services onboarding pattern).
