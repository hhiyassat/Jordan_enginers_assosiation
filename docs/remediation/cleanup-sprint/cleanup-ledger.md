# Cleanup Sprint Ledger

Sprint branch: `remediation/architecture-security-production-readiness`
Sprint start HEAD: `89833fb070cc71fa0c11cb27e5ac7f8ce550122a`

## Phase 0 — Decision table (parsed from audit deliverables)

| AUDIT_ID | PATHS_OR_SYMBOLS | AUDIT_CLASSIFICATION | AUDIT_RECOMMENDATION | CONFIDENCE | EVIDENCE | PLANNED_ACTION |
|---|---|---|---|---|---|---|
| U-01 | `backend/app/Contracts/Services/ServiceDefinitionSnapshot.php` | DEAD_CONFIRMED | DELETE | HIGH | `grep -RIn ServiceDefinitionSnapshot backend/{app,modules,plugins,integrations}` returns only the file itself + a doc-comment mention in `NashmiIntegrationService.php:18` (references deleted pushService). Zero `use`, `new`, `app()`, type-hint. | Phase 1 / CL-01 → delete |
| U-03 | `backend/config/esp.php` dead keys: `default_sla_hours`, `max_upload_size_mb`, `rate_limit_login`, `rate_limit_api` | DEAD_CONFIRMED | DELETE_AFTER_DEPRECATION_WINDOW | HIGH | data-sources agent verified zero `config()` readers; already commented as deprecated per P-2 protocol. | Phase 2 / CL-02 → remove |
| U-02 | `backend/modules/JeaServices/Engine/HttpJeaMembershipVerifier.php` | RESERVED_EXTENSION_WITH_CONTRACT | KEEP | HIGH | Not bound; ProductionSafety enforces "not Fake" in prod; config keys `jea.membership_api.*` exist and are read ONLY by this class; blocked by BLK-02 (real JEA endpoint URL + auth). | Phase 3 / CL-03 → RESERVED_EXTENSION_WITH_CONTRACT (document) |
| DG-01 | Org-scoped model lookups at 7 sites (PaymentsController + ReviewQueueController×3 + CertificatesController + UserManagementController×2) | EXACT | CONSOLIDATE | HIGH | Same `Model::forOrganization($orgId)->findOrFail($id)` pattern. `ApplicationController::findAccessible()` is a private helper of the same shape. | Phase 4 / CL-04 → extract shared helper |
| DG-09 | Engineer/Project scoped lookup: EngineerController show/quota + ProjectController show | STRUCTURAL | CONSOLIDATE | HIGH | Same lookup pattern extended to `office_user_id` / `owner_user_id` scope columns. | Phase 5a / CL-05 → apply helper |
| DG-13 | UserManagement scoped lookups + others already in DG-01/DG-09 | STRUCTURAL | CONSOLIDATE | HIGH | Superset acknowledged by audit as "same shared helper suggested in DG-01". | Phase 5b / CL-06 → confirm coverage / document |
| DG-02 | `WorkflowEngine::confirmPayment` vs `confirmPaymentFromReceipt` + 2 controller entry points | BEHAVIORAL | KEEP_SEPARATE | HIGH | CS-03 split is intentional. | Phase 6 register |
| DG-03 | `WorkflowEngine::generateCertificateNumber` vs `Application::generateReference` | STRUCTURAL | KEEP_SEPARATE | HIGH | Two callsites; premature abstraction to extract SerialGenerator. | Phase 6 register |
| DG-05 | `SchemaValidator` vs `Srv001Guard` | STRUCTURAL | KEEP_SEPARATE | HIGH | Two distinct concerns (generic vs SRV-001 rules). | Phase 6 register |
| DG-06 | `JeaNotificationService::send` vs Platform `NotificationService::dispatch`/`sendToUser` | BEHAVIORAL | KEEP_SEPARATE | HIGH | H-07 boundary split. | Phase 6 register |
| DG-07 | Nashmi `ValidateIntegrationKey` vs GSB `GsbIpWhitelist` | STRUCTURAL | KEEP_SEPARATE | HIGH | Different protocols. | Phase 6 register |
| DG-10 | `RespondsWithLockedService` trait consumers | NOT_DUPLICATE | KEEP_SEPARATE | HIGH | Already consolidated via trait. | Phase 6 register (no-op) |
| DG-11 | `CapacityGuard` (gate) vs `QuotaLedger` (ledger) | BEHAVIORAL | KEEP_SEPARATE | HIGH | Different scopes; fusion would break `Application::booted::deleted → releaseFor`. | Phase 6 register |
| DG-12 | `FeeCalculator` (schema fees) vs `QuotaLedger::overflowSurchargeFor` (quota surcharge) | BEHAVIORAL | KEEP_SEPARATE | HIGH | Different data sources. | Phase 6 register |
| DG-04 | Submission gate sequence (ApplicationController::submit hardcoded order + WorkflowEngine TOCTOU re-run) | BUSINESS_RULE | BACKLOG | HIGH | Only one caller today; extract if a second appears. | Phase 7 backlog |
| DG-08 / FE-DG-01 | 3 discipline admin pages (ComplaintsAdmin, LegalFinesAdmin, SupervisionTransfersAdmin) share ~400 LOC | STRUCTURAL | BACKLOG | HIGH | Complex per-page forms + test coverage; extract `DataAdminPage<T>` in a dedicated PR. | Phase 7 backlog |
| DG-14 | 4 hidden `app(\Modules\<Other>\...)` cross-JEA-module resolves | STRUCTURAL | BACKLOG | HIGH | CS-05 made visible + allowlisted; retirement is the CS-05-BL-1 backlog item. | Phase 7 backlog |
| FE-DG-02 | `ServiceList` (applicant) vs `ServicesList` (admin) | BEHAVIORAL | KEEP_SEPARATE | HIGH | Different intents; already-distinct names. | Phase 6 register |
| FE-DG-03 | `api/jea/hooks.ts` vs `api/platform/hooks.ts` | STRUCTURAL | KEEP_SEPARATE | HIGH | Workstream-6 intentional split; barrels merge. | Phase 6 register |
| FE-DG-04 | Applicant/Admin/Reviewer Dashboards | BEHAVIORAL | KEEP_SEPARATE | HIGH | Zero code overlap beyond hero+stat layout. | Phase 6 register |

