# Handoff Evidence Index

**Handoff:** `ESP_V2_SERVICE_ARCHITECTURE_RUNTIME_MODEL`
**HEAD:** `f3fc366d8effed8f11fa2787fb6629a339ebfbfb`

Every finding in the primary handoff and supporting deliverables was produced from one of the four evidence sources listed below, plus in-repo file reads. Each source is either a) an agent-generated report (paths under `/tmp/`), b) an in-repo file at HEAD, or c) an in-repo document from prior sprints referenced verbatim.

---

## A. Agent-generated evidence (out-of-repo)

| Source | Lines | Contents |
|---|---|---|
| `/tmp/svc-inventory.txt` | 1745 | Full service inventory (all 57 services + 7 parent tiles). Per-service: catalog source, schema source, documents source, fee source, workflow source, guard source, calculation source, reference source, frontend source, test source, runtime entrypoint, activation status. Produced by the Service catalog inventory agent. |
| `/tmp/svc-runtime.txt` | 838 | Backend runtime + reachability audit. Per-component: file path + line ranges, routes, entrypoint, call chain, container binding, database deps, config deps, frontend deps, test caller, runtime-reachable classification, confidence. Produced by the Backend runtime + reachability agent. |
| `/tmp/svc-frontend.txt` | 478 | Frontend service-code branching audit. Verdict: **PURELY DYNAMIC** — zero `if (code === 'SRV-...')` conditionals. 21 files analysed: 15 GENERIC_DYNAMIC_RENDERER, 4 GENERIC_CAPABILITY, 1 SCHEMA_TYPE_DEFINITION, 0 SERVICE_SPECIFIC. Only hardcoded field IDs: `area_m2` and `governorate` in `Apply.tsx`. Produced by the Frontend service-code branching agent. |
| `/tmp/svc-data.txt` | 1768 | Data ownership + persistence audit. 24 tables mapped across 5 modules (JeaServices, JeaProjects, JeaDiscipline, JeaDues, Nashmi). Per-table: migration file, columns, logical owner, writers, readers, versioning, race analysis, risk assessment. Confirms: no `CalculationSnapshot`, no `RuleVersion`, no `schema_version`. Produced by the Data ownership + storage patterns agent. |

## B. In-repo files consulted at HEAD

### B.1 Runtime engine

* `backend/modules/JeaServices/Providers/JeaServicesServiceProvider.php` — singleton bindings (`CrossCuttingSubmissionPipeline`, `ServiceSubmissionGuardRegistry`, `JeaMembershipVerifier`, `ServiceLockLookup`, `ApplicationLookup`).
* `backend/modules/JeaServices/Engine/Srv001Guard.php` — 204 lines. The only service-specific guard currently registered.
* `backend/modules/JeaServices/Engine/WellsCountCalculator.php` — 92 lines. PROVISIONAL calculator; source: محضر اجتماع 2026-07-26 §X.
* `backend/modules/JeaServices/Engine/NetDepthTable.php` — 96 lines. PROVISIONAL calculator; source: محضر اجتماع 2026-07-26 §XI.
* `backend/modules/JeaServices/Engine/ExplorationRequirementMatrix.php` — 165 lines. Encodes جدول 4-1 from كتاب التعليمات الفنية 2025.
* `backend/modules/JeaServices/Engine/SchemaValidator.php` — schema-driven, no service branching.
* `backend/modules/JeaServices/Engine/FeeCalculator.php` — dispatches on `schema.fee.type` (fixed / tiered / formula / matrix / per_unit).
* `backend/modules/JeaServices/Engine/WorkflowEngine.php` — 638+ lines. Only service-code-aware method is `generateCertificateNumber` (formatting only).
* `backend/modules/JeaServices/Engine/ServiceSubmissionGuardRegistry.php` — registry dispatch pattern (L38-42).
* `backend/modules/JeaServices/Engine/CrossCuttingSubmissionPipeline.php` — pipeline of service-independent guards.
* `backend/modules/JeaServices/Engine/CadastralConflictGuard.php` — cross-org detector (CC-001).
* `backend/modules/JeaServices/Engine/OwnerMatchClearanceGuard.php` — same-owner clearance requirement (CC-002).

### B.2 Controllers

* `backend/modules/JeaServices/Http/Controllers/ServiceCatalogController.php` — catalog CRUD.
* `backend/modules/JeaServices/Http/Controllers/ApplicationController.php` — application lifecycle.
* `backend/modules/JeaServices/Http/Controllers/PaymentsController.php`, `PaymentCallbackController.php`, `CertificatesController.php`, `ReviewQueueController.php`, `ReviewDashboardController.php`, `ManualReferenceController.php`, `OfficeRegistrationController.php`, `OfficeSettingsController.php`.

### B.3 Seeders

