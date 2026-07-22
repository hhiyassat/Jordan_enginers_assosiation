# File Classification Manifest — Workstream 4

Every source file in `backend/app/` and `frontend/src/` tagged with
its architectural role. This is the authoritative inventory: future
workstreams check their moves against this document, and a future
`dep-cruiser` custom rule can consume the JSON extract (produced
on-demand from this file).

**Scope:** 89 backend `.php` files + 159 frontend `.ts`/`.tsx`/`.json`
files = 248 files total.

## Tags

| tag | meaning | must not contain |
|-----|---------|------------------|
| **PC** | Platform Core — reusable across any tenant | domain terminology, service-specific rules, hardcoded workflows |
| **STC** | Shared Technical Component — domain-neutral utility | service-specific assumptions, business logic |
| **SM** | Service Module — JEA-specific | any hard dependency from a platform-tagged file |
| **PLG** | Optional Plugin — installable / removable capability | platform imports (dependency direction only) |
| **EIA** | External Integration Adapter — GSB / Nashmi / etc. | platform imports (dependency direction only) |
| **RED** | Requiring Redesign — entangled today, needs decomposition in Workstreams 5+ | (currently violates one or more of the above rules) |

**Test file convention:** a `*.test.*` file inherits the tag of the
file it tests. Not listed separately unless the classification differs.

**Method:** hand-classified from directory structure, import graph, and
domain-content inspection at commit `414b9c8`. Two edge-case rules:

1. If a file straddles two categories, it gets **RED** and is listed
   as a decomposition target in `01-refactoring-plan.md` §3.
2. Framework scaffolding (`app/Providers/*`, `main.tsx`, `App.tsx`,
   `routes.tsx`) is **PC** even though it composes non-platform
   things — its ROLE is composition.

---

## Backend — `backend/app/`

### `Console/Commands/`

| file | tag | rationale |
|------|-----|-----------|
| `AuditLogPrune.php` | **PC** | Audit trail retention — platform hygiene. |
| `GsbPruneLogs.php` | **EIA** | Prunes GSB call logs. |
| `OpenAnnualDues.php` | **SM** | Opens F-05 annual-dues rows. JEA. |
| `RemindExpiries.php` | **SM** | JEA cert / supervision expiry reminders. |
| `UserCredentials.php` | **PC** | Bootstrap admin credentials. |

### `Engine/`

| file | tag | rationale |
|------|-----|-----------|
| `CapacityGuard.php` | **SM** | JEA per-engineer / per-office quota checks. |
| `Disciplines.php` | **SM** | JEA discipline enum (materials-testing etc.). |
| `Exceptions/ConflictException.php` | **PC** | Generic. |
| `Exceptions/InvalidStateException.php` | **PC** | Generic workflow exception. |
| `Exceptions/RoleMismatchException.php` | **PC** | Generic RBAC exception. |
| `Exceptions/WorkflowException.php` | **PC** | Base workflow exception. |
| `FeeCalculator.php` | **RED** | Fee-calc primitive + JEA fee shape + surcharges. Split platform + JEA impl. |
| `QuotaLedger.php` | **SM** | JEA quota-tracking. |
| `SanctionGuard.php` | **SM** | JEA suspension / de-registration enforcement. |
| `SchemaStructureValidator.php` | **PC** | Generic JSON-shape validator. |
| `SchemaValidator.php` | **RED** | Generic field validation + JEA field types. Split. |
| `StageActions.php` | **RED** | Generic stage-action registry + JEA action set. Split. |
| `WorkflowEngine.php` | **RED** | Generic state machine + reads JEA schema. Split. |

### `Http/Controllers/`

