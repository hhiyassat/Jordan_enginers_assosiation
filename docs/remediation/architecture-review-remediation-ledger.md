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

#### C-02 · MockPaymentGateway blocked in production
- `ORIGINAL_SEVERITY`: CRITICAL
- `ORIGINAL_EVIDENCE`: `AppServiceProvider.php:54` bound `MockPaymentGateway` unconditionally; `verifyCallback` accepts any payload.
- `IMPLEMENTATION_STATUS`: FIXED_PENDING_PRODUCTION_VALIDATION (real gateway = `BLOCKED_EXTERNAL_INPUT`)
- `FILES_CHANGED`:
  - `backend/app/Providers/AppServiceProvider.php` — Mock binding wrapped in `if (!$this->app->environment('production'))`.
  - `backend/app/Support/ProductionSafety.php` (new) — `checkPaymentGatewayBinding()` aborts boot in production when Mock resolves or when nothing is bound.
- `TESTS_ADDED`: `ProductionSafetyTest::test_mock_payment_gateway_bound_in_production_is_a_violation`, `test_real_payment_gateway_bound_is_ok`.
- `RESIDUAL_RISK`: Deploying to `APP_ENV=production` without a real PaymentGateway binding intentionally aborts boot with a clear error. Ops must add the real gateway binding in a production-only ServiceProvider before deploying.
- `EXTERNAL_DEPENDENCY`: `BLOCKED_EXTERNAL_INPUT` — real gateway provider (eFAWATEERcom / JoMoPay / other) contract, callback signature spec, credentials.

#### C-03 · FakeJeaMembershipVerifier blocked in production + HTTP driver skeleton
- `ORIGINAL_SEVERITY`: CRITICAL
- `ORIGINAL_EVIDENCE`: `JeaServicesServiceProvider.php:100` bound `FakeJeaMembershipVerifier` unconditionally.
- `IMPLEMENTATION_STATUS`: FIXED_PENDING_PRODUCTION_VALIDATION (real endpoint = `BLOCKED_EXTERNAL_INPUT`)
- `FILES_CHANGED`:
  - `backend/modules/JeaServices/Providers/JeaServicesServiceProvider.php` — Fake binding wrapped in non-production check.
  - `backend/modules/JeaServices/Engine/HttpJeaMembershipVerifier.php` (new) — production HTTP driver: typed request/response, configurable auth scheme (bearer/basic/header), timeout+retry, PII-safe error logs, throws on network/non-2xx (never silently accepts).
  - `backend/config/jea.php` (new) — configuration surface for the JEA API (base_url, auth_scheme, auth_token, basic credentials, timeout, retry policy).
  - `backend/app/Support/ProductionSafety.php` — `checkJeaMembershipVerifierBinding()` aborts boot when Fake resolves in production.
- `TESTS_ADDED`:
  - `ProductionSafetyTest::test_fake_jea_verifier_bound_in_production_is_a_violation`, `test_http_jea_verifier_bound_is_ok`.
  - `HttpJeaMembershipVerifierTest` (7 tests): empty inputs, valid+invalid endpoint responses, missing reason default, 5xx throws, missing base_url throws.
- `RESIDUAL_RISK`: The real driver is written against a defensible request/response shape (POST `{name, membership_number}` → `{is_valid, reason_ar}`); if the real JEA contract differs, the mapping in `HttpJeaMembershipVerifier` needs one method-body edit — callers depend only on the interface.
- `EXTERNAL_DEPENDENCY`: `BLOCKED_EXTERNAL_INPUT` — real JEA endpoint URL, auth scheme, request/response schema.

#### M-22 · sanctum.php published with absolute token lifetime
- `ORIGINAL_SEVERITY`: MEDIUM
- `ORIGINAL_EVIDENCE`: No `backend/config/sanctum.php` → `expiration` fell back to Sanctum's default `null` → tokens never expired absolutely.
- `IMPLEMENTATION_STATUS`: FIXED
- `FILES_CHANGED`: `backend/config/sanctum.php` (new) with `'expiration' => (int) env('SANCTUM_EXPIRATION_MINUTES', 480)`; ProductionSafety enforces positive value in production.
- `TESTS_ADDED`: `ProductionSafetyTest::test_sanctum_expiration_null_is_a_violation`, `test_sanctum_expiration_positive_is_ok`.

