# Service Definition — Source of Truth Analysis

**Handoff:** `ESP_V2_SERVICE_ARCHITECTURE_RUNTIME_MODEL`
**HEAD:** `f3fc366d8effed8f11fa2787fb6629a339ebfbfb`
**Read-only:** yes.

---

## 1. Canonical answer

The runtime source of truth for a service is the **`service_definitions.schema` JSON column** in PostgreSQL.

The migration that creates the table declares this explicitly:

* File: `backend/modules/JeaServices/Database/Migrations/2025_01_01_000003_create_service_definitions_table.php`
* Header comment: *"BR-001: Schema-driven engine. The schema JSON column is the source of truth."*

At runtime every generic component (ApplicationController, WorkflowEngine, FeeCalculator, SchemaValidator, frontend Apply.tsx) reads this column and dispatches on its contents. **No service-specific code path** consults any file, ORM subclass, or hardcoded map.

## 2. What `schema` contains

The JSON object contains four top-level sections consumed by the generic engine:

| Section | Consumer | Purpose |
|---|---|---|
| `fields[]` | `SchemaValidator::validateData`, frontend `Apply.tsx` renderer | Form fields — id, type, label, required, validation |
| `documents[]` | `SchemaValidator::validateDocuments` | Required/optional documents with max_size limits |
| `fee` | `FeeCalculator::calculateBreakdown` | Fee type (`fixed`/`tiered`/`formula`/`matrix`/`per_unit`) + rate + surcharges |
| `workflow.stages[]` | `WorkflowEngine::submit/claim/decide` | Ordered stages with `role`, `sla_hours`, `notifications`, `can_request_modifications` |

Optional service-level extensions observed:

* `certificate_template` — path or slug used by `CertificatesController::downloadPdf`
* `quota_basis` — used by `JeaProjects\CapacityGuard` to select the discipline family (e.g. `materials_testing`)
* Per-stage flag such as `external: true` (used by `soilProposedWorkflow` for SRV-001 first-auditor-is-external)

## 3. Population sources (build → runtime)

Every populated row on `service_definitions` traces to one of two seeder trees:

### 3.1 Base catalog

* `ServicePlan2026Seeder` — creates all 57 service rows plus the 7 parent (JEA-*) tiles. Fills each service with a `placeholderSchema` (empty fields, empty documents, empty workflow, and a placeholder 50,000 JOD fee where no real fee seeder overrides it).

### 3.2 Overrides (in order)

Applied after the base catalog by `DatabaseSeeder`:

| Seeder | Overrides |
|---|---|
| `Srv001PilotSeeder` | SRV-001 — 28 fields, 2 documents, project-sector routing signal |
| `SurveyWorkflowsSeeder` | 7 real workflow builders (SRV-001, SRV-002, SRV-007, SRV-008, SRV-009, SRV-012, SRV-014) — sources are drawio flowchart PDFs under `flowcahrt/` |
| `CatalogWorkflowsSeeder` | 43 catalog:2026 template workflows (drawings, drawingsSafety, drawingsEnhanced, drawingsSimple, financial, certificate, certificateSite, engineer, board, directResponse, inspection) applied to remaining services |
| `DrawingsDocumentsSeeder` | 15-document manifest applied to DRW-P-001..010 |
| `DrawingEngineerPickerSeeder` | engineer_picker field added to DRW-P-* |
| `DrawingFeeMatrixSeeder` | 12 DRW-P matrix fees (governorate × building_class) |
| `SolarFeeSeeder` | DRW-P-006 solar override (4 JOD/kW) |
| `ExcavationFeeSeeder` | SRV-007 + SRV-012 (3.5 JOD/m²) |
| `SiteSurveyFeesSeeder` | SRV-001..006 (150 fils/lm + 1% surcharge) |
| `ManualReferencesSeeder` + `ManualReferenceLinksSeeder` | JORD tickets linked to fields/documents |

## 4. Layers that do NOT carry service knowledge

The following layers are strictly generic — they receive service data from the schema JSON at runtime and never hardcode service identity:

* **Controllers**: `ApplicationController`, `ServiceCatalogController`, `PaymentsController`, `CertificatesController`, `ReviewQueueController`, `ReviewDashboardController`, `ManualReferenceController` — no service-code branching.
* **Engines**: `WorkflowEngine`, `FeeCalculator`, `SchemaValidator`, `SchemaStructureValidator`, `StageActions`, `CrossCuttingSubmissionPipeline`, `CadastralConflictGuard`, `OwnerMatchClearanceGuard`.
* **Frontend**: the full `frontend/src/modules/JeaServices/pages/` tree renders 100% from schema. Explicit verification (agent-3 output `/tmp/svc-frontend.txt`, 478 lines) found **zero** `if (code === 'SRV-...')` branches; only two hardcoded field IDs exist (`area_m2`, `governorate`) inside `Apply.tsx` for project auto-fill.

## 5. Layers that DO carry service knowledge

Exactly one service — SRV-001 — has any service-specific runtime code:

| Component | File | Nature |
|---|---|---|
| `Srv001Guard` | `backend/modules/JeaServices/Engine/Srv001Guard.php` | Registered in `JeaServicesServiceProvider::register` L91 via `ServiceSubmissionGuardRegistry` map `[Srv001Guard::SERVICE_CODE => new Srv001Guard()]`. Branches on `SERVICE_CODE === 'SRV-001'` implicitly through the registry lookup; internally enforces routing (project_sector=حكومي → SRV-006), exploration matrix validation (JORD-91), and special-study tagging. |
| `WellsCountCalculator` | `backend/modules/JeaServices/Engine/WellsCountCalculator.php` | Pure calculator called only by `Srv001Guard::meetingDerivedValues()`. Marked **PROVISIONAL** in file header: source is محضر اجتماع 2026-07-26 §X — **not JEA-approved**. |
| `NetDepthTable` | `backend/modules/JeaServices/Engine/NetDepthTable.php` | Pure lookup called only by `Srv001Guard::meetingDerivedValues()`. Marked **PROVISIONAL** in file header: source is محضر اجتماع 2026-07-26 §XI. Documented invariant issue: `third + two_thirds ≠ total` awaiting JEA clarification. |
| `ExplorationRequirementMatrix` | `backend/modules/JeaServices/Engine/ExplorationRequirementMatrix.php` | Encodes جدول 4-1 from كتاب التعليمات الفنية 2025. Pure lookup called only by `Srv001Guard::validate()`. |

The registry dispatch pattern in `ServiceSubmissionGuardRegistry` L38-42 means a service without a registered guard hits nothing service-specific on submission — only the cross-cutting pipeline runs.

The `WorkflowEngine::generateCertificateNumber` (L665+) does read the service code, but only to embed it in a formatted serial string — not to branch business logic.

## 6. Divergence from a fully generic model

The following aspects of a service currently CANNOT be expressed purely inside `schema` JSON and require code contribution:

1. **Service-specific submission guards** (only SRV-001 has one). New guards must be added to the `ServiceSubmissionGuardRegistry` singleton binding.
2. **Service-specific pre-persist calculators** (WellsCountCalculator, NetDepthTable, ExplorationRequirementMatrix — all SRV-001 only). These persist derived values into `app.data` at submit time.
3. **New workflow-stage actions** — `StageActions::run($action)` currently supports a fixed set of action names. A new action name in `schema.workflow.stages[].actions[]` would fail unless added to `StageActions`.
4. **New fee-formula families** — `FeeCalculator` dispatches on 5 `type` values. A sixth (e.g. per-node graph fee) would need a new branch.
5. **New notification kinds** — `JeaNotificationService::emit*` methods enumerate kinds; a new kind requires an emitter method.

## 7. Confidence

* Schema-as-source-of-truth (**HIGH**) — verified by migration comment + all-consumers trace + frontend audit.
* Only-SRV-001-has-service-specific-code (**HIGH**) — verified via the runtime reachability audit (`/tmp/svc-runtime.txt`) and the frontend audit (`/tmp/svc-frontend.txt`).
* Provenance of PROVISIONAL calculators (**MEDIUM**) — the file headers cite meeting minutes but no JEA-approved evidence exists in the repository for `WellsCountCalculator` bands or `NetDepthTable` decomposition.
