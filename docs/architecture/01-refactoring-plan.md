# Refactoring Plan — Platform vs Service Modules

Reference document for the architecture-level refactoring described
in the Governing Refactor Spec. Consumers:

- The engineer(s) executing each workstream.
- The reviewer(s) validating that each workstream preserves behaviour.
- Future service-module authors who need to know the extension points.

Baseline snapshot: `docs/architecture/00-baseline.md`. All numbers
below refer to the state at commit `bc8aaed`.

---

## 1. Governing model (recap of the spec)

The end state is a **modular monolith** with strictly separated concerns:

```
┌──────────────────────────────────────────────────────────────────┐
│                     Domain-Neutral Platform                        │
│  auth · users · orgs · roles · workflow-engine · notifications ·   │
│  audit · storage · i18n · design-system · plugin-host · APIs       │
└──────────────────────────────────────────────────────────────────┘
              ▲                    ▲                    ▲
              │ contracts          │ contracts          │ contracts
              │                    │                    │
     ┌────────┴────────┐    ┌──────┴──────┐    ┌────────┴────────┐
     │ Service Module  │    │  Plugin     │    │ Integration     │
     │  jea-services   │    │ (ai-schema, │    │   Adapter       │
     │  jea-discipline │    │  captcha…)  │    │ (gsb, nashmi)   │
     │  jea-dues       │    └─────────────┘    └─────────────────┘
     │  jea-projects   │
     └─────────────────┘
```

Dependency direction is one-way. The platform never imports a service
module. Services never import each other's internals — only public
contracts.

---

## 2. Component inventory + classification

Classification key (per Refactor Spec §11):

| tag | meaning |
|-----|---------|
| **PC** | Platform Core |
| **STC** | Shared Technical Component |
| **SM** | Service Module (JEA-specific) |
| **PLG** | Optional Plugin |
| **EIA** | External Integration Adapter |
| **RED** | Component Requiring Redesign (currently entangled) |

### 2.1 Backend inventory

#### Controllers (`app/Http/Controllers/Api/`)

| File | Tag | Notes |
|------|-----|-------|
| `AuthController` | **PC** | Login/logout/me/register/password change — platform. |
| `NotificationController` | **PC** | Generic inbox — platform. |
| `AdminController` | **RED** | Mixes platform dashboard, user-mgmt, audit-logs, AI-schema. Split. |
| `UserManagementController` | **PC** | Platform user CRUD. Superuser scope (project memory). |
| `ServiceCatalogController` | **RED** | JEA-service CRUD + fee editor + locking. Split into a platform "service definition registry" primitive + JEA fee editor. |
| `ApplicationController` | **RED** | 500+ lines: applications CRUD + review-queue + review-dashboard + document upload + payment confirm + cert issuance. Split. |
| `ProjectController` | **SM** | JEA `Project` entity. |
| `EngineerController` | **SM** | JEA `Engineer` entity. |
| `ComplaintController` | **SM** | JEA disciplinary. |
| `LegalFineController` | **SM** | JEA Art.14 fines. |
| `SupervisionTransferController` | **SM** | JEA supervision-transfer queue. |
| `OfficeSettingsController` | **SM** | JEA office boost/quota flags. |
| `RecurringDuesController` | **SM** | JEA F-04/F-05 dues. |
| `MyOfficeController` | **SM** | JEA applicant self-service. |
| `GsbController` | **EIA** | GSB (Jordan Gov Service Bus) integration. |
| `IntegrationController` | **EIA** | Nashmi PM integration. |
| `CaptchaController` | **PLG** | Optional captcha challenge. |

**Result:** 3 controllers are platform, 2 are external adapters, 1 is
plugin, 8 are JEA-service, 3 need decomposition.

#### Middleware (`app/Http/Middleware/`)

| File | Tag | Notes |
|------|-----|-------|
| `SecurityHeaders` | **PC** | Generic HTTP headers. |
| `LogApiAccess` | **PC** | Generic API access log. |
| `ReadTokenFromCookie` | **PC** | Sanctum bearer promotion. |
| `TokenInactivityCheck` | **PC** | Session-timeout policy. |
| `EnforcePasswordPolicy` | **PC** | Password rotation. |
| `CheckRole` | **PC** | RBAC gate. |
| `TrackUserActivity` | **PC** | Last-seen bump. |
| `ValidateIntegrationKey` | **EIA** | Nashmi X-Integration-Key. |
| `GsbIpWhitelist` | **EIA** | GSB IP-restriction. |
| `VerifyCaptcha` | **PLG** | Captcha verification. |

**Result:** clean split — 7 platform middleware, 2 external-adapter,
1 plugin. This is the most-correctly-shaped part of the codebase today.

#### Models (`app/Models/`)