#### P0-E · Production readiness validator
- `ORIGINAL_SEVERITY`: (composite of many boot-time invariants)
- `IMPLEMENTATION_STATUS`: FIXED
- `FILES_CHANGED`:
  - `backend/app/Support/ProductionSafety.php` (new) — centralized checklist: PaymentGateway binding, JeaMembershipVerifier binding, FILESYSTEM_DISK, QUEUE_CONNECTION, CACHE_STORE, SESSION_DRIVER, APP_DEBUG, SESSION_SECURE_COOKIE, SESSION_HTTP_ONLY, sanctum.expiration, GSB_ALLOWED_IPS, NASHMI_SIGNING_SECRET, CAPTCHA_ENABLED.
  - `backend/app/Providers/AppServiceProvider.php` — `boot()` now calls `ProductionSafety::enforce($this->app)`. No-op outside production.
- `TESTS_ADDED`: `ProductionSafetyTest` (18 tests): one negative + one positive per invariant, plus a test that `enforce()` throws with a clear multi-line message when app env is production.

#### C-04 · Cadastral / OwnerMatch guard TOCTOU
- `ORIGINAL_SEVERITY`: CRITICAL
- `ORIGINAL_EVIDENCE`: Guard reads outside submit transaction; two concurrent submits from different orgs with same parcel triple can both pass and both transition.
- `IMPLEMENTATION_STATUS`: FIXED
- `FILES_CHANGED`: `backend/modules/JeaServices/Engine/WorkflowEngine.php` — re-evaluates `CrossCuttingSubmissionPipeline::validate($app)` inside the atomic `DB::transaction` scope prior to transition.
- `TESTS_ADDED`: `CadastralPortabilityAndToctouTest::test_toctou_prevention_on_concurrent_submit`.
- `TESTS_RUN`: 811 pass.

#### C-05 / H-09 · Postgres portability / cadastral indexing
- `ORIGINAL_SEVERITY`: CRITICAL
- `ORIGINAL_EVIDENCE`: `CadastralPriorApplicationLookup.php` used `json_extract` raw SQL, failing on PostgreSQL and doing unindexed table scans.
- `IMPLEMENTATION_STATUS`: FIXED
- `FILES_CHANGED`:
  - `backend/modules/JeaServices/Database/Migrations/2026_07_30_130000_add_cadastral_columns_to_applications_table.php` — adds indexed `basin_number` and `parcel_number` columns and backfills existing rows.
  - `backend/modules/JeaServices/Models/Application.php` — auto-syncs `basin_number` and `parcel_number` on model save.
  - `backend/modules/JeaServices/Engine/CadastralPriorApplicationLookup.php` — uses portable Eloquent `where('basin_number', ...)->where('parcel_number', ...)` leveraging the composite index.
- `TESTS_ADDED`: `CadastralPortabilityAndToctouTest::test_cadastral_columns_auto_sync_on_save`.
- `TESTS_RUN`: 811 pass.

### High

#### H-04 · Nashmi inbound security (HMAC signature + replay window + nonce + IP allowlist)
- `ORIGINAL_SEVERITY`: HIGH
- `ORIGINAL_EVIDENCE`: Nashmi webhook only validated simple API key without body signature or replay protection.
- `IMPLEMENTATION_STATUS`: FIXED
- `FILES_CHANGED`: `backend/integrations/Nashmi/Http/Middleware/ValidateIntegrationKey.php` — enforces HMAC-SHA256 over raw request body, strict timestamp replay window (300s), nonce deduplication store, and IP allowlist enforcement in production.
- `TESTS_ADDED`: `NashmiSecurityTest` (4 tests).
- `TESTS_RUN`: 4 pass.

#### H-10 · Queue architecture for asynchronous operations
- `ORIGINAL_SEVERITY`: HIGH
- `ORIGINAL_EVIDENCE`: Synchronous execution of notifications, integration webhooks, and dues generation in HTTP request lifecycle.
- `IMPLEMENTATION_STATUS`: FIXED
- `FILES_CHANGED`:
  - `backend/app/Jobs/ProcessNotificationJob.php`
  - `backend/integrations/Nashmi/Jobs/ProcessNashmiOutboundJob.php`
- `TESTS_ADDED`: `QueueJobsTest` (2 tests).
- `TESTS_RUN`: 2 pass.

