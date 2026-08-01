# Duplicate-Consolidation Backlog

Phase 7 of the cleanup sprint. Records every duplicate group the
audit flagged as `BACKLOG` — real duplication that we deliberately
did NOT consolidate in this sprint because consolidation is
high-risk or premature.

**No entry on this page is described as fixed or closed.**

---

## BL-DG-04 · Submission gate sequence

| Field | Value |
|---|---|
| BACKLOG_ID | BL-DG-04 |
| CURRENT_IMPLEMENTATIONS | `ApplicationController::submit` (backend/modules/JeaServices/Http/Controllers/ApplicationController.php:272-330) hardcodes the gate order (schema → cross-cutting → per-service registry → capacity → sanction). `WorkflowEngine::submit` (backend/modules/JeaServices/Engine/WorkflowEngine.php:88-145) independently re-runs the cross-cutting subset inside its DB transaction for TOCTOU safety. |
| DUPLICATION_TYPE | BUSINESS_RULE — the acceptance sequence is a business decision expressed in two files. |
| CURRENT_RISK | Low. Both sites currently converge on the same order; the TOCTOU re-run inside `WorkflowEngine::submit` protects against races. A future contributor could add a gate to the controller without noticing that only the cross-cutting subset is re-run in the engine. |
| WHY_NOT_CONSOLIDATED_NOW | Only ONE caller (`ApplicationController::submit`) — extracting a `SubmissionGatePipeline` service today would move code without eliminating any duplication. Consolidation makes sense the moment a second submission entry point appears (admin manual entry, integration adapter, bulk upload). |
| CANONICAL_OWNER_CANDIDATE | `Modules\JeaServices\Engine\SubmissionGatePipeline` (new) owning the gate order + re-running the same set inside the WorkflowEngine transaction. |
| DEPENDENCIES | None functional. Needs test rewrite for the 4+ submit tests. |
| CHARACTERIZATION_TESTS_REQUIRED | Yes — the current submit flow has 12+ feature tests spread across `ApplicationSubmitTest`, `SubmissionRegressionTest`, `CapacityGuardTest`, `SanctionGuardTest`, `CadastralConflictGuardTest`, `OwnerMatchClearanceGuardTest`. Any consolidation MUST first pin the gate ORDER (specifically: SchemaValidator errors must exit before CapacityGuard errors are reported, so applicants see field errors before quota errors). |
| ACCEPTANCE_CRITERIA | (a) `SubmissionGatePipeline` service exists with a single public method returning an ordered array of errors; (b) `ApplicationController::submit` reduces to a validate-→-invoke-pipeline-→-transition sequence; (c) `WorkflowEngine::submit` calls the same pipeline instead of the CrossCutting subset; (d) all 12+ submit tests pass unchanged; (e) a new test proves that when a schema error AND a capacity error co-exist, only the schema error surfaces. |
| EFFORT | 2–3 dev days. |
| PRIORITY | Low today (single caller); rises to Medium when a second submission entry point is planned. |

---

## BL-DG-08 / BL-FE-DG-01 · Frontend discipline admin tables

| Field | Value |
|---|---|
| BACKLOG_ID | BL-DG-08 |
| CURRENT_IMPLEMENTATIONS | frontend/src/modules/JeaDiscipline/pages/ComplaintsAdmin.tsx (429 LOC) + LegalFinesAdmin.tsx (490 LOC) + SupervisionTransfersAdmin.tsx (497 LOC) — three admin pages that share ~400 LOC of state machine + confirm-dialog + manual cache invalidation. |
| DUPLICATION_TYPE | STRUCTURAL — same loading/error/save state machine, different data shapes. |
| CURRENT_RISK | Low. Each page has its own vitest coverage; the duplicated boilerplate is easy to read but tedious to maintain. |
| WHY_NOT_CONSOLIDATED_NOW | The per-page forms are domain-specific and complex (complaint decision workflow vs legal-fine tier ladder vs supervision-transfer approval). A generic `DataAdminPage<T>` extraction requires render-prop rewiring + three test-file rewrites. Not a security or correctness defect. |
| CANONICAL_OWNER_CANDIDATE | `frontend/src/platform/ui/DataAdminPage.tsx` (new) — a generic list/form/actions container with render-prop rows + form body + action buttons. |
| DEPENDENCIES | React Query migration for these pages would be ideal (currently manual `useState` + cache invalidation). |
| CHARACTERIZATION_TESTS_REQUIRED | Yes — each page has 4–7 vitest tests that must pass unchanged after extraction. |
| ACCEPTANCE_CRITERIA | (a) `DataAdminPage<T>` component exists with render-prop rows + form body + action buttons; (b) three admin pages reduce to configuration + render-prop values; (c) all existing vitest tests pass; (d) no visible UX change (accessibility + i18n keys preserved). |
| EFFORT | 2–3 dev days including test rewrites. |
| PRIORITY | Low. Nice-to-have maintenance win. |