| File | Tag | Notes |
|------|-----|-------|
| `User`, `Organization`, `AuditLog`, `Notification` | **PC** | Platform. |
| `PersonalAccessToken` (Sanctum) | **PC** | Platform. |
| `ServiceDefinition` | **RED** | Currently JEA-specific (fee schema, workflow stages, compliance notes, quota_discipline_override). Should split: a platform `ServiceDefinition` primitive (id, code, schema-blob-shape-agnostic) + a JEA-side view. |
| `Application`, `ApplicationDocument`, `ApplicationReview`, `Certificate` | **RED** | Entangled with `ServiceDefinition` above. Also carries JEA status enum. |
| `Project` | **SM** | JEA project. |
| `Engineer`, `EngineerDisciplineQuota`, `OfficeCeiling`, `OfficeBoostFlags` | **SM** | JEA engineer/office. |
| `RecurringObligation` | **SM** | JEA F-04/F-05. |
| `Complaint`, `Sanction` | **SM** | JEA disciplinary. |
| `LegalFine` | **SM** | JEA legal fines. |
| `SupervisionTransfer` | **SM** | JEA. |
| `IntegrationCycle`, `IntegrationSubmission` | **EIA** | Nashmi. |

#### Engine primitives (`app/Engine/`)

| File | Tag | Notes |
|------|-----|-------|
| `WorkflowEngine` | **RED** | Generic workflow orchestration primitives (state transitions, stage advancement) BUT currently reads a JEA-specific schema shape (`ServiceDefinition::schema.workflow.stages`). Extract the primitives to a platform module with a `ServiceSchemaContract` interface, keep JEA adapter alongside. |
| `SchemaValidator` | **RED** | Generic JSON-schema-like validation BUT tied to JEA field types. Same treatment. |
| `FeeCalculator` | **RED** | Generic fee calc BUT reads JEA fee blocks + surcharges + tier tables. Extract primitives; JEA fee rules stay in service module. |
| `QuotaLedger` | **SM** | JEA-specific (engineer disciplines, materials-testing, office ceilings). |
| `StageActions` | **RED** | Generic stage-action registry BUT the action set is JEA. |
| `SchemaStructureValidator` | **PC** | Validates that `schema.fields`, `schema.workflow.stages`, `schema.documents` are shape-well-formed. Platform primitive. |

**Result:** most of `app/Engine/` needs to become a platform "workflow +
schema" package with narrow interfaces, plus JEA-side adapters that
plug the JEA schema and rules into it.

#### Services (`app/Services/`)

| File | Tag | Notes |
|------|-----|-------|
| `RecurringDuesService` | **SM** | JEA F-04/F-05. |
| `SupervisionTransferService` | **SM** | JEA. |

#### Seeders (`database/seeders/`)

Every seeder except `DatabaseSeeder` orchestrator is JEA-specific:
`ServicePlan2026Seeder`, `SurveyWorkflowsSeeder`,
`CatalogWorkflowsSeeder`, `MaterialsTestingQuotaSeeder`,
`GovernmentSurveyQuotaSeeder`, `SiteSurveyFeesSeeder`,
`MaterialsSampleRetentionSeeder`, `FeeSurchargesSeeder`,
`DrawingEngineerPickerSeeder`, `ServiceFeeDefaultsSeeder`,
`DemoEngineersSeeder`, `SampleProjectsSeeder`, `DemoSeeder`.

**Tag:** all **SM** except the empty `DatabaseSeeder` orchestrator
(**PC**). Every JEA seeder must move to a `jea/*` service-module
directory once the module split lands.

#### API routes (`routes/api.php`)

Currently a single 200-line file registering platform + JEA + integration
routes. **RED.** The refactor introduces per-module route files loaded
by the platform's plugin/service registrar.

### 2.2 Frontend inventory

#### `src/api/`

| File | Tag | Notes |
|------|-----|-------|
| `http.ts` | **PC** | Generic HTTP client, timeout, 401 handler. |
| `queryClient.ts` | **PC** | React Query bootstrap. |
| `auth.ts` | **PC** | Platform auth. |
| `notifications.ts` | **PC** | Generic inbox. |
| `users.ts` | **PC** | Platform user management. |
| `hooks.ts` | **RED** | Mixes platform hooks (`useAdminDashboardStats` — mixed shape) with JEA hooks (`usePaginatedAdminApplications`). Split. |
| `client.ts` | **RED** | Barrel that re-exports both platform + JEA APIs. Split into `platform/index` and `jea/index`. |
| `admin.ts` | **RED** | Mixes platform admin (dashboard/users) with JEA-specific admin (office fees, complaints, legal fines, supervision, service catalog, AI schema). |
| `applications.ts` | **SM** | JEA. |
| `projects.ts`, `engineers.ts` | **SM** | JEA. |
| `review.ts` | **SM** | JEA review. |
| `myOffice.ts` | **SM** | JEA. |
| `services.ts` | **RED** | JEA service catalog surface. |
| `integration.ts` | **EIA** | Nashmi. |

#### `src/auth/`

