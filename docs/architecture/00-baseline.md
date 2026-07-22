# Baseline — 2026-07-22

Frozen state of the ESP v2 codebase against which the architectural
refactoring will be measured. Captured immediately before any
refactoring work begins, per Governing Refactor Spec §2.

## Repository state

| item | value |
|------|-------|
| repository | `esp-v2` |
| branch (baseline) | `refactor/architecture-baseline-and-plan` |
| forked from | `feat/jord-84-85-86-office-fees-polish` @ `bc8aaed` |
| main | `main` (12 commits behind the feature branch) |
| working tree | clean except three known-safe untracked files (see below) |

**Untracked / never-committed files** (unchanged across every batch on
the feature branch — the handoff doc calls these out too):

- `backend/bootstrap/cache/services.php` — Laravel runtime cache
- `backend/dain-out-saleh.txt` — unrelated user file
- `docs/تدقيق الكتروني.pptx` — user's own doc

## Environment

| runtime | version |
|---------|---------|
| PHP | 8.5.7 (composer constraint `^8.3`) |
| Node | 22.22.0 |
| Laravel | `^13.8` |
| Laravel Sanctum | `*` |
| React | `^18.2.0` |
| React Router | `^6.30.4` |
| React Query | `^5.101.2` |
| i18next | `^24.2.3` |
| TypeScript | `^5.2.2` |
| Vite | `^5.0.0` |
| Vitest | `^4.1.10` |
| Tailwind | `^3.4.1` |

## Codebase size

| slice | count |
|-------|-------|
| Backend controllers (`app/Http/Controllers/Api/`) | 17 |
| Backend models (`app/Models/`) | 25 |
| Backend migrations | 39 |
| Backend seeders | 23 |
| Backend feature tests | 59 files |
| Frontend applicant pages | 27 |
| Frontend reviewer pages | 5 |
| Frontend admin pages | 31 |
| Frontend UI components (`components/ui/`) | 27 |
| Frontend api modules | 18 |
| Frontend total .ts/.tsx files | 157 |
| Locale files | 2 (`ar.json`, `en.json`) |

## Test totals — frozen baseline

| suite | files | tests | assertions |
|-------|-------|-------|------------|
| Backend PHPUnit | 59 | **545 passed** | 2036 |
| Frontend Vitest | 63 | **410 passed** | (assertion count not surfaced by vitest reporter) |
| Frontend TypeScript build (`tsc && vite build`) | — | **green** | — |

Zero known failures. Zero skipped tests. Zero warnings that block merge.

## Known technical debt inherited from PR #2's session

- No architecture-boundary tests (nothing prevents `platform` code from
  importing a service module).
- No dependency-cruiser / eslint-plugin-import restriction rules.
- Backend `ApplicationController` is 500+ lines and mixes 3 concerns
  (applications CRUD, review-queue, review-dashboard).
- Backend `AdminController` mixes dashboard, users, audit-logs, and
  AI-schema endpoints (3 unrelated capabilities).
- Backend `ServiceCatalogController` mixes service CRUD, fee editor,
  and locking (all JEA-service-specific but bundled).
- Frontend `pages/admin/` mixes platform-admin surfaces (UserManagement,
  IntegrationCycles) with JEA-specific admin surfaces (OfficeDues,
  ComplaintsAdmin, LegalFinesAdmin, SupervisionTransfersAdmin,
  ServiceFeesAdmin).
- Frontend `pages/reviewer/*` and `pages/applicant/*` are entirely
  JEA-specific but sit at the top level of the source tree.
- `frontend/src/i18n/locales/{ar,en}.json` co-mingle platform strings
  (nav, common, errorBoundary, validation) with JEA-specific strings
  (adminDashboard, reviewPanel, apply, myApplications, projects, etc.).
- `frontend/src/types/index.ts` (346 lines) declares BOTH platform
  types (User, Notification) and JEA-service types (Application,
  ServiceDefinition, Certificate, Engineer, Project, Complaint, etc.).
- No package-level module boundaries; every import goes through
  relative paths.
- No documented plugin contract; the Claude AI schema-generator,
  Captcha, GSB, and Nashmi integrations are hardcoded into the API
  layer.
- Every seeder assumes JEA data (rate tables, workflow stages,
  compliance notes, quotas). No seed layer for a non-JEA tenant.
- `Application::STATUS_*` constants and `service_definition.schema`
  shape are JEA workflow assumptions leaking into the workflow engine.

## Ownership state at baseline

- Single-owner monorepo — no code-owner file, no per-module ownership
  boundary.
- CI: not yet inspected; assumed to run both suites end-to-end on push
  (verified locally before this baseline).
- Deployment: single artifact per stack (Laravel PHP-FPM + Vite build);
  no separation between platform and JEA-service deploy units.

## Behaviour that must be preserved (regression surface)

Everything currently pinned by the 545 backend tests and 410 frontend
tests. Notable invariants that must survive refactoring:

- Every documented API route continues to accept the same requests and
  return the same shape (URLs, HTTP methods, JSON keys).
- `role:` middleware behaviour and the superuser scope rule (project
  memory: superuser is user-management only, not god-mode).
- Cookie-based session cookie behaviour (`ESP_SESSION_LIFETIME_MINUTES`,
  `ESP_SESSION_COOKIE_SECURE`).
- `/auth/me` returns `200 {user: null}` for guests (JORD-84 PM).
- Bilingual UI: every user-facing string flips languages via i18next.
- Cross-office data isolation: office A never sees office B's dues,
  complaints, sanctions.
- Fee editor preserves surcharges across rate edits.
- Review queue role-scoping: non-admin reviewers see only rows their
  role can act on.
- Workflow stage transitions go through `WorkflowEngine` (not raw
  status writes) — the BUILD CONTRACT WF-001 invariant.
- Every state transition emits an `AuditLog` row.

**These invariants are pinned by tests; the refactoring must keep every
existing test green without weakening a single one.**

## What comes next

See `docs/architecture/01-refactoring-plan.md` for the inventory,
contamination findings, target architecture, and workstream roadmap.