| file | tag | rationale |
|------|-----|-----------|
| `Controller.php` | **PC** | Laravel base controller. |
| `Api/AdminController.php` | **RED** | Dashboard + user-mgmt + audit + AI (4 unrelated). Split in Workstream 5. |
| `Api/ApplicationController.php` | **RED** | CRUD + review-queue + review-dashboard + docs + payment + cert (10 things). Split. |
| `Api/AuthController.php` | **PC** | Platform. |
| `Api/CaptchaController.php` | **PLG** | Optional. |
| `Api/ComplaintController.php` | **SM** | JEA disciplinary. |
| `Api/EngineerController.php` | **SM** | JEA engineers. |
| `Api/GsbController.php` | **EIA** | GSB adapter. |
| `Api/IntegrationController.php` | **EIA** | Nashmi. |
| `Api/LegalFineController.php` | **SM** | JEA Art.14 fines. |
| `Api/MyOfficeController.php` | **SM** | JEA applicant self-service. |
| `Api/NotificationController.php` | **PC** | Generic inbox. |
| `Api/OfficeSettingsController.php` | **SM** | JEA office quotas + boost flags. |
| `Api/ProjectController.php` | **SM** | JEA projects. |
| `Api/RecurringDuesController.php` | **SM** | JEA F-04 / F-05. |
| `Api/ServiceCatalogController.php` | **RED** | Service CRUD + fee editor + locking. JEA fee editor extracts; catalog primitive stays platform. |
| `Api/SupervisionTransferController.php` | **SM** | JEA transfer queue. |
| `Api/UserManagementController.php` | **PC** | Platform user CRUD. |

### `Http/Middleware/`

| file | tag | rationale |
|------|-----|-----------|
| `CheckRole.php` | **PC** | RBAC gate. |
| `EnforcePasswordPolicy.php` | **PC** | Password rotation. |
| `GsbIpWhitelist.php` | **EIA** | GSB adapter. |
| `LogApiAccess.php` | **PC** | Generic API access log. |
| `ReadTokenFromCookie.php` | **PC** | Sanctum cookie → bearer. |
| `SecurityHeaders.php` | **PC** | HTTP headers. |
| `TokenInactivityCheck.php` | **PC** | Session-timeout. |
| `TrackUserActivity.php` | **PC** | Last-seen bump. |
| `ValidateIntegrationKey.php` | **EIA** | Nashmi X-Integration-Key. |
| `VerifyCaptcha.php` | **PLG** | Captcha. |

### `Http/Requests/`

| file | tag | rationale |
|------|-----|-----------|
| `ConfirmPaymentRequest.php` | **SM** | JEA payment step. |
| `DecideApplicationRequest.php` | **SM** | JEA reviewer decision. |
| `StoreApplicationRequest.php` | **SM** | JEA application create. |

### `Models/`

| file | tag | rationale |
|------|-----|-----------|
| `Application.php` | **RED** | JEA app + status enum + service-def coupling. Move to jea-services. |
| `ApplicationDocument.php` | **SM** | JEA. |
| `ApplicationReview.php` | **SM** | JEA review record. |
| `AuditLog.php` | **PC** | Platform audit. |
| `Certificate.php` | **SM** | JEA cert issue. |
| `CertificateCounter.php` | **SM** | JEA cert sequence. |
| `Complaint.php` | **SM** | JEA disciplinary. |
| `Concerns/BelongsToOrganization.php` | **PC** | Platform trait. |
| `Concerns/OrganizationScope.php` | **PC** | Platform trait. |
| `Engineer.php` | **SM** | JEA. |
| `EngineerDisciplineQuota.php` | **SM** | JEA quota. |
| `GsbCallLog.php` | **EIA** | GSB. |
| `IntegrationCycle.php` | **EIA** | Nashmi. |
| `LegalFine.php` | **SM** | JEA. |
| `Notification.php` | **PC** | Platform inbox. |
| `OfficeCeiling.php` | **SM** | JEA. |
| `OfficeCoalition.php` | **SM** | JEA. |
| `OfficeCoalitionMember.php` | **SM** | JEA. |
| `Organization.php` | **PC** | Tenant. |
| `Project.php` | **SM** | JEA project. |
| `QuotaConsumption.php` | **SM** | JEA. |
| `RecurringObligation.php` | **SM** | JEA F-04/F-05. |
| `Sanction.php` | **SM** | JEA disciplinary. |
| `ServiceDefinition.php` | **RED** | Platform primitive + JEA schema shape. Split with contract. |
| `SupervisionTransfer.php` | **SM** | JEA. |
| `User.php` | **PC** | Platform user. |

### `Providers/`, `Rules/`, `Services/`