| File | Tag | Notes |
|------|-----|-------|
| `AuthContext.tsx`, `AuthProvider.tsx`, `guards.tsx` | **PC** | Platform. |
| `LoginPage.tsx` | **PC** | Platform (uses `t('org.name')` which is JEA copy — copy is data, not code). |

#### `src/components/`

| File | Tag | Notes |
|------|-----|-------|
| `ErrorBoundary` | **PC** | Platform. |
| `LanguageSwitcher` | **PC** | Platform. |
| `NotificationBell` | **PC** | Platform inbox widget. |
| `JEALogo` | **SM** | JEA-specific brand asset. Should live in a JEA theme package. |

#### `src/components/ui/`

| File | Tag | Notes |
|------|-----|-------|
| `Button`, `Modal`, `ConfirmDialog`, `FormField`, `TextField`, `PageHero`, `Bilingual` | **PC** | Design system. |
| `Captcha` | **PLG** | Captcha widget (paired with plugin backend). |
| `WorkflowStepper`, `ServiceInfoCard`, `ComplianceNotesBanner`, `QuotaCard` | **SM** | Render JEA-specific schema shapes / labels. |

#### `src/engine/`

| File | Tag | Notes |
|------|-----|-------|
| `DynamicForm` | **RED** | Renders a `ServiceSchema.fields[]` — generic in intent but the schema shape is JEA-defined today. Extract as platform "form-from-schema" primitive; the JEA `ServiceSchema` becomes one impl of the contract. |
| `DocumentUploader`, `DocumentPreviewCard` | **RED** | Same treatment. |

#### `src/layout/`

`Layout`, `Header`, `RouteSuspense`, `navItems` — all **PC**.

Note: `navItems.tsx` currently *contains* every route (platform + JEA
admin + reviewer + applicant lanes). Refactor: the platform shell
exposes a `registerNav()` API; each service module contributes its own
nav entries.

#### `src/pages/`

| Directory | Tag | Notes |
|-----------|-----|-------|
| `pages/auth/*` | **PC** | ChangeCredentials, Profile — platform. |
| `pages/applicant/*` | **SM** | 27 files, all JEA-specific. Move whole tree under `jea/pages/applicant/`. |
| `pages/reviewer/*` | **SM** | 5 files, all JEA reviewer surfaces. |
| `pages/admin/AdminDashboard` | **RED** | Mixed platform+JEA content — split into a platform admin shell + JEA "recent applications" widget. |
| `pages/admin/UserManagement` | **PC** | Platform user CRUD. |
| `pages/admin/IntegrationCycles`, `IntegrationCycleDetail` | **EIA** | Nashmi. |
| `pages/admin/OfficesList`, `OfficeSettings`, `OfficeDues` | **SM** | JEA. |
| `pages/admin/ServicesList`, `NewService`, `EditService`, `ServiceFeesAdmin` | **SM** | JEA (with AI plugin dependency). |
| `pages/admin/ComplaintsAdmin`, `LegalFinesAdmin`, `SupervisionTransfersAdmin` | **SM** | JEA. |
| `pages/admin/AdminApplications` | **SM** | JEA (org-wide applications view). |

#### `src/utils/`

| File | Tag | Notes |
|------|-----|-------|
| `errorMessage`, `csv`, `useSortableRows`, `SortHeader` | **STC** | All domain-neutral. Move to `@platform/ui-utils`. |

#### `src/types/index.ts`

**RED.** 346 lines mixing platform types (`User`, `Notification`,
`ApiError`) with JEA types (`Application`, `ServiceDefinition`,
`Certificate`, `Engineer`, `Project`, `Complaint`, `Sanction`, workflow
schema shape). Split.

#### `src/i18n/locales/{ar,en}.json`

**RED.** Every locale file co-mingles:

- Platform copy: `common.*`, `error.*`, `validation.*`, `nav.*`,
  `layout.*`, `notifications.*`, `errorBoundary.*`, `documentUploader.*`
- JEA-service copy: `adminDashboard.*`, `reviewPanel.*`, `apply.*`,
  `myApplications.*`, `projects.*`, `projectContextHeader.*`,
  `applyError.*`, `status.*`, `projectStatus.*`, `userManagement.*`

Refactor: split into per-module locale files, merged at boot by i18next.

### 2.3 Classification summary (backend + frontend)

| tag | count of modules (backend + frontend) | proportion |
|-----|--------------------------------------|-----------|
| PC (Platform Core) | 32 | ~18% |
| STC (Shared Technical Component) | 6 | ~3% |
| SM (Service Module — JEA) | 84 | ~47% |
| PLG (Plugin) | 3 | ~2% |
| EIA (External Integration Adapter) | 5 | ~3% |
| RED (Requiring Redesign) | 47 | ~26% |

**Interpretation:** roughly a quarter of the codebase needs
decomposition — mainly because the JEA schema shape has leaked into
platform-adjacent primitives (engine, service definition, admin routes,
API barrels, locale files, type declarations).