#### H-05 · GSB IP whitelist fails closed in production when empty
- `ORIGINAL_SEVERITY`: HIGH
- `ORIGINAL_EVIDENCE`: `GsbIpWhitelist::handle` logged a warning and allowed all traffic when `config('gsb.allowed_ips')` was empty. Violated MODEE Annex 4.15 §4.5 rule 11 silently in any production where ops forgot to set `GSB_ALLOWED_IPS`.
- `IMPLEMENTATION_STATUS`: FIXED
- `FILES_CHANGED`: `backend/integrations/Gsb/Http/Middleware/GsbIpWhitelist.php` — empty allowlist in `app()->environment('production')` returns 403 and logs `critical`. Non-production keeps the permissive warning behavior so dev isn't locked out.
- `TESTS_ADDED`: `GsbSecurityTest::test_empty_allowlist_in_production_denies_access`, `test_empty_allowlist_in_local_still_allows_with_warning`, `test_configured_allowlist_permits_matching_ip_in_any_env`, `test_configured_allowlist_denies_non_matching_ip`.
- `TESTS_RUN`: 779 pass.
- `EXTERNAL_DEPENDENCY`: The actual production IP allowlist values remain `BLOCKED_EXTERNAL_INPUT`.

#### H-06 · GSB error-path log no longer dumps raw response body
- `ORIGINAL_SEVERITY`: HIGH
- `ORIGINAL_EVIDENCE`: `GsbClient::call` logged up to 500 bytes of the raw response body on 4xx/5xx, bypassing `stripImageFields`. GSB error responses regularly contain citizen PII (MODEE §4.5.2 violation).
- `IMPLEMENTATION_STATUS`: FIXED
- `FILES_CHANGED`: `backend/integrations/Gsb/Services/GsbClient.php` — the `Log::warning('GSB call failed', …)` context now contains `url`, `status`, `body_length` only.
- `TESTS_ADDED`: `GsbSecurityTest::test_gsb_error_log_does_not_contain_raw_response_body` — uses `Log::spy` + `Http::fake` to prove the log context excludes the raw body and any PII markers.
- `TESTS_RUN`: 779 pass.
- `RESIDUAL_RISK`: Debugging a production GSB incident now requires reproducing with dev-side capture or querying GSB directly; there is no on-disk raw-body trail.

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

#### H-02 · Application reference-number atomic counter
- `ORIGINAL_SEVERITY`: HIGH
- `ORIGINAL_EVIDENCE`: `Application::generateReference()` computed the next sequence with `Application::count() + 1`. Two concurrent submits for the same (service, year) both saw N and both wrote N+1 — one insert died on `reference_number` unique index.
- `IMPLEMENTATION_STATUS`: FIXED
- `FILES_CHANGED`:
  - `backend/modules/JeaServices/Database/Migrations/2026_07_30_120000_create_application_counters_table.php` (new) — table `application_counters(service_definition_id, year, next_serial)` with unique `(service_definition_id, year)`. Reversible.
  - `backend/modules/JeaServices/Models/ApplicationCounter.php` (new).
  - `backend/modules/JeaServices/Models/Application.php` — `generateReference()` now delegates to `allocateReferenceSerial()` which mirrors the H-03 pattern: unconditional INSERT (swallow duplicate-key), then SELECT FOR UPDATE, then increment. Wrapped in `DB::transaction(..., attempts: 5)`.
- `TESTS_ADDED`:
  - `ApplicationReferenceSerialTest` (4 tests): strictly monotonic sequence, pre-existing counter row honored, uniqueness across many allocations, per-service independence.
- `TESTS_UPDATED`:
  - `ApplicationReferenceTest::test_sequence_increments_per_service_per_year` and `test_sequence_is_independent_across_services` were pinning the old count-based semantics (seeded rows influencing the sequence). Updated to consume from the counter directly, matching the new correct contract.
- `TESTS_RUN`: 809 pass / 1 skipped. Migration rollback + re-migrate verified.
- `RESIDUAL_RISK`: True cross-process concurrency verification requires PostgreSQL CI (still `BLOCKED_EXTERNAL_INPUT` — see C-05).
- `BACKFILL`: The migration seeds each `(service, year)` counter from `MAX(numeric_seq) + 1` over pre-existing 10-digit references. Legacy alpha references (`ESP-XXX-…`) are ignored. Runs at migration time in driver-agnostic PHP so it works on SQLite, PostgreSQL, and MySQL without dialect-specific SQL.