* `backend/modules/JeaServices/Database/Seeders/ServicePlan2026Seeder.php` — 57 services + 7 parents.
* `backend/modules/JeaServices/Database/Seeders/Srv001PilotSeeder.php` — 28 fields + 2 documents for SRV-001.
* `backend/modules/JeaServices/Database/Seeders/SurveyWorkflowsSeeder.php` — 7 real workflows.
* `backend/modules/JeaServices/Database/Seeders/CatalogWorkflowsSeeder.php` — 43 template workflows.
* `backend/modules/JeaServices/Database/Seeders/DrawingFeeMatrixSeeder.php` — 12 DRW-P fees.
* `backend/modules/JeaServices/Database/Seeders/ExcavationFeeSeeder.php` — SRV-007 + SRV-012.
* `backend/modules/JeaServices/Database/Seeders/SolarFeeSeeder.php` — DRW-P-006 override.
* `backend/modules/JeaServices/Database/Seeders/SiteSurveyFeesSeeder.php` — SRV-001..006.
* `backend/modules/JeaServices/Database/Seeders/DrawingsDocumentsSeeder.php` — 15-doc manifest.
* `backend/modules/JeaServices/Database/Seeders/DrawingEngineerPickerSeeder.php` — engineer_picker field.
* `backend/modules/JeaServices/Database/Seeders/ManualReferencesSeeder.php`, `ManualReferenceLinksSeeder.php`.

### B.4 Migrations (source-of-truth statement)

* `backend/modules/JeaServices/Database/Migrations/2025_01_01_000003_create_service_definitions_table.php` — header comment: *"BR-001: Schema-driven engine. The schema JSON column is the source of truth."*
* `backend/modules/JeaServices/Database/Migrations/2025_01_01_000004_create_applications_table.php` — application schema + JSON `data` column.

### B.5 Frontend files (audited by frontend agent)

* `frontend/src/modules/JeaServices/pages/Apply.tsx` — schema-driven renderer. Only hardcoded field IDs: `area_m2`, `governorate` (auto-fill from project).
* Full page tree under `frontend/src/modules/JeaServices/pages/`.

## C. Prior-sprint documents referenced

| Path | Referenced in |
|---|---|
| `docs/remediation/cleanup-sprint/final-cleanup-report.md` | Recap of validation gates (backend SQLite + Postgres + PHPStan + Vitest + Playwright + Architecture + Security) and CLEANUP_FINAL_HEAD baseline. |
| `docs/remediation/cleanup-sprint/duplicate-consolidation-backlog.md` | BL-DG-04 (submission gate), BL-DG-08 (admin tables), BL-DG-14 (cross-module resolves) referenced by R-09. |
| `docs/remediation/cleanup-sprint/justified-duplication-register.md` | DG-02 payment split, DG-03 counters, DG-05 SchemaValidator vs Srv001Guard, DG-06 notification split, DG-07 integration middleware, DG-10 RespondsWithLockedService, DG-11 CapacityGuard vs QuotaLedger, DG-12 FeeCalculator vs QuotaLedger — reproduced in `08-Service-Architecture-Risks-and-Decisions.md` §Cross-cutting decisions currently frozen. |
| `docs/remediation/cleanup-sprint/CL-04-dg01-org-scoped-lookups.md` | `BelongsToOrganization::findForOrganizationOrFail` helper referenced by data-ownership register. |
| `docs/remediation/cleanup-sprint/CL-06-dg13-user-management-scoped-lookups.md` | User model deliberately not on `BelongsToOrganization` — referenced by multi-tenant scope discussion. |

## D. Test evidence relied upon

The following test names appear as citations across the reachability matrix (03) and data-ownership register (05). Tests were **not executed** as part of this read-only handoff; their names are cited from the code index.

* `ServiceSubmissionGuardRegistryTest`
* `Srv001GuardTest`
* `CadastralConflictGuardTest`
* `OwnerMatchClearanceGuardTest`
* `SanctionGuardTest`
* `CapacityGuardTest`
* `SchemaValidatorTest`
* `FeeCalculatorTest`
* `WorkflowEngineTest`
* `CertificateSerialAllocationTest`
* `ApplicationReferenceSerialTest`
* `RealConcurrencyOnPostgresTest`
* `ConfirmPaymentFromReceiptTest`
* `PaymentCallbackControllerTest`
* `PaymentInitiateAndManualConfirmTest`
* `CertificateVerifyTest`
* `SharedServiceCatalogTest`
* `AdminServicesIndexTest`
* `ServiceLockingTest`
* `WellsCountCalculatorTest`
* `NetDepthTableTest`
* `ExplorationRequirementMatrixTest`
* `QuotaConsumptionOnApprovalTest`
* `SubmissionRegressionTest`
* `CrossTenantIsolationTest`
* `NashmiSecurityTest`
* `OfficeRegistrationCaptchaTest`
* `OfficeRegistrationApprovalTest`

## E. Reproducing the audit

To regenerate the four `/tmp/svc-*.txt` reports, re-launch the four Explore agents at HEAD `f3fc366d8effed8f11fa2787fb6629a339ebfbfb` with the same charter:

1. **Service catalog inventory** → `/tmp/svc-inventory.txt`
2. **Backend runtime + reachability** → `/tmp/svc-runtime.txt`
3. **Frontend service-code branching** → `/tmp/svc-frontend.txt`
4. **Data ownership + storage patterns** → `/tmp/svc-data.txt`

The agents were run in isolation-mode="worktree" so no repo state was modified during evidence gathering.