---

## 3. Contamination findings — concrete violations

These are the smoking-gun cases where a supposedly platform-neutral
component holds JEA-specific assumptions. Each must be fixed before the
platform can host a non-JEA tenant.

### 3.1 The `ServiceDefinition.schema` shape is JEA

`ServiceDefinition::schema` is typed as a free-form JSON blob but every
consumer (workflow engine, fee calculator, quota ledger, schema
validator, admin editor, applicant Apply page) reads JEA-specific
keys: `workflow.stages[].role='auditor|staff'`,
`fee.type='fixed|per_unit|free'`, `fee.surcharges`, `documents[].id`,
`compliance_notes[].category='retention|fee|eligibility|conduct'`,
`quota_discipline_override='materials_testing'`.

**Fix:** define a platform `SchemaContract` interface with only
shape-agnostic keys (`fields[]`, `workflow: {stages: []}`), plus a JEA
extension interface `JeaServiceSchemaContract extends SchemaContract`
that adds fees / surcharges / compliance notes / quota overrides.
Every consumer reads through the correct interface for its layer.

### 3.2 Workflow engine reads JEA action IDs

`WorkflowEngine::decide()` accepts `decision ∈ {approved, rejected,
modifications_requested}` — those are JEA disciplinary decisions.
Non-JEA workflows (e.g. a permit review) may want different terminal
verbs.

**Fix:** the engine accepts a `TransitionContract` (opaque
transition-id string). JEA registers `{approved, rejected,
modifications_requested}` as its transition set. Platform stays neutral.

### 3.3 Application status enum

`Application::STATUS_*` constants encode a JEA lifecycle
(`draft → submitted → under_review → modifications_requested →
approved | rejected → certificate_issued`). This lives inside the
Application model itself.

**Fix:** the JEA `Application` model owns its status enum. The platform
`WorkflowEngine` uses opaque state ids, resolved through the schema.

### 3.4 `AdminController` bundles unrelated capabilities

`AdminController` has: dashboard stats, user CRUD, audit-log listing,
AI schema generation (three separate endpoints), and chat-schema. These
four capability groups belong in four different modules (dashboard →
platform, user CRUD → platform user-mgmt, audit-log → platform audit,
AI-schema → optional plugin).

### 3.5 `ApplicationController` violates single-responsibility

500+ lines, holds: applications CRUD + submit + document upload + review
queue + review dashboard + claim + decide + payment-confirm + cert-issue
+ cert-verify + cert-PDF-download. Ten capabilities in one class.

### 3.6 `hooks.ts` mixes platform + JEA queries

`useAdminDashboardStats` (platform-shape dashboard) sits next to
`usePaginatedAdminApplications` (JEA-shape applications) in the same
file. Consumers can't tell which import path pulls in which module.

### 3.7 `routes.tsx` centralises every route

Every route (platform + reviewer + applicant + admin + integration)
is registered in a single file. Adding or removing a service module
requires editing the platform's route table — a violation of the
"platform must not know which services are installed" invariant.

### 3.8 `navItems.tsx` hardcodes every menu entry

Same problem as `routes.tsx` — the platform sidebar has explicit
knowledge of every JEA lane (`/admin/complaints`, `/admin/legal-fines`,
`/admin/supervision-transfers`, `/admin/service-fees`).

### 3.9 Locales co-mingle platform + JEA copy

`ar.json` and `en.json` hold platform strings (`nav`, `common`,
`validation`) next to JEA copy (`apply.*`, `myApplications.*`,
`reviewPanel.*`). A non-JEA tenant would inherit "طلباتي" (my
applications) it doesn't need.

### 3.10 `types/index.ts` is a god module

`Application`, `ServiceDefinition`, `Engineer`, `Project`, `Complaint`,
`Sanction`, `Certificate`, `Notification`, `User`, `ApiError` all in
one file. TypeScript sees no boundary; a rogue platform component can
`import { Complaint } from '../../types'` and today nothing prevents it.

### 3.11 Every DB seeder is JEA

`ServicePlan2026Seeder` seeds 60 JEA services. `MaterialsTestingQuotaSeeder`
seeds a JEA quota. There is no platform-neutral seed layer, so a new
non-JEA tenant can't be bootstrapped without editing these files.

### 3.12 No plugin contract

The Claude AI schema generator is invoked directly from
`AdminController`. Captcha check is invoked directly from a
middleware alias. GSB / Nashmi integration each live as one-off
controllers. There is no plugin lifecycle (register/enable/disable
/uninstall), no plugin discovery, no plugin config schema. Adding a
new external identity provider today means editing platform routes +
middleware — a §6 violation.

---

## 4. Target architecture

### 4.1 Backend layout