#### H-03 · Certificate first-per-year serial race
- `ORIGINAL_SEVERITY`: HIGH
- `ORIGINAL_EVIDENCE`: `WorkflowEngine::allocateCertificateSerial` at line 693-714 called `firstOrCreate` OUTSIDE the `lockForUpdate` scope. The FIRST-ever allocation for a given (org, year) had a race window: two concurrent submits both hit `firstOrCreate`, the loser got a duplicate-key violation, and the outer `DB::transaction` had `attempts=1` (no retry).
- `IMPLEMENTATION_STATUS`: FIXED
- `FILES_CHANGED`: `backend/modules/JeaServices/Engine/WorkflowEngine.php` — the allocation now issues an unconditional INSERT, swallows the `UniqueConstraintViolationException` (which is the expected race outcome), then proceeds to `lockForUpdate` — which is guaranteed to find the row now. Both winner and loser reach the lock and each gets a distinct serial.
- `TESTS_ADDED`: `CertificateSerialAllocationTest::test_first_issue_when_counter_row_already_exists_uses_that_next_serial` — pre-inserts a counter row (simulating a concurrent winner with `next_serial=7`) and verifies the allocation returns 7 (not overwritten) and advances the counter to 8.
- `TESTS_RUN`: 805 pass.

### Medium
M-01 through M-22 — see final report §5.

### Low
L-01 through L-13 — see final report §5.

---

## Change log

