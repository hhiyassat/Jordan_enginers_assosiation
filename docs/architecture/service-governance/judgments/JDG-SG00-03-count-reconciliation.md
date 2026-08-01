JUDGMENT_ID=JDG-SG00-03
TITLE=Count reconciliation — services, fees, workflows, documents
SCOPE=architecture/service-governance/SG-00
OWNER=service-governance-remediation

الوضع=
Prior handoff (docs/handoff/service-architecture/01-...md §1 and factual ending block) reports 57 services (14 SRV + 12 DRW-P + 6 FIN + 6 CERT + 2 ENG + 4 DEC + 13 MSC), placeholder-fee count 35, real-fee count 22, real-workflow count 7, template-workflow count 50, seeded-documents count 11. Repository evidence at HEAD confirms most counts but reveals: (a) the "placeholder fee = 50000 JOD" is applied by `ServiceFeeDefaultsSeeder` AFTER `ServicePlan2026Seeder` (which writes amount=0). The mechanism was described imprecisely in the prior handoff. (b) SRV-001 has 2 documents seeded via Srv001PilotSeeder, not counted in the DRW-P family. (c) DRW-P-001..010 share the 15-doc DrawingsDocumentsSeeder manifest — that is 10 services carrying documents, not 15. (d) SRV-014 is treated as having a real workflow (visualInspectionWorkflow from a flowchart source), but that flowchart itself has not been JEA-signed.

تحرير_محل_النزاع=
Are the counts in the prior handoff (a) numerically correct, and (b) semantically correct — i.e., do the DEFINED / ABSENT labels hide material distinctions such as approved vs unapproved workflows, or real vs placeholder fees, or service-specific vs family-inherited documents?

السبب=
This program (§C) mandates verifying and correcting counts and requires status vocabularies that distinguish approved / unapproved / template / placeholder / absent / unknown for fees, workflows and documents.

الشرط=
An accurate corrected maturity CSV must (i) enumerate every service, (ii) apply the required status vocabularies, (iii) cite the file-line evidence for each classification.

المانع=
The prior handoff's binary DEFINED / ABSENT labels are insufficient — they cannot distinguish REAL_APPROVED vs REAL_UNAPPROVED, or SOURCE_DERIVED_APPROVED vs SOURCE_DERIVED_UNAPPROVED.

العلة=
Governance completeness. Publication (SG-01) depends on distinguishing services whose fees are real-but-unapproved from services whose fees are real-and-approved from services whose fees are placeholder-only.

القادح=
No signed JEA approval evidence exists in the repository for the workflows produced by SurveyWorkflowsSeeder or the fees produced by DrawingFeeMatrixSeeder / SolarFeeSeeder / ExcavationFeeSeeder / SiteSurveyFeesSeeder. Their sources are flowchart PDFs and manuals (كتاب التعليمات الفنية 2025, JORD-63, JORD-64, JORD-78, JORD-85), which are technical references, not JEA approval attestations.

الصحة=
Valid classification (each service, per feature): use the three required vocabularies (REAL_APPROVED / REAL_UNAPPROVED / PLACEHOLDER / ABSENT / UNKNOWN for fees; SOURCE_DERIVED_APPROVED / SOURCE_DERIVED_UNAPPROVED / TEMPLATE / PLACEHOLDER / ABSENT / UNKNOWN for workflows; SERVICE_SPECIFIC_APPROVED / SERVICE_SPECIFIC_UNAPPROVED / FAMILY_INHERITED / PLACEHOLDER / ABSENT / UNKNOWN for documents).

الفساد=
The prior CSV (02-Service-Inventory-and-Maturity-Matrix.csv) uses DEFINED / ABSENT — repairable by re-emitting with the correct vocabularies. Do not delete the prior CSV; keep both, mark the prior as superseded.

البطلان=
Using the binary DEFINED / ABSENT labels for the governance-critical publication decision in SG-01 would be invalid.

الأثر=
(1) SG-00-corrected-maturity-model.csv emits the required statuses per service. (2) SG-01 publication conditions can cite specific statuses (e.g., "PUBLICATION blocked if fee_status IN (PLACEHOLDER, REAL_UNAPPROVED, ABSENT, UNKNOWN)"). (3) The residual list explicitly enumerates services whose status is UNKNOWN and requires a JEA / product decision.