```
backend/
  platform/                              ← new, domain-neutral
    src/
      Auth/                              ← AuthController, session cookie, guards
      Users/                             ← UserManagementController, User model
      Organizations/                     ← Organization primitive
      Roles/                             ← CheckRole, policy helpers
      Notifications/                     ← NotificationController + Notification model
      Audit/                             ← AuditLog + record helper
      Sessions/                          ← Sanctum cookie config, TokenInactivityCheck
      Workflow/                          ← WorkflowEngine primitives + SchemaContract
      SchemaRegistry/                    ← SchemaStructureValidator (shape-agnostic)
      Storage/                           ← file-storage abstraction (S3 planned)
      I18n/                              ← locale loader, per-module registration
      Http/                              ← SecurityHeaders, LogApiAccess, ReadTokenFromCookie
      Plugins/                           ← Plugin contract, registrar, lifecycle
      Contracts/                         ← public interfaces (ServiceModuleContract, PluginContract, IntegrationAdapterContract)
    tests/
    routes.php                           ← platform routes only

  modules/
    jea-services/                        ← JEA "service definition catalog" + Application lifecycle
      src/
        Http/Controllers/                ← ApplicationController (split into thin), ServiceCatalogController
        Models/                          ← Application, ApplicationDocument, ApplicationReview, ServiceDefinition, Certificate
        Domain/                          ← JeaWorkflowAdapter, JeaFeeCalculator, JeaSchemaContract
        Services/                        ← application-lifecycle services
      database/migrations/               ← JEA tables
      database/seeders/                  ← ServicePlan2026Seeder etc.
      routes.php                         ← /applications/*, /services/*
      module.php                         ← module manifest (name, version, dependencies, routes, seeders, migrations)
      tests/

    jea-projects/                        ← Project, engineer/office primitives
      src/
        Models/                          ← Project, Engineer, OfficeCeiling, OfficeBoostFlags, EngineerDisciplineQuota
        Http/Controllers/                ← ProjectController, EngineerController, OfficeSettingsController
        Services/
      database/migrations/
      routes.php
      module.php
      tests/

    jea-discipline/                      ← Complaints, sanctions, legal fines, supervision transfers
      src/
        Http/Controllers/                ← ComplaintController, LegalFineController, SupervisionTransferController
        Models/                          ← Complaint, Sanction, LegalFine, SupervisionTransfer
        Services/                        ← SupervisionTransferService
      routes.php
      module.php
      tests/

    jea-dues/                            ← F-04/F-05 recurring obligations
      src/
        Http/Controllers/                ← RecurringDuesController, MyOfficeController (dues slice)
        Models/                          ← RecurringObligation
        Services/                        ← RecurringDuesService
        Console/Commands/                ← OpenAnnualDues
      routes.php
      module.php
      tests/

  plugins/
    ai-schema/                           ← Claude schema-gen (optional)
      src/                               ← AI schema controllers, chat-update-schema
      routes.php
      plugin.php                         ← plugin manifest
    captcha/                             ← Text-captcha challenge + middleware
      src/
      routes.php
      plugin.php

  integrations/
    gsb/                                 ← GSB integration adapter
      src/                               ← GsbController, GsbIpWhitelist
      routes.php
      integration.php                    ← adapter manifest
    nashmi/                              ← Nashmi PM integration adapter
      src/                               ← IntegrationController, ValidateIntegrationKey
      routes.php
      integration.php

  app/                                   ← Laravel scaffolding (kept minimal); ModuleServiceProvider discovers + registers modules
  bootstrap/app.php                      ← boots platform + iterates enabled modules/plugins/integrations
  config/modules.php                     ← list of enabled modules/plugins/integrations
```

### 4.2 Frontend layout

