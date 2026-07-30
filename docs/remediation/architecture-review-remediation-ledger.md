# Architecture-Review Remediation Ledger

**Source:** Independent architecture review of HEAD `41e27a3a53b14da115ec63d72d9d97b6e9f535d4` (2026-07-30).
**Branch:** `remediation/architecture-security-production-readiness`
**Baseline verdict:** `ACCEPT_WITH_MAJOR_REMEDIATION`.

## Status vocabulary

| Status | Meaning |
|---|---|
| `OPEN` | Not yet started |
| `IN_PROGRESS` | Started, not landed |
| `FIXED` | Implemented + tested + committed on this branch |
| `FIXED_PENDING_PRODUCTION_VALIDATION` | Implemented + tested locally; requires validation in a real environment (e.g., PostgreSQL CI, real payment sandbox) |
| `BLOCKED_EXTERNAL_INPUT` | Cannot be honestly completed without external contracts/credentials/business input |
| `NOT_REPRODUCIBLE` | Original evidence no longer reproduces on this branch |
| `SUPERSEDED_WITH_EVIDENCE` | Superseded by a more authoritative implementation; evidence recorded |

## Baseline

- `START_BRANCH=main`
- `START_HEAD=41e27a3a53b14da115ec63d72d9d97b6e9f535d4`
- `START_WORKTREE_STATUS=clean-plus-untracked-user-material` (regenerated `bootstrap/cache/services.php` + Arabic reference docs + today's handoff — all preserved)
- `PHP_VERSION=8.5.7`
- `COMPOSER_VERSION=2.10.1`
- `LARAVEL_VERSION=13.8+`
- `NODE_VERSION=v22.22.0`
- `NPM_VERSION=10.9.4`
- `REACT_VERSION=^18.2.0`
- `TYPESCRIPT_VERSION=^5.2.2`
- `DATABASE_DRIVER=sqlite (in-memory for tests; dev file DB)`
- `BACKEND_TEST_BASELINE=<to be filled after clean run>`
- `FRONTEND_TEST_BASELINE=<to be filled>`
- `E2E_TEST_BASELINE=<to be filled>`

## Findings

### Critical

#### C-01 · Restrict superuser authority to user-management only
- `ORIGINAL_SEVERITY`: CRITICAL
- `ORIGINAL_EVIDENCE`: `User::canEditServices()` returns true for superuser; `User::isReviewer()` includes superuser; every `role:admin,superuser` route middleware admits superuser to non-user-management surfaces (services catalog, AI schema, projects admin, discipline admin, dues admin).
- `IMPLEMENTATION_STATUS`: FIXED
- `FILES_CHANGED`:
  - `backend/app/Models/User.php` — `isReviewer()` drops `'superuser'`; `canEditServices()` returns admin only
  - `backend/routes/api.php` — admin dashboard block `role:admin,superuser` → `role:admin`
  - `backend/plugins/AiSchema/routes.php` — same
  - `backend/modules/JeaProjects/routes.php` — same
  - `backend/modules/JeaDiscipline/routes.php` — same
  - `backend/modules/JeaServices/routes.php` — same
  - `backend/modules/JeaDues/routes.php` — same
  - `backend/modules/JeaServices/Http/Controllers/ManualReferenceController.php` — 3 inline `isAdmin() \|\| isSuperuser()` → `isAdmin()`
  - `backend/modules/JeaServices/Http/Controllers/OfficeRegistrationController.php` — `authorizeAdmin` drops superuser
- `TESTS_ADDED`:
  - `backend/tests/Feature/SuperuserScopeTest.php` (new): 3 named tests + 33 data-provider entries covering every JEA-business admin endpoint (dashboard, applications, audit-logs, service catalog CRUD + lock/unlock + fees, manual refs, office regs, projects admin, discipline admin, dues admin, AI schema); 2 positive tests confirming user-management still works; 1 model-helper unit test.
- `TESTS_UPDATED`:
  - `backend/tests/Feature/ServiceLockingTest.php` — `test_superuser_can_toggle_lock` → `test_superuser_cannot_toggle_lock`; `test_superuser_can_edit_after_unlock` → `test_superuser_cannot_edit_services`. These tests previously pinned the incorrect behavior.
  - `backend/tests/Unit/Models/UserRolesTest.php` — `test_can_edit_services_is_admin_or_superuser_only` → `test_can_edit_services_is_admin_only`; added `test_is_reviewer_excludes_superuser`.
- `TESTS_RUN`: `php artisan test` — **771 passed / 1 skipped / 2748 assertions** (baseline 734 → +37, all from new SuperuserScopeTest)
- `COMMIT`: (see git log after this commit)
- `RESIDUAL_RISK`: None internal. Post-deploy, verify any external tooling that assumed superuser could reach the JEA admin surface (there is none in-repo).
- `EXTERNAL_DEPENDENCY`: None.

#### C-02 · Replace MockPaymentGateway (production-safe abstraction + boot guard)
- `ORIGINAL_SEVERITY`: CRITICAL
- `ORIGINAL_EVIDENCE`: `AppServiceProvider.php:54` binds `MockPaymentGateway` unconditionally; `verifyCallback` accepts any payload.
- `IMPLEMENTATION_STATUS`: OPEN
- `EXTERNAL_DEPENDENCY`: real gateway (eFAWATEERcom or equivalent) provider contract, callback specification, credentials — `BLOCKED_EXTERNAL_INPUT` for the real driver.

#### C-03 · Replace FakeJeaMembershipVerifier (production-safe abstraction + boot guard + HTTP driver skeleton)
- `ORIGINAL_SEVERITY`: CRITICAL
- `ORIGINAL_EVIDENCE`: `JeaServicesServiceProvider.php:100` binds `FakeJeaMembershipVerifier` unconditionally; fake accepts any non-empty name+number pair.
- `IMPLEMENTATION_STATUS`: OPEN
- `EXTERNAL_DEPENDENCY`: real JEA membership verification endpoint URL + auth scheme + response schema.

#### C-04 · Cadastral / OwnerMatch guard TOCTOU
- `ORIGINAL_SEVERITY`: CRITICAL
- `ORIGINAL_EVIDENCE`: Guard reads outside submit transaction; two concurrent submits from different orgs with same parcel triple can both pass and both transition.
- `IMPLEMENTATION_STATUS`: OPEN

#### C-05 · Postgres portability / no CI matrix / json_extract used in cadastral guard
- `ORIGINAL_SEVERITY`: CRITICAL
- `ORIGINAL_EVIDENCE`: `CadastralPriorApplicationLookup.php:112-113` uses `json_extract`; `.github/workflows/ci.yml:32` SQLite-only.
- `IMPLEMENTATION_STATUS`: OPEN

### High

#### H-01 · Null-org silent scope no-op → fail-closed
- `ORIGINAL_SEVERITY`: HIGH
- `ORIGINAL_EVIDENCE`: `OrganizationScope::apply` returned without filtering when `Auth::user()->organization_id` was null. Any authenticated user with a null org (integration user, corrupted row, misconfigured account) read every tenant's data unfiltered.
- `IMPLEMENTATION_STATUS`: FIXED
- `FILES_CHANGED`:
  - `backend/app/Models/Concerns/OrganizationScope.php` — null-org branch now filters `whereRaw('1 = 0')` (zero rows) and logs `org_scope.null_org_authenticated_user` warning.
  - `backend/app/Models/Concerns/BelongsToOrganization.php` — `scopeForCurrentOrganization` mirrored fail-closed; docblock updated.
- `TESTS_ADDED`:
  - `BelongsToOrganizationTest::test_null_org_authenticated_user_sees_zero_rows_via_global_scope`
  - `BelongsToOrganizationTest::test_null_org_authenticated_user_sees_zero_rows_via_for_current_organization`
  - `BelongsToOrganizationTest::test_null_org_can_still_use_without_org_scope_for_explicit_cross_tenant` (documents the escape hatch)
- `TESTS_RUN`: 774 passed / 1 skipped / 2751 assertions
- `RESIDUAL_RISK`: A future code path that runs `Auth::login($user)` with a `$user` whose org happens to be null will now get empty results instead of accidental cross-tenant data. If such a path exists intentionally, it must call `::withoutOrgScope()` explicitly.
- `EXTERNAL_DEPENDENCY`: None.

- H-02 · Application reference-number race (`Application::generateReference` count()+1)
- H-03 · First-per-year certificate serial race (firstOrCreate outside FOR UPDATE lock)
- H-04 · Nashmi integration lacks HMAC signature + timestamp + replay + IP allowlist
- H-05 · GSB IP whitelist fails open when unconfigured
- H-06 · GSB error-path log dumps raw response body (PII)
- H-07 · Bidirectional cross-JEA-module coupling
- H-08 · Platform User/Organization import JEA models
- H-09 · Cadastral guard unindexed JSON scan (overlaps with C-05)
- H-10 · No queues; expensive work sync in HTTP
- H-11 · REQUIREMENTS.md out of sync (OTP, MP4, autosave-to-cache, reference format)
- H-12 · MockPaymentGateway + FakeJeaMembershipVerifier are prod defaults (rolled into C-02/C-03)

### Medium
M-01 through M-22 — see final report §5.

### Low
L-01 through L-13 — see final report §5.

---

## Change log

- **2026-07-30** — C-01 · Superuser scope restricted to user-management only. 771 pass, +37 tests.
- **2026-07-30** — H-01 · Null-org authenticated users now fail closed (zero rows) with a security warning. 774 pass, +3 tests.