| file | tag | rationale |
|------|-----|-----------|
| `Providers/AppServiceProvider.php` | **PC** | Composition root. |
| `Providers/StorageServiceProvider.php` | **PC** | Storage bootstrap. |
| `Rules/PdfOrDwgFile.php` | **RED** | Generic file-type rule + DWG (CAD-specific) hint. Split. |
| `Services/CaptchaService.php` | **PLG** | Captcha. |
| `Services/Gsb/GsbAuthManager.php` | **EIA** | GSB. |
| `Services/Gsb/GsbClient.php` | **EIA** | GSB. |
| `Services/NashmiIntegrationService.php` | **EIA** | Nashmi. |
| `Services/Notifications/NotificationService.php` | **PC** | Platform notification service. |
| `Services/Payment/MockPaymentGateway.php` | **PC** | Payment abstraction impl. |
| `Services/Payment/PaymentGateway.php` | **PC** | Payment contract. |
| `Services/Payment/PaymentInitiation.php` | **PC** | Payment DTO. |
| `Services/Payment/PaymentReceipt.php` | **PC** | Payment DTO. |
| `Services/RecurringDuesService.php` | **SM** | JEA. |
| `Services/SupervisionTransferService.php` | **SM** | JEA. |

### Backend summary

| tag | count | % |
|-----|-------|---|
| PC | 30 | 33.7% |
| STC | 0 | 0% |
| SM | 40 | 44.9% |
| PLG | 3 | 3.4% |
| EIA | 9 | 10.1% |
| RED | 7 | 7.9% |
| **total** | **89** | **100%** |

---

## Frontend — `frontend/src/`

### Composition roots

| file | tag | rationale |
|------|-----|-----------|
| `App.tsx` | **PC** | Composition. |
| `main.tsx` | **PC** | Boot. |
| `routes.tsx` | **RED** | Hardcodes every route (JEA + platform + integration). Refactor to module-registry in Workstream 10. |

### `api/`

| file | tag | rationale |
|------|-----|-----------|
| `admin.ts` | **RED** | Platform admin + JEA-service admin. Split in Workstream 6. |
| `applications.ts` | **SM** | JEA. |
| `applicationsCreate.test.ts` | **SM** | Test of JEA. |
| `auth.ts` | **PC** | Platform. |
| `client.ts` | **RED** | Barrel re-exports platform + JEA. Split. |
| `client401.test.ts` | **PC** | Platform 401 handling. |
| `engineers.ts` | **SM** | JEA. |
| `hooks.ts` | **RED** | Platform hooks + JEA hooks. Split. |
| `http.ts` | **PC** | Generic HTTP client. |
| `integration.ts` | **EIA** | Nashmi. |
| `myOffice.ts` | **SM** | JEA. |
| `notifications.ts` | **PC** | Generic. |
| `projects.ts` | **SM** | JEA. |
| `queryClient.ts` | **PC** | React Query bootstrap. |
| `review.ts` | **SM** | JEA reviewer. |
| `services.ts` | **SM** | JEA service catalog. |
| `tokenStorage.test.ts` | **PC** | Platform session. |
| `users.ts` | **PC** | Platform user CRUD. |

### `auth/`

| file | tag | rationale |
|------|-----|-----------|
| `AuthContext.tsx` | **PC** | Platform. |
| `AuthProvider.test.tsx` | **PC** | Test of platform. |
| `AuthProvider.tsx` | **PC** | Platform. |
| `guards.test.tsx` | **PC** | Test of platform. |
| `guards.tsx` | **PC** | Platform. |
| `LoginPage.tsx` | **PC** | Platform login screen. |

### `components/`

| file | tag | rationale |
|------|-----|-----------|
| `ErrorBoundary.test.tsx` | **PC** | Test. |
| `ErrorBoundary.tsx` | **PC** | Platform. |
| `JEALogo.tsx` | **SM** | JEA brand asset. |
| `LanguageSwitcher.test.tsx` | **PC** | Test. |
| `LanguageSwitcher.tsx` | **PC** | Platform. |
| `NotificationBell.test.tsx` | **PC** | Test. |
| `NotificationBell.tsx` | **PC** | Platform inbox widget. |

### `components/ui/` — design system + JEA-specific widgets