```
frontend/src/
  platform/                              ← domain-neutral SPA foundation
    api/                                 ← http.ts, queryClient, auth, notifications, users, hooks (platform slice)
    auth/                                ← AuthContext, AuthProvider, guards, LoginPage
    components/                          ← ErrorBoundary, LanguageSwitcher, NotificationBell
    design-system/                       ← Button, Modal, ConfirmDialog, FormField, TextField, PageHero, Bilingual
    layout/                              ← Layout, Header, RouteSuspense, NavRegistry (replaces static navItems)
    engine/                              ← DynamicForm, DocumentUploader, DocumentPreviewCard (contract-driven)
    utils/                               ← csv, errorMessage, useSortableRows, SortHeader
    i18n/                                ← loader + platform locale
    contracts/                           ← ServiceModuleContract, PluginContract, SchemaContract (TS)
    routes/                              ← generic route registry; NO service routes
    pages/                               ← Login, ChangeCredentials, Profile, ErrorPage

  modules/
    jea-services/                        ← JEA applicant lifecycle
      pages/                             ← Dashboard, ServiceList, CategoryServicesView, Apply, MyApplications, ApplicationDetail, ProjectsList, ProjectDetail, ProjectContextHeader, MyOffice
      api/                               ← applications, projects, engineers, myOffice, services, review
      components/                        ← WorkflowStepper, ServiceInfoCard, ComplianceNotesBanner, QuotaCard, MiniStageTimeline, JEALogo
      types/                             ← Application, ServiceDefinition, Certificate, Engineer, Project, JeaServiceSchema
      i18n/                              ← ar.json + en.json for JEA copy
      routes.tsx                         ← contributes /dashboard, /services, /apply/:code, /my-applications, /applications/:id, /projects, /my-office
      nav.tsx                            ← contributes applicant lane
      module.ts                          ← module manifest
      tests/

    jea-reviewer/                        ← JEA review surface
      pages/                             ← ReviewQueue, ReviewPanel, ReviewDashboard
      routes.tsx                         ← /review/*
      nav.tsx
      module.ts

    jea-discipline/                      ← Admin queues for complaints, fines, transfers
      pages/                             ← ComplaintsAdmin, LegalFinesAdmin, SupervisionTransfersAdmin
      api/                               ← complaint + legalFine + supervisionTransfer clients
      types/
      routes.tsx                         ← /admin/complaints, /admin/legal-fines, /admin/supervision-transfers
      nav.tsx
      module.ts

    jea-dues-admin/                      ← Admin dues editor
      pages/                             ← OfficeDues, OfficesList, OfficeSettings
      routes.tsx
      nav.tsx
      module.ts

    jea-service-admin/                   ← ServicesList, NewService, EditService, ServiceFeesAdmin
      pages/
      routes.tsx
      nav.tsx
      module.ts

  plugins/
    ai-schema/                           ← Chat-schema panel in NewService/EditService
      components/                        ← wires AI-schema hooks into the JEA service-admin
      module.ts

    captcha/                             ← <Captcha /> widget
      components/
      module.ts

  integrations/
    nashmi/                              ← IntegrationCycles + IntegrationCycleDetail
      pages/
      api/
      module.ts

  App.tsx                                ← composition root; discovers modules via manifest
  main.tsx                               ← boot
```

### 4.3 Contracts (shared vocabulary)

Backend (PHP interfaces / abstract classes):

- `ServiceModuleContract` — register(module): applies routes, migrations, seeders, nav entries, i18n bundles, permissions.
- `PluginContract` — register(plugin): same shape as ServiceModule + `dependencies[]`, `requiredPlatformVersion`, lifecycle hooks (install/uninstall/enable/disable).
- `IntegrationAdapterContract` — same shape as Plugin with an extra `credentials` schema hook.
- `SchemaContract` — `getFields()`, `getWorkflow()`, `getDocuments()` — the workflow engine consumes only this.
- `TransitionContract` — opaque transition id + label + guard + result state.
- `NotificationChannelContract` — for pluggable email/SMS/push providers.
- `StorageContract` — for pluggable file storage.

Frontend (TS types):

- `ServiceModule` — `{ id, routes: RouteDescriptor[], navItems: NavItem[], i18n: Partial<LocaleBundle>, mount?(ctx), unmount?(ctx) }`.
- `Plugin`, `IntegrationAdapter` — mirror the backend contracts.
- `SchemaContract` — TS interface consumed by DynamicForm / DocumentUploader.

---

## 5. Workstream roadmap (§15)

16 workstreams. Each is independently reviewable and (mostly) can land
as its own PR. Numbers are dependencies, not calendar days.