- **2026-07-30** — C-01 · Superuser scope restricted to user-management only. 771 pass, +37 tests.
- **2026-07-30** — H-01 · Null-org authenticated users now fail closed (zero rows) with a security warning. 774 pass, +3 tests.
- **2026-07-30** — H-05 + H-06 · GSB integration hardening: IP whitelist fails closed in production; error-path log redacts body. 779 pass, +5 tests.
- **2026-07-30** — M-22 + P0-E + C-02 + C-03 · Production boot safety: sanctum.php published (480-min absolute lifetime), ProductionSafety validator (12 invariants), Mock/Fake bindings restricted to non-production, HttpJeaMembershipVerifier skeleton + config/jea.php. 804 pass, +25 tests. External blockers: real PaymentGateway driver + JEA endpoint contract.
- **2026-07-30** — H-03 · Certificate first-per-year serial allocation now handles the concurrent-first-writer race by swallowing UniqueConstraintViolationException and falling through to FOR UPDATE. 805 pass, +1 test.
- **2026-07-30** — H-02 · Application reference-number allocation now uses an atomic per-(service, year) counter (application_counters). Backfill migration seeds legacy references. 809 pass, +4 tests, 2 legacy tests updated to match counter semantics.
- **2026-07-30** — C-04 + C-05/H-09 · Cadastral database portability & TOCTOU race fix: indexed basin/parcel columns, Eloquent query without json_extract, atomic pipeline re-validation inside submit transaction. 811 pass, +2 tests.
- **2026-07-30** — H-04 · Nashmi inbound security: HMAC-SHA256 signature, 300s timestamp replay window, nonce store, and IP allowlist enforcement. 4 pass.
- **2026-07-30** — H-10 + Operations · Queue architecture (ProcessNotificationJob, ProcessNashmiOutboundJob) & production deployment assets (Dockerfile, docker-compose, supervisor config, k6 performance scripts).
- **2026-07-31** — Follow-ups to previous remediation batch: NotificationService::sendToUser (unblocks ProcessNotificationJob at runtime); ProductionSafety guards use `get_class()===` rather than `instanceof` for exact-class match; service_definitions.description_ar/en widened to text; e2e helper skips captcha input when hidden. 817 pass.
- **2026-07-31** — P2 dead code · Four unused `esp.php` keys removed (`default_sla_hours`, `max_upload_size_mb`, `rate_limit_login`, `rate_limit_api`). The two dead files (`frontend/src/pages/applicant/ApplicationDetail.tsx`, `JeaDrawingsSeeder.php`) were removed by commit 8969eeb. `backend/dain-out-saleh.txt` remains untracked and undeleted per the review rule for user-owned material of unknown provenance. 817 pass.
- **2026-07-31** — P4 doc reconciliation · README, BUILD_CONTRACT, METHODOLOGY_AUDIT corrected from `Laravel 12 / PHP 8.2+ / MySQL 8` to `Laravel 13 / PHP 8.3+ (tested on 8.5) / PostgreSQL / MySQL 8 (SQLite for dev+test)`. The 2026-07-30 handoff was edited in place with the correct test counts (817 backend / 413 frontend / 6 e2e specs / 12 e2e scenarios) and the stale ApplicationDetail row removed — but left uncommitted because the handoff file itself is still an untracked user-owned document. When the user promotes it to git, the corrections are already in the working tree.
- **2026-07-31** — Migration reversibility · Fixed `add_cadastral_columns_to_applications_table` down() — SQLite failed to drop `basin_number` / `parcel_number` because the auto-generated single-column indexes (`applications_basin_number_index`, `applications_parcel_number_index`) still referenced them. down() now drops all three indexes (compound + two single) before dropping the columns. Full reversibility verified: fresh → rollback:step=1 → migrate → migrate:reset → migrate all clean.
- **2026-07-31** — P0-E-2 + L-11 + L-12 · Security event logging + observability polish. Added dedicated `security` log channel (365-day retention). Introduced `App\Support\SecurityEvents` emitter with typed helpers for login_success/failed, logout, password_changed, token_revoked, authorization_denied, integration_signature_failure, payment_callback_failure. Wired AuthController (login/logout/password change) + CheckRole (authz denial) + Nashmi ValidateIntegrationKey (four failure modes). LogApiAccess now reads the correlation-id from CorrelationId middleware's attribute bag instead of minting a duplicate. Added `/api/ready` readiness endpoint (DB + cache probes). 822 pass, +5 tests.
- **2026-07-31** — P1-08 · Password policy uplift. Bumped min length 8 → 12; require symbols; optional HIBP `->uncompromised()` gated by `esp.password_check_compromised` (default off in dev/CI, enforced true in prod by ProductionSafety). Added `password_history` table + `App\Support\PasswordHistory` + `PasswordPolicy::distinctFromHistoryFor(user)` — every password-accepting endpoint (register, admin create, admin update, changePassword, `user:credentials` CLI) now records the new hash and rejects reuse of the last 5. 829 pass, +7 tests; 2 legacy tests updated to compliant passwords; PlatformMigrationsOnlyTest allowlist extended with `password_history`.
- **2026-07-31** — P1-09 · Document download endpoint. `GET /api/v1/applications/{id}/documents/{docId}` routes through `findAccessible()` (org scope + applicant-own-only for applicants), then looks up the document scoped by application_id. Missing storage-file returns 404 rather than a stack trace. Response marked private + no-store + nosniff. 834 pass, +5 tests covering owning applicant, same-org reviewer, cross-org 404, cross-application 404, missing file 404.
- **2026-07-31** — P1-10 · Authenticated certificate PDF download. New `GET /api/v1/applications/{id}/certificate/pdf` uses the same org-scope + applicant-own-only guard — no token in URL. The SPA switches to this endpoint so the qr_token never lands in browser history / referrer / upstream logs. Public `/certificates/{n}/pdf?token=…` retained for QR-scan verification of physical certificates (which cannot be rotated). Extracted the PDF-rendering pipeline into `renderPdf()` shared by both paths. 839 pass, +5 tests: owning applicant no-token happy path, same-org reviewer, cross-org 404, 410 revoked, 404 no-cert-yet.
- **2026-07-31** — P1-05 · Cross-tenant negative tests. Added CrossTenantIsolationTest (11 assertions) covering AdminDashboardController::allApplications + auditLogs, ProjectController::show + index, EngineerController::show + index, OfficeSettingsController::show + update, PaymentsController::confirm, CertificatesController::issue, ReviewQueueController::index. Complements the existing cross-org tests for UserManagementController, RecurringDuesController, ComplaintController, LegalFineController, SupervisionTransferController. 850 pass, +11 tests.
- **2026-07-31** — M-09 + L-10 + L-13 · Notification hygiene + missing FK constraints. Notification model now uses `BelongsToOrganization` (defense-in-depth in addition to the existing per-user filter). Added `notifications:prune` command scheduled daily at 02:30, retention windows configurable via `esp.notification_retention_days` (default 180) and `esp.notification_unread_retention_days` (default 365). New migration wraps `office_registration_requests.reviewed_by_user_id` / `approved_organization_id` in FK constraints (`nullOnDelete`) so orphan rows can't accumulate. SQLite path is a no-op — SQLite cannot ADD constraints to existing columns; Postgres/MySQL enforce them. 853 pass, +3 NotificationsPruneTest.
- **2026-07-31** — CI · PostgreSQL matrix job added. New `backend-postgres` job in `.github/workflows/ci.yml` boots Postgres 15 as a service container and runs the full backend PHPUnit suite against `DB_CONNECTION=pgsql`. `phpunit.xml`'s DB env vars marked `force="false"` so the CI job env wins. Together with the pre-existing sqlite job this closes C-05's "no Postgres CI matrix" gap. Status FIXED_PENDING_PRODUCTION_VALIDATION — job is authored and locally-consistent but cannot be exercised without a GitHub Actions run.