البقايا=
RES-SG00-03: Every service currently classified `_UNAPPROVED` requires a JEA decision to move to `_APPROVED`. Owner: product / JEA. Blocks: publication of that service (SG-01). Closure: signed decision attached to service's UAT record.

التعارض=
The prior handoff's summary counts (35 placeholder fees, 22 real fees) implicitly treat the 22 "real" fees as if they are equivalently approved. Repository evidence distinguishes technical-reference-cited fees (SITE_SURVEY 150 fils/lm etc.) from JEA-signed fees. None of the 22 has a signed JEA UAT reference in the repo.

الجمع=
Reconcilable: retain 22 "real" fees as REAL_UNAPPROVED (technically specified from manuals) — a distinct status from placeholder. This keeps the numeric total meaningful while surfacing the missing UAT attestation.

الترجيح=
Evidence tier 3 (approved technical standard) exists for some fees (كتاب التعليمات الفنية 2025 for exploration matrix; JORD-* manual references for drawing fees). Evidence tier 1-2 (JEA-signed decision, contractual requirement) is absent → default to REAL_UNAPPROVED, not REAL_APPROVED, in the absence of a signed decision.

التوقف=
Not stopped. The correction is a classification act.

EVIDENCE=
- backend/modules/JeaServices/Database/Seeders/ServicePlan2026Seeder.php:381-408 (placeholderSchema fills amount=0)
- backend/modules/JeaServices/Database/Seeders/ServiceFeeDefaultsSeeder.php:19,37,54-71 (fills amount=0 with 50000 JOD default)
- backend/database/seeders/DatabaseSeeder.php:59,121 (ServicePlan runs before ServiceFeeDefaults)
- backend/modules/JeaServices/Database/Seeders/DrawingsDocumentsSeeder.php:43-54,77 (15-doc manifest applied to 10 services DRW-P-001..010)
- backend/modules/JeaServices/Database/Seeders/Srv001PilotSeeder.php (SRV-001-only documents seeder — 2 documents)
- No repository file matching *uat*, *signed*, *approval-attestation* for the fee or workflow content

DECISION=
Emit `SG-00-corrected-maturity-model.csv` with per-service classifications using the required vocabularies. Mark the prior CSV (02-Service-Inventory-and-Maturity-Matrix.csv) as SUPERSEDED for the governance-critical columns while retaining it for the source-file trace columns.

Numeric restatement (unchanged from prior handoff where verified; refined semantics):
- TOTAL_SERVICES=57 (unchanged)
- SERVICES_WITH_REAL_APPROVED_FEE=0 (no signed JEA fee attestation exists in the repo)
- SERVICES_WITH_REAL_UNAPPROVED_FEE=22 (technically specified from manuals, not JEA-signed)
- SERVICES_WITH_PLACEHOLDER_FEE=35 (50000 JOD default from ServiceFeeDefaultsSeeder)
- SERVICES_WITH_SOURCE_DERIVED_APPROVED_WORKFLOW=0
- SERVICES_WITH_SOURCE_DERIVED_UNAPPROVED_WORKFLOW=7 (SRV-001, 002, 007, 008, 009, 012, 014 — flowchart-derived, not JEA-signed)
- SERVICES_WITH_TEMPLATE_WORKFLOW=50
- SERVICES_WITH_SERVICE_SPECIFIC_APPROVED_DOCUMENTS=0
- SERVICES_WITH_SERVICE_SPECIFIC_UNAPPROVED_DOCUMENTS=1 (SRV-001, 2 documents from Srv001PilotSeeder)
- SERVICES_WITH_FAMILY_INHERITED_DOCUMENTS=10 (DRW-P-001..010, 15-doc manifest)
- SERVICES_WITH_ABSENT_DOCUMENTS=46

IMPLEMENTATION_EFFECT=
SG-00-corrected-maturity-model.csv is created. No runtime changes.

MIGRATION_EFFECT=
None. SG-01 uses these statuses to define publication conditions.

TEST_EVIDENCE=
Not applicable.

OPEN_RESIDUALS=
- RES-SG00-03 (every _UNAPPROVED status pending JEA sign-off).