| # | Workstream | Scope | Depends on | Acceptance |
|---|------------|-------|------------|-----------|
| **1** | **Baseline capture** | This doc + `00-baseline.md`. | — | Merged. |
| **2** | **Architecture inventory** | This doc's §2 + §3. | 1 | Merged. |
| **3** | **Dependency analysis + enforcement tests (scaffold)** | Add dep-cruiser + eslint-plugin-import + PHPUnit `ArchitectureBoundariesTest`. Rules START as warnings, not errors. | 2 | dep-cruiser + eslint rules land, first `ArchitectureBoundariesTest` file exists (may be empty). |
| **4** | **Component classification (mechanical)** | Add `@platform` / `@jea` / `@plugin` JSDoc / PHP docblock tags per file. Update inventory doc with concrete file counts. No file moves. | 2 | Every file in the inventory has a tag; inventory diff-clean. |
| **5** | **Platform-core cleanup — backend** | Extract `AdminController` into `AdminDashboardController` (platform) + `AiSchemaController` (plugin). Split `ApplicationController` into `ApplicationsController` (CRUD) + `ReviewQueueController` (reviewer) + `ReviewDashboardController` (reviewer dashboard). Preserve every route via aliasing. | 3, 4 | Every test still green; new controller files ≤ 200 lines each. |
| **6** | **Platform-core cleanup — frontend** | Split `api/admin.ts` into `api/platform/admin.ts` + `api/jea/admin.ts`. Split `api/hooks.ts` similarly. Split `types/index.ts` into `types/platform.ts` + `types/jea.ts`. NO route or file relocation yet. | 3, 4 | Same tests green; imports still work via re-export barrels. |
| **7** | **Service module extraction — backend, jea-dues** | Move `RecurringDuesController`, `MyOfficeController` (dues slice), `RecurringObligation`, `RecurringDuesService`, `OpenAnnualDues` into `backend/modules/jea-dues/`. Set up `ModuleServiceProvider` boot mechanism. First service module to prove the pattern. | 5 | Feature suite still green; disabling the module in `config/modules.php` drops dues routes cleanly. |
| **8** | **Service module extraction — remaining backend modules** | Repeat pattern for `jea-services` (applications + service catalog), `jea-projects`, `jea-discipline` (complaints + fines + transfers). | 7 | Feature suite green. Enforcement tests pass. Each module can be disabled independently. |
| **9** | **Shared technical component consolidation** | Move `frontend/src/utils/*` and platform design-system into `frontend/src/platform/`. Update imports. | 6 | Vitest + tsc green. |
| **10** | **Frontend modularization** | Move `pages/applicant/*` → `modules/jea-services/pages/`, `pages/reviewer/*` → `modules/jea-reviewer/pages/`, JEA admin pages → `modules/jea-*-admin/pages/`. Introduce `ModuleRegistry` that composes routes + navItems. | 6, 9 | SPA still renders every existing page; navigation identical. |
| **11** | **API standardization** | Standardize response envelope + error shape + pagination + correlation id across every controller. Add `ApiResponse` helper. | 8 | Every 2xx / 4xx / 5xx follows one envelope; API contract tests green. |
| **12** | **Data-ownership separation** | Enforce "each module owns its migrations + tables". Move JEA-specific migrations into their module folders. Add `PlatformMigrationsOnlyTest` that fails if a platform-neutral migration references a JEA table. | 8 | Every migration lives in its owning module; `php artisan migrate:fresh` still bootstraps. |
| **13** | **Plugin architecture** | Introduce `PluginContract` + registrar. Migrate Claude AI schema-gen + Captcha into `backend/plugins/*`. Introduce `plugins/*/plugin.php` manifests. | 8, 11 | Disabling the AI plugin removes the AI routes; SPA still boots. |
| **14** | **Integration-adapter separation** | Migrate GSB + Nashmi into `backend/integrations/*` with `IntegrationAdapterContract`. | 13 | GSB / Nashmi routes disappear when the adapter is disabled. |
| **15** | **Architecture-enforcement tests (promotion)** | Promote dep-cruiser + eslint + PHPUnit rules from **warning** to **error**. Add contract tests: "platform imports no jea module", "no service imports another service's internals", "no controller imports a model from a different module". | 3, 8, 10, 13, 14 | CI fails on any boundary violation. |
| **16** | **Documentation + migration guide** | Author `docs/architecture/modules.md`, `docs/architecture/plugins.md`, `docs/architecture/adding-a-service-module.md`, `docs/architecture/adding-a-plugin.md`. Update `README.md`. | 15 | Docs cover every extension point with a code example. |

**Recommended execution order:** 1, 2, 3, 4, then pairs (5 + 6), 7 as
proof-of-concept, 8, then (9 + 10), then (11 + 12), then 13 → 14 → 15
→ 16.

**Total realistic effort:** ~4-6 weeks of focused work if a single
engineer owns it, less if parallelised.

---

## 6. Architecture enforcement design (Workstream 3 + 15)

### 6.1 Frontend — `dep-cruiser` + `eslint-plugin-import`

`.dependency-cruiser.js` rule sketch:

```js
module.exports = {
  forbidden: [
    { name: 'platform-cannot-import-modules',
      severity: 'error',
      from: { path: '^src/platform' },
      to:   { path: '^src/modules|^src/plugins|^src/integrations' } },

    { name: 'module-cannot-import-another-module',
      severity: 'error',
      from: { path: '^src/modules/([^/]+)' },
      to:   { path: '^src/modules/(?!$1)' } },

    { name: 'service-cannot-reach-platform-internals',
      severity: 'error',
      from: { path: '^src/(modules|plugins|integrations)' },
      to:   { path: '^src/platform/.*/internal/' } },

    { name: 'no-cross-module-nav-import',
      severity: 'error',
      from: { path: '^src/layout' },
      to:   { path: '^src/modules' } },
  ],
};
```

### 6.2 Backend — PHPUnit architecture test

`tests/Architecture/BoundariesTest.php`:

```php
public function test_platform_does_not_reference_a_service_module(): void
{
    $offenders = $this->grepImports(
        under: base_path('platform/'),
        matching: '/^App\\\\Modules\\\\/',
    );
    $this->assertEmpty($offenders, ...);
}

public function test_module_does_not_reference_another_module(): void { ... }

public function test_controller_layer_has_no_business_decisions(): void
{
    // heuristic: no `if ($status === '...')` or hardcoded enums in controllers
}
```

### 6.3 CI wiring

- `npm run arch:check` runs dep-cruiser + eslint rules; blocks PR if
  any violation.