---

## BL-DG-14 · Hidden cross-JEA-module `app(FQCN)` resolves

| Field | Value |
|---|---|
| BACKLOG_ID | BL-DG-14 |
| CURRENT_IMPLEMENTATIONS | Four `app(\Modules\<Other>\...::class)` sites made visible by CS-05's strengthened detector: `ApplicationController::show:126 → QuotaLedger::overflowSurchargeFor`, `ApplicationController::submit:305 → CapacityGuard::validate`, `ApplicationController::submit:325 → SanctionGuard::validate`, `Application::booted::deleted:75 → QuotaLedger::releaseFor`. Also `ComplaintController:151 → SupervisionTransferService` (same module, less concerning). |
| DUPLICATION_TYPE | STRUCTURAL — repeated sibling-module container resolutions. |
| CURRENT_RISK | Moderate. Post-CS-05 they are documented + allowlisted, but each hidden resolve is a Platform→JEA-sibling coupling that survives only because the boundary test's allowlist mechanism grants an explicit exception. |
| WHY_NOT_CONSOLIDATED_NOW | Retirement requires the CS-05-BL-1 approach: contribute each sibling guard/service to a Platform-owned registry (e.g. `JeaDiscipline::register()` contributes `SanctionGuard` to `CrossCuttingSubmissionGuardRegistry`; `JeaProjects::register()` contributes `CapacityGuard`; the model boot hook emits a domain event that `JeaProjects` listens for). Each conversion is one small PR with its own tests. Doing all four in a single cleanup sprint would exceed "narrowly scoped." |
| CANONICAL_OWNER_CANDIDATE | Extended `Modules\JeaServices\Engine\CrossCuttingSubmissionGuardRegistry` accepting external contributions + a `Modules\JeaServices\Events\ApplicationDeleted` domain event listened by `JeaProjects`. |
| DEPENDENCIES | Event dispatcher must run before soft-delete commit (for the `booted::deleted` path). |
| CHARACTERIZATION_TESTS_REQUIRED | Yes — the existing capacity, sanction, and quota-release tests must pass unchanged after the resolves become registered contributions. |
| ACCEPTANCE_CRITERIA | (a) `SM_ALLOWED_IMPORTS` in `SiblingModuleBoundariesTest` shrinks by at least the four `app(FQCN)` entries; (b) new registry accepts contributions from `JeaDiscipline` + `JeaProjects` service providers; (c) `Application::booted::deleted` dispatches a domain event and `JeaProjects` has a listener; (d) `test_no_undocumented_cross_jea_module_imports` still passes with fewer allowlist entries. |
| EFFORT | 1 dev week across 4 sequential PRs (one per resolve site). |
| PRIORITY | Medium. Increases when a fifth sibling coupling is proposed — that's the signal to build the registry rather than add a fifth allowlist entry. |

---

## Register invariants

* **None of the three backlog items above is fixed by any commit in
  this cleanup sprint.** Each entry lives here specifically because
  the audit determined consolidation would be premature or
  high-risk in the current sprint's scope.
* Every entry has ACCEPTANCE_CRITERIA sufficient to close it out
  when someone picks it up.
* Every entry has a REVIEW TRIGGER — a specific condition that
  should promote it from backlog to active work.
