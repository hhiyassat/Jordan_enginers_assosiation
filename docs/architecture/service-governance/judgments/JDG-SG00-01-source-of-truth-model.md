JUDGMENT_ID=JDG-SG00-01
TITLE=Service configuration source-of-truth model
SCOPE=architecture/service-governance/SG-00
OWNER=service-governance-remediation

الوضع=
The prior handoff (docs/handoff/service-architecture/06-Service-Definition-Source-of-Truth-Analysis.md §1) states that "the runtime source of truth for a service is the service_definitions.schema JSON column". Repository evidence at 83d3a459 shows: (a) schema JSON is authoritative for fields/documents/fee/workflow configuration data, but (b) generic-engine code (SchemaValidator, FeeCalculator, WorkflowEngine, StageActions), (c) cross-cutting guards (CadastralConflictGuard, OwnerMatchClearanceGuard, SanctionGuard, CapacityGuard), (d) service-specific extension code (Srv001Guard, WellsCountCalculator, NetDepthTable, ExplorationRequirementMatrix), and (e) sibling-module + external state (quota_consumptions, sanctions, payment_callbacks) jointly determine a submission's outcome. Seeders (ServicePlan2026Seeder, ServiceFeeDefaultsSeeder, SurveyWorkflowsSeeder, CatalogWorkflowsSeeder, DrawingFeeMatrixSeeder, SolarFeeSeeder, ExcavationFeeSeeder, SiteSurveyFeesSeeder, Srv001PilotSeeder, DrawingsDocumentsSeeder, DrawingEngineerPickerSeeder) write the build-time baseline; admin edits mutate the DB row afterwards. No table records the transformation history.

تحرير_محل_النزاع=
Is `service_definitions.schema` alone the source of truth for the complete business behaviour of a service, or is it the source of the CONFIGURATION portion of a distributed judgment that also depends on engine code, guards, extensions, and external state?

السبب=
Downstream deliverables (SG-01..SG-06) depend on a precise mental model of what "the service" is. An incorrect single-source model would treat schema JSON edits as sufficient for governance, missing the extension-code and cross-cutting-state dimensions.

الشرط=
A correct source-of-truth statement must (a) name the configuration source, (b) name the build-time baseline source, (c) name the joint runtime-judgment sources, (d) explicitly acknowledge drift between seeded baseline and DB current state.

المانع=
Restating the single-source claim without qualification would violate this Phase's own reconciliation mandate (§B.A).

العلة=
Historical reproducibility and governance completeness. If a future contributor believes schema JSON is the complete source, they will not add extension-code review to admin-edit workflows and will not track calculator provenance separately.

القادح=
The prior handoff itself (`06-...md` §5) enumerates SRV-001 extension code that clearly shapes runtime behaviour outside the schema JSON. The claim in §1 of that same file is therefore internally inconsistent.

الصحة=
Valid statement: "`service_definitions.schema` is the current runtime service-CONFIGURATION source. Complete application behaviour is jointly produced by schema configuration + generic engine version + cross-cutting guards + optional service extensions + external and sibling-module state. Build-time baseline resides in seeders. Runtime current state resides in the database. Drift risk is present."

الفساد=
Retaining the older phrasing "schema JSON is the only source of truth" while separately documenting extensions would leave a self-contradiction in the handoff — a defective but repairable state, to be repaired by SG-00.

البطلان=
Publishing SG-01..SG-06 without correcting this statement would produce governance whose foundations are provably inconsistent.

الأثر=
(1) SG-00 must explicitly restate the source-of-truth model. (2) Downstream phases must treat "the service" as a composite of schema + engine + guards + extensions + external state. (3) Governance mechanisms (lifecycle, versioning, snapshots) must cover each dimension separately.

البقايا=
RES-SG00-01: `service_definition_versions` (Phase SG-03) must decide whether it snapshots ONLY the schema JSON, or ALSO the extension declarations. Owner: SG-03. Risk: HIGH if partial snapshots create false reproducibility. Blocks: SG-03 completion. Closure: SG-03 judgment record.

التعارض=
Prior handoff `06-...md` §1 (single-source claim) vs `06-...md` §5 (enumerated extensions). Both cannot be true simultaneously.

الجمع=
Not reconcilable — the claims are mutually exclusive as literally written.

الترجيح=
Evidence tier 5-6 (runtime safety + repository implementation) requires the enumerated-extensions view. The single-source claim rests on the intent of the migration comment ("schema JSON column is the source of truth"), which reflects a BR-001 aspirational statement about schema-driven design, not an audited factual claim about complete runtime behaviour.

التوقف=
Not stopped. The correction is a documentation act with no business-authority dependency.

EVIDENCE=
- backend/modules/JeaServices/Database/Migrations/2025_01_01_000003_create_service_definitions_table.php (schema JSON column + BR-001 aspirational comment)
- backend/modules/JeaServices/Engine/Srv001Guard.php (extension code shaping behaviour)
- backend/modules/JeaServices/Engine/WellsCountCalculator.php header (PROVISIONAL provenance)
- backend/modules/JeaServices/Engine/NetDepthTable.php header (PROVISIONAL provenance)
- backend/modules/JeaServices/Engine/CrossCuttingSubmissionPipeline.php (guards operating outside schema)
- backend/modules/JeaServices/Database/Seeders/ServiceFeeDefaultsSeeder.php:37 (50000 JOD placeholder default applied after specific fee seeders)
- docs/handoff/service-architecture/06-Service-Definition-Source-of-Truth-Analysis.md §1 vs §5 (internal contradiction)

DECISION=
Adopt the distributed source-of-truth model. The corrected statement to use across all governance documents:

    SERVICE_CONFIGURATION_SOURCE=DATABASE_SCHEMA_ROW
    BUILD_BASELINE_SOURCE=SEEDERS
    COMPLETE_JUDGMENT_SOURCE=DISTRIBUTED (schema + engine + guards + extensions + external state)
    DRIFT_RISK=PRESENT (no snapshot / no rule-version tracking yet)

IMPLEMENTATION_EFFECT=
SG-00-handoff-reconciliation.md restates the model and marks the prior single-source phrasing as SUPERSEDED. No runtime code changes.

MIGRATION_EFFECT=
None. SG-03 will build the schema-snapshot mechanism using this model; SG-04 will build the rule-version mechanism.

TEST_EVIDENCE=
Not applicable — documentation-only judgment.

OPEN_RESIDUALS=
- RES-SG00-01 (scope of version snapshot — decided in SG-03).