- `php artisan test --testsuite=Architecture` runs backend boundary
  tests.
- Both wired into the existing test job.

---

## 7. Decision log — key architectural choices

| Decision | Options | Chosen | Rationale |
|----------|---------|--------|-----------|
| Deploy shape | (a) modular monolith, (b) microservices, (c) split now | **modular monolith** | Refactor spec §7. No independent-scaling need today. Distributed complexity would swallow the ticket-work budget. |
| Module boundary tool | (a) folder convention only, (b) Composer packages + separate autoload, (c) dep-cruiser + eslint + PHPUnit tests | **(c)** with folder convention | Composer sub-packages is heavy; folder convention alone is unenforceable. Automated tests catch violations at PR time. |
| Where to put JEA services | (a) top-level `jea/`, (b) `backend/modules/jea-*`, (c) leave in place with tags | **(b)** | Namespacing signals "this is a module family" without prescribing a single mega-module. |
| Plugin contract | (a) reuse Laravel service providers, (b) custom `PluginContract`, (c) event-bus only | **(b) + (a)** | Custom contract for lifecycle + config schema; internally each plugin still boots via a service provider. |
| Frontend module wiring | (a) manual imports in App.tsx, (b) Vite glob-import module manifests, (c) dynamic-import lazy loader | **(b)** | Compile-time discovery; no runtime plugin server; still allows lazy code-split via `React.lazy` inside each module. |
| Locale split | (a) per-module JSON files merged at boot, (b) shared JSON with namespace prefix per module, (c) leave co-mingled | **(a)** | Deletable per-module: disabling `jea-services` removes `myApplications.*` keys automatically. |
| Where does `ServiceDefinition` live | (a) platform, (b) jea-services, (c) split with contract | **(c)** | The schema-blob primitive (id, code, blob) is platform; the shape of the blob is JEA. Contract enforces neutrality. |
| Backward compat | (a) preserve every route + import path, (b) publish v2 API alongside v1, (c) rename freely | **(a) during refactor, (c) after Workstream 15** | The ticket-fix PR is waiting; every re-route in the refactor is a review risk multiplier. Preserve, then rename after enforcement lands. |

---

## 8. Deliverables checklist (Refactor Spec §17)

Legend: ✅ complete, 🟡 partial / in this doc, ⬜ future workstream.

| deliverable | status | pointer |
|-------------|--------|---------|
| Baseline (repo state, commit, env, dependencies, current architecture, test results, known failures/warnings) | ✅ | `00-baseline.md` |
| Architecture findings (major structural problems, dep violations, contamination, duplicated / misleading abstractions, circular deps, security/perf concerns) | 🟡 | §3 above (concrete violations, no cycles found) |
| Final architecture (platform, services, plugins, adapters, shared libs, dep direction, ownership, contracts, extension points) | 🟡 | §4 above (proposed, not yet built) |
| Platform–Service responsibility matrix | ✅ | §2 above (per-module classification) |
| Reusable components inventory | 🟡 | §2 above (per-item); public interface / owner / consumers to be filled per component during Workstream 4 |
| Service isolation evidence | ⬜ | after Workstream 8; verified by Workstream 15 tests |
| Testing (before/after, new tests, arch-boundary tests, plugin tests, service isolation tests, regression, gaps) | 🟡 | baseline recorded (`00-baseline.md`); after-refactor pending |
| Change inventory (created/modified/moved/renamed/removed/deprecated files, new modules/contracts/extension points) | ⬜ | per workstream |
| Migration and compatibility (breaking changes, back-compat measures, DB migrations, config, API, frontend, plugin/module migration, rollback) | ⬜ | per workstream + final `03-migration-guide.md` |
| Remaining work (technical debt, deferred decisions, risks, known limitations, next phases) | ✅ | §9 below |

---

## 9. Remaining work at end of this session

- **Workstream 3** (dep-cruiser + eslint + PHPUnit boundary scaffolds) — **safest first execution target**. Adds tests as warnings only; catches violations without breaking the build. Ready to start same-session if approved.
- **Workstream 4** (mechanical file tagging) — low-risk, high-visibility.
- **Workstream 5–14** — actual refactoring; each is its own PR. Should NOT start same-session because each is 200+ file touches minimum and needs its own review round.
- **JORD-4** (the PM's file-structure ticket) is fulfilled once Workstreams 8 + 10 land — but the ticket's phrasing is vague; propose an ADR ("Adopting modular monolith architecture") to be attached to the ticket before starting Workstream 5.

**Recommended posture:** merge PR #2 first (ticket work), then this
architecture branch (baseline + plan), then start Workstream 3 on its
own branch off `main`. That keeps review scope tight for each PR.

**Not-yet-decided:** whether the platform ships as its own composer /
npm package (v2 direction) or stays inside the monorepo as a folder
tree (this document assumes folder tree). Decision deferred to
Workstream 8 when the module count is stable enough to judge.