## Extracted counts vs audit totals

| Category | Audit total | Rows in decision table |
|---|---|---|
| DEAD_CONFIRMED files | 1 | 1 (U-01) |
| DEAD_CONFIRMED symbols total (class + config keys) | 5 | 1 class + 4 keys = 5 |
| UNFINISHED_SCAFFOLD | 1 | 1 (U-02) |
| CONSOLIDATE groups | 3 | DG-01 + DG-09 + DG-13 = 3 |
| KEEP_SEPARATE backend groups | 8 | DG-02, DG-03, DG-05, DG-06, DG-07, DG-10, DG-11, DG-12 = 8 |
| BACKLOG groups | 3 | DG-04, DG-08/FE-DG-01, DG-14 = 3 |

Counts match audit totals. Proceeding to execution.

Frontend KEEP_SEPARATE groups (FE-DG-02, FE-DG-03, FE-DG-04) are recorded in the Phase 6 register as **supporting entries** — the audit's `KEEP_SEPARATE_COUNT=8` referred to backend groups; the three frontend groups are additional no-consolidation decisions.

## Item log (populated as phases complete)

| Phase | Report | Status | Commit | Notes |
|---|---|---|---|---|
| CL-01 | [CL-01-dead-service-definition-snapshot.md](CL-01-dead-service-definition-snapshot.md) | done | `38a39a4` | Deleted the DTO + BoundariesTest doc-comment cleanup. |
| CL-02 | [CL-02-unused-config-keys.md](CL-02-unused-config-keys.md) | superseded_with_evidence | `3be07ed` | Keys already absent; deprecation comment locked. |
| CL-03 | [CL-03-unfinished-scaffold-decision.md](CL-03-unfinished-scaffold-decision.md) | reserved_extension_with_contract | `0a44c38` | HttpJeaMembershipVerifier: BLK-02 blocker. |
| CL-04 | [CL-04-dg01-org-scoped-lookups.md](CL-04-dg01-org-scoped-lookups.md) | done | `bb748fb` | Consolidated 7 Application sites via `findForOrganizationOrFail` helper. |
| CL-05 | [CL-05-dg09-engineer-project-scoped-lookups.md](CL-05-dg09-engineer-project-scoped-lookups.md) | superseded_to_keep_separate | `fcdb7a0` | Office-owner scoping ≠ tenant scoping. |
| CL-06 | [CL-06-dg13-user-management-scoped-lookups.md](CL-06-dg13-user-management-scoped-lookups.md) | superseded_to_keep_separate | `4896e29` | User model can't use trait; 2 sites stay inline. |
| Phase 6 | [justified-duplication-register.md](justified-duplication-register.md) | done | `45a0318` | 11 groups (8 backend + 3 frontend) registered. |
| Phase 7 | [duplicate-consolidation-backlog.md](duplicate-consolidation-backlog.md) | done | `cfd92cd` | 3 explicit backlog entries with acceptance criteria. |
| Final | [final-cleanup-report.md](final-cleanup-report.md) | done | (this commit) | All gates PASS. |

## Governance

- Working from HEAD `89833fb` — read-only audit deliverables treated as authoritative decision input.
- No push, no tag, no merge, no force-reset.
- No deletion or consolidation beyond the audit's exact findings.
- User-owned untracked files preserved.