| file | tag | rationale |
|------|-----|-----------|
| `Bilingual.tsx` / `Bilingual.test.tsx` | **PC** | Generic. |
| `Button.tsx` / `Button.test.tsx` | **PC** | Design system. |
| `Captcha.tsx` / `Captcha.test.tsx` | **PLG** | Optional. |
| `ComplianceNotesBanner.tsx` / `.test.tsx` | **SM** | Renders JEA `schema.compliance_notes[]`. |
| `ConfirmDialog.tsx` / `.test.tsx` | **PC** | Platform. |
| `ExpiryBadge.tsx` / `.test.tsx` | **SM** | JEA cert / supervision expiry chip. |
| `FormField.tsx` / `.test.tsx` | **PC** | Design system. |
| `Modal.tsx` | **PC** | Design system. |
| `PageHero.tsx` | **PC** | Design system. |
| `PhaseBadge.tsx` / `.test.tsx` | **SM** | JEA delivery phase 1..5. |
| `QuotaCard.tsx` / `.test.tsx` | **SM** | JEA office quota widget. |
| `RolePathBadge.tsx` / `.test.tsx` | **SM** | JEA workflow role-path chip. |
| `ServiceInfoCard.tsx` / `.test.tsx` | **SM** | JEA service metadata card. |
| `SkipToContent.tsx` | **PC** | Accessibility. |
| `WorkflowStepper.tsx` / `.test.tsx` | **SM** | Renders JEA workflow stages. |

### `engine/`

| file | tag | rationale |
|------|-----|-----------|
| `DocumentPreviewCard.tsx` / `.test.tsx` | **RED** | Generic-in-intent, JEA schema shape today. |
| `DocumentUploader.tsx` | **RED** | Same. |
| `DynamicForm.tsx` | **RED** | Renders `ServiceSchema.fields[]`. Contract split in Workstream 10. |
| `DynamicForm.i18n.test.ts` / `order.test.tsx` / `validateAll.test.ts` | **RED** | Tests of above. |
| `workflowRolePath.ts` / `.test.ts` | **SM** | JEA workflow role-path derivation. |

### `i18n/`

| file | tag | rationale |
|------|-----|-----------|
| `index.ts` | **PC** | i18next bootstrap. |
| `i18n.test.ts` | **PC** | Test of framework. |
| `locales/ar.json` | **RED** | Co-mingled platform + JEA copy. Split per-module in Workstream 10. |
| `locales/en.json` | **RED** | Same. |

### `layout/`

| file | tag | rationale |
|------|-----|-----------|
| `Header.tsx` | **PC** | Shell. |
| `Layout.tsx` | **PC** | Shell. |
| `navItems.tsx` | **RED** | Hardcodes every nav lane. Refactor to NavRegistry (Workstream 10). |
| `navItems.test.tsx` | **RED** | Test of above. |
| `pageTitle.ts` / `.test.ts` | **RED** | Maps platform + JEA route to title. Split. |
| `RouteSuspense.tsx` | **PC** | Suspense wrapper. |
| `SidebarContent.tsx` | **PC** | Shell. |

### `pages/admin/` — mixed platform + JEA + adapter surfaces

| file | tag | rationale |
|------|-----|-----------|
| `AdminApplications.tsx` / `.test.tsx` | **SM** | JEA org-wide applications view. |
| `AdminDashboard.tsx` / `.test.tsx` | **RED** | Platform tiles + JEA "recent applications". |
| `ComplaintsAdmin.tsx` / `.test.tsx` | **SM** | JEA. |
| `EditService.tsx` / `EditService.applyAndSave.test.tsx` | **SM** | JEA service admin. |
| `IntegrationCycleDetail.tsx` / `.test.tsx` | **EIA** | Nashmi. |
| `IntegrationCycles.tsx` / `.test.tsx` | **EIA** | Nashmi. |
| `LegalFinesAdmin.tsx` / `.test.tsx` | **SM** | JEA. |
| `NewService.tsx` | **SM** | JEA. |
| `OfficeDues.tsx` / `.test.tsx` | **SM** | JEA. |
| `OfficeSettings.tsx` / `.test.tsx` | **SM** | JEA. |
| `OfficesList.tsx` / `.test.tsx` | **SM** | JEA. |
| `saveErrorHelpers.ts` / `.test.ts` | **SM** | JEA service-save error normalisation. |
| `ServiceFeesAdmin.tsx` / `.test.tsx` | **SM** | JEA. |
| `ServicesList.tsx` / `.test.tsx` | **SM** | JEA. |
| `SupervisionTransfersAdmin.tsx` / `.test.tsx` | **SM** | JEA. |
| `UserManagement.tsx` / `.test.tsx` | **PC** | Platform user CRUD. |

### `pages/applicant/` — all JEA-specific

Every file (13 pairs of `.tsx` + `.test.tsx`, plus helpers) tagged **SM**:

`ApplicationDetail`, `applicationStatus`, `Apply`, `applyErrorHelpers`,
`CategoryServicesView`, `Dashboard` (+ i18n test), `MiniStageTimeline`,
`missingRequiredDocs`, `MyApplications`, `MyOffice`,
`ProjectContextHeader`, `ProjectDetail`, `ProjectsList`, `ServiceList`.

### `pages/auth/`

| file | tag | rationale |
|------|-----|-----------|
| `ChangeCredentials.tsx` / `.test.tsx` | **PC** | Platform first-login credential change. |
| `Profile.tsx` / `.test.tsx` | **PC** | Platform profile. |

### `pages/reviewer/`

All 5 files (`ReviewDashboard.tsx` / `.test.tsx`, `ReviewPanel.tsx`,
`ReviewQueue.tsx` / `.test.tsx`) tagged **SM** — JEA reviewer surface.

### `test/` — Vitest infrastructure

| file | tag | rationale |
|------|-----|-----------|
| `queryWrapper.tsx` | **PC** | Test util. |
| `setup.ts` | **PC** | Test bootstrap. |

### `types/`

| file | tag | rationale |
|------|-----|-----------|
| `index.ts` | **RED** | 346-line god module. Split platform + JEA. |

### `utils/`

| file | tag | rationale |
|------|-----|-----------|
| `csv.ts` / `.test.ts` | **STC** | Generic. |
| `errorMessage.ts` / `.test.ts` | **STC** | Generic. |
| `SortHeader.tsx` | **STC** | Generic. |
| `useSortableRows.ts` / `.test.ts` | **STC** | Generic. |

### Frontend summary

| tag | count | % |
|-----|-------|---|
| PC | 45 | 28.3% |
| STC | 6 | 3.8% |
| SM | 79 | 49.7% |
| PLG | 2 | 1.3% |
| EIA | 4 | 2.5% |
| RED | 23 | 14.5% |
| **total** | **159** | **100%** |

---

## Combined summary

| tag | backend | frontend | total | % of 248 |
|-----|---------|----------|-------|---------|
| **PC** — Platform Core | 30 | 45 | 75 | 30.2% |
| **STC** — Shared Technical | 0 | 6 | 6 | 2.4% |
| **SM** — Service Module (JEA) | 40 | 79 | 119 | 48.0% |
| **PLG** — Plugin | 3 | 2 | 5 | 2.0% |
| **EIA** — External Integration Adapter | 9 | 4 | 13 | 5.2% |
| **RED** — Requiring Redesign | 7 | 23 | 30 | 12.1% |
| **total** | **89** | **159** | **248** | **100%** |

### Interpretation

- **~33% platform + shared** (75 PC + 6 STC out of 248) — that's the
  reusable core. Encouraging: a third of the codebase is already
  domain-neutral.
- **~48% JEA-specific service code** (119 SM) — the JEA business
  service. Owns its models, screens, workflows, seeders.
- **~12% needs decomposition** (30 RED) — the concrete refactoring
  surface. Every RED file is a Workstream 5..14 target.
- **~7% adapters + plugins** (13 EIA + 5 PLG) — already close to the
  target shape; Workstreams 13 + 14 lift them into `plugins/` and
  `integrations/` directories.
- **Zero STC on the backend** — every backend utility today either
  belongs to the platform namespace or a service module. If more
  cross-service technical utils emerge they'll grow this category.

---

## Concrete RED targets (decomposition surface)

Every file tagged RED is a candidate for a Workstream 5..14 split.
Consolidated here so a reviewer can trace target → workstream:

| RED file | Workstream | Planned decomposition |
|----------|-----------|-----------------------|
| `Http/Controllers/Api/AdminController.php` | 5 | dashboard (PC) + user-mgmt (PC, already extracted to UserManagementController) + audit-log (PC) + ai-schema (PLG) |
| `Http/Controllers/Api/ApplicationController.php` | 5 | applications CRUD (SM) + review-queue (SM) + review-dashboard (SM) + document-upload (SM) + payment-confirm (SM) + cert-issue (SM) |
| `Http/Controllers/Api/ServiceCatalogController.php` | 5 | ServiceCatalog CRUD (PC primitive) + JEA fee editor (SM) |
| `Engine/WorkflowEngine.php` | 8 | PC state machine + JEA workflow adapter |
| `Engine/SchemaValidator.php` | 8 | PC field-type registry + JEA field-type impls |
| `Engine/StageActions.php` | 8 | PC stage-action registry + JEA action set |
| `Engine/FeeCalculator.php` | 8 | PC calc primitives + JEA fee/surcharge rules |
| `Models/Application.php` | 7 | Move to `modules/jea-services/`. Extract status enum to service. |
| `Models/ServiceDefinition.php` | 7 | Platform `ServiceDefinition` primitive + JEA `JeaServiceSchema` view. |
| `Rules/PdfOrDwgFile.php` | 8 | PC PDF rule + SM DWG rule. |
| `api/admin.ts` | 6 | Split into `platform/admin.ts` + `jea/admin.ts`. |
| `api/hooks.ts` | 6 | Split into `platform/hooks.ts` + `jea/hooks.ts`. |
| `api/client.ts` | 6 | Split barrels. |
| `engine/DynamicForm.tsx` | 10 | Consumes `SchemaContract` (PC); current JEA `ServiceSchema` becomes one impl. |
| `engine/DocumentUploader.tsx` | 10 | Same. |
| `engine/DocumentPreviewCard.tsx` | 10 | Same. |
| `layout/navItems.tsx` | 10 | Replace with `NavRegistry` — modules register their own lanes. |
| `layout/pageTitle.ts` | 10 | Same pattern — per-module registration. |
| `routes.tsx` | 10 | Replace with `ModuleRegistry` composition. |
| `types/index.ts` | 6 | Split platform + JEA types. |
| `i18n/locales/{ar,en}.json` | 10 | Per-module locale bundles merged at boot. |
| `pages/admin/AdminDashboard.tsx` | 5 | Platform admin shell + JEA "recent apps" widget. |

30 RED files → 12 concrete decomposition operations across
Workstreams 5..10.

---

## Machine-readable extract

A JSON snapshot for future dep-cruiser custom rules or CI checks
lives at `docs/architecture/03-file-classification.json` (co-committed
with this Markdown source). It is derived from this document — if the
Markdown changes, regenerate the JSON.

Extract script (kept simple; run manually):

```bash
# tags: PC, STC, SM, PLG, EIA, RED
# emit one line per file
awk '/^\| `.+\.(php|ts|tsx|json)` .*\| \*\*(PC|STC|SM|PLG|EIA|RED)\*\*/ {
  match($0, /`([^`]+)`/, f); match($0, /\*\*(PC|STC|SM|PLG|EIA|RED)\*\*/, t);
  print f[1] "\t" t[1];
}' docs/architecture/03-file-classification.md
```

The extract is regenerated as part of the plan doc's Workstream 15
(promote enforcement). No script is committed today — humans update
the Markdown, and dep-cruiser doesn't yet consume it.

---

## Acceptance criteria (from `01-refactoring-plan.md` §5, workstream 4)

| criterion | result |
|-----------|--------|
| Every file in the inventory has a tag | ✅ 248/248 tagged |
| Inventory diff-clean | ✅ this doc replaces the module-level tables in the plan doc as the authoritative source |
| No file moves | ✅ zero source files touched |
| Existing suites still green | ✅ Backend 551 / Frontend 410 unchanged |

## Deferred to later workstreams

- **Physical enforcement of the tags** — today the manifest is
  descriptive. Workstreams 5..14 move files into folder trees
  (`platform/`, `modules/*`, `plugins/`, `integrations/`) that
  match the tags, at which point dep-cruiser rules can enforce
  the boundaries mechanically.
- **In-file docblock tagging** — deliberately deferred. Adding
  `@architecture PC` to 248 files produces a 500-line diff with
  marginal value; when a file MOVES into its target folder the tag
  becomes visible in the path itself.
- **JSON extract auto-regeneration in CI** — added when
  dep-cruiser gains a custom rule that consumes it.

## Next workstream

Workstream 5 — split the two god-controllers (`AdminController`,
`ApplicationController`) plus `ServiceCatalogController`, with route
aliases to preserve every existing URL. First workstream that actually
touches source code paths; the manifest here becomes the checklist.
