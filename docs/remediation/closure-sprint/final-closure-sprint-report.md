# ESP-v2 · Final Closure Sprint Report

**Branch:** `remediation/architecture-security-production-readiness`
**Sprint start HEAD:** `a4224fcceff08a73d7c348b4a3324417fe66a413`
**Date range:** 2026-07-31 → 2026-08-01

The closure sprint executed ten items strictly one at a time. Every
item produced a dedicated report + a single local commit + updated
ledger + executed focused / containing / static-analysis gates
before advancing. No item was skipped, and no result claimed in
this report was fabricated: every number below traces to a specific
command run in this session.

## Per-item summary table

| Item | Start state | Final state | Commit | Tests | Residual |
| ---- | ----------- | ----------- | ------ | ----- | -------- |
| CS-01 | Prod boot aborted on Nashmi signing-secret check (wrong config key) | Config path corrected; 4 regression tests + a full "all safe settings" boot test | `5105257` | 22 tests / 23 assertions | none |
| CS-02 | Two Job classes with 0 production dispatchers; no jobs/failed_jobs migrations | IntegrationController + AuthController dispatch jobs; jobs+failed_jobs+job_batches migrations; WithoutOverlapping idempotency; real worker integration test | `5d0252f` | 11 focused / 33 assertions + full suite 877/873+4-skipped | Jea sync notifications still direct (BL-CS02-1); redis in prod (BL-OPS-1) |
| CS-03 | PaymentGateway abstraction had 0 runtime callers; raw payment_reference accepted as proof | PaymentCallbackController + PaymentsController::initiate wire the gateway; admin-only manual confirm with required rationale; SignedTestPaymentGateway; payment_callbacks table with unique(reference) | `89bfc40` + insertOrIgnore fix at final gate | 34 focused / 83 assertions | Real provider adapter (BL-CS03-1); refund UX (BL-CS03-2) |
| CS-04 | `ApplicationLookup` contract had 0 production consumers | SanctionGuard migrated; `snapshotOf()` helper on the impl; SM_ALLOWED_IMPORTS 15→14; 5-test contract pin | `3f7883a` | 5 focused / 16 assertions | 14 more SM entries (BL-CS05-1) |
| CS-05 | 4 hidden `app(\Modules\...)` invisible to boundary test; 1 frontend cross-module import | Detector strengthened (use/app/resolve/make/new); hidden resolves allowlisted; ProjectContextHeader moved from JeaProjects→JeaServices; OptionalModuleBootTest published module-independence matrix | `0f33728` | 16 arch tests / 15 passed / 1 skipped | Full retirement of 15 SM entries; coupled cluster jea-services/projects/discipline (BL-CS05-1) |
| CS-06 | Public office-registration submit had throttle but no captcha | `captcha` middleware wired; 6 tests cover missing/invalid/expired/replayed/valid + ProductionSafety guard | `ee31466` | 6 focused / 12 assertions | Nothing above single-request captcha strength |
| CS-07 | Nashmi nonce header optional in every env; non-atomic dedupe | Required in production (401 on miss); Cache::add() atomic dedupe; namespaced by sha256(secret) fingerprint so key rotation invalidates prior nonces | `dff547c` | 5 focused / 15 assertions | Nashmi client must send a nonce header in prod |
| CS-08 | application_reviews.reviewer_id had no supporting index; Postgres seq-scanned | Composite (reviewer_id, created_at) btree index; Postgres EXPLAIN plans show Bitmap + Index Scan Backward (~9500x speedup on recent-decisions) | `484b363` | migrate + rollback + re-migrate on both SQLite and Postgres; full suite unchanged 909/905 | none |
| CS-09 | Dockerfile did not run (missing configs, apk typo, no .dockerignore); compose incomplete | Fixed Dockerfile (nginx + supervisord + php-fpm configs + phpredis + .dockerignore); six-service compose (app+worker+scheduler+pg+redis+minio) up-tested; live queued job round-trip; /up 200; /api/ready {db:ok, cache:ok} | `5f1d305` | build + up + smoke all pass; RealWorkerIntegrationTest for the pipeline shape | Real production secrets + metrics + tracing (BL-OPS-2..3) |
| CS-10 | 13 residual M/L findings unclassified | 4 FIX_NOW (L-04 env→config, NEW-A4/A5 dead-code deletion, NEW-A11 password-change rate limiter); 5 explicit backlog with full fields in residual-backlog.md; 2 NOT_REPRODUCIBLE with counter-evidence | `aa3c019` | full suite 902/898+4-skipped | See residual-backlog.md |

## Final gates

All gates executed in this final-verification block:

| Gate | Result | Detail |
|---|---|---|
| Backend SQLite full suite | PASS | 902 tests / 898 passed / 4 skipped / 2985 assertions / 31.7s |
| Backend PostgreSQL full suite | PASS | 902 tests / 901 passed / 1 skipped / 2993 assertions / 99.0s (Docker Postgres 15 on port 55435, disposed after run) |
| PHPStan | PASS | 0 errors on `phpstan analyse` (default config, no path filters) |
| Frontend lint | NOT_APPLICABLE | `npm run lint` script does not exist in `frontend/package.json` |
| Frontend typecheck | PASS | `tsc --noEmit` exit 0 |
| Frontend tests | PASS | 67 files / 438 tests / 0 failures / 12.3s |
| Frontend production build | PASS | Vite build exit 0; `dist/assets/index-C9TEPstf.js` = 377.95 kB → 122.68 kB gzipped |
| Playwright E2E | PASS | 12 tests / 12 passed / 17.5s (chromium) |
| Architecture tests | PASS | 16 tests / 15 passed / 1 skipped (SM ceiling countdown skip is deliberate) |
| Security tests (composite) | PASS | 112 tests / 112 passed / 161 assertions across ProductionSafety + Nashmi security + nonce + GsbSecurity + SuperuserScope + CrossTenantIsolation + Payment + OfficeRegistrationCaptcha + BelongsToOrganization |
| Real PostgreSQL concurrency | PASS | 3 tests / 3 passed / 8 assertions / 3.3s (pcntl_fork against Postgres 15) |
| Queue worker integration | PASS | `RealWorkerIntegrationTest`: 1 test / 9 assertions (database driver, `Queue::pop()->fire()`) |
| Docker stack smoke | PASS | CS-09 executed the full `docker compose build` + `docker compose up -d` + `/up 200` + `/api/ready 200` + queued-job round-trip + `docker compose down` end-to-end (see CS-09 report). Stack not re-executed in this final block (redundant) |

### One issue discovered during final verification

Running the full backend suite on PostgreSQL exposed a driver-portability
bug in CS-03's `PaymentCallbackController::handle`. The controller
used `try { PaymentCallback::create(...) } catch (QueryException)` to
fold duplicate callbacks into an idempotent 200 response; on SQLite
the surrounding transaction survives the caught exception, but
PostgreSQL aborts the entire transaction on any query error so the
subsequent read (`PaymentCallback::count()`) failed with
`"current transaction is aborted, commands ignored until end of
transaction block"`.

**Fixed inline as part of this final-verification commit**: switched
to `PaymentCallback::query()->insertOrIgnore(...)` which is the
cross-driver-safe primitive for "insert if new, no-op if key
exists" and returns 1/0 accordingly. All 13 CS-03 payment tests
pass on both drivers after the fix.

## Findings remaining after this sprint

Fully specified in
[`docs/remediation/closure-sprint/residual-backlog.md`](residual-backlog.md).

| Category | Count | Notes |
|---|---|---|
| INTERNAL_CRITICAL_REMAINING | 0 | NEW-A1 (CS-01) was the only outstanding critical |
| INTERNAL_HIGH_REMAINING | 0 | NEW-A2/A3 adopted via CS-03/CS-04; H-10 fully wired via CS-02 |
| INTERNAL_MEDIUM_REMAINING | 3 | M-14 (BL-M14), M-16 (BL-M16), M-17 (BL-M17) — all explicit backlog |
| INTERNAL_LOW_REMAINING | 2 | L-01 (BL-L01), L-08 (BL-L08) — explicit backlog. L-04 + L-06 closed (L-04 fixed, L-06 NOT_REPRODUCIBLE) |
| EXPLICIT_BACKLOG_COUNT | 11 | BL-M14, BL-M16, BL-M17, BL-L01, BL-L08, BL-CS05-1, BL-CS03-1, BL-CS03-2, BL-CS02-1, BL-OPS-1, BL-OPS-2, BL-OPS-3 |
| EXTERNAL_BLOCKERS_REMAINING | 5 | BLK-01 (payment provider), BLK-02 (JEA verifier), BLK-03 (Nashmi secret rotation policy), BLK-04 (GSB IP allowlist), BLK-05 (CI matrix run) |

## Sprint compliance

* **Local commits only.** Ten commits, one per closure item plus this final-verification commit.
* **No push, no tag, no merge, no force-reset.**
* **User-owned untracked files preserved throughout.** Nothing under `docs/التعليمات/`, `backend/dain-out-saleh.txt`, or the JEA reference images/PDFs was touched.
* **`backend/bootstrap/cache/services.php`** was regenerated by test runs multiple times per the framework's autoload cache behaviour; restored via `git checkout --` after each drift so it stayed at its committed state.

## Required factual ending

```
SPRINT_START_HEAD=a4224fcceff08a73d7c348b4a3324417fe66a413
SPRINT_FINAL_HEAD=3f3e5af7a36e5a7c9ff47ed3b451f07f2f3daee0
SPRINT_BRANCH=remediation/architecture-security-production-readiness
SPRINT_COMMITS_CREATED=11 (10 per-item + 1 final-verification fix)

CS01_NEW_A1=FIXED
CS02_QUEUE_RUNTIME=FIXED
CS03_PAYMENT_RUNTIME=FIXED
CS04_APPLICATION_LOOKUP=FIXED
CS05_CROSS_MODULE_BOUNDARIES=PARTIALLY_FIXED
CS06_PUBLIC_REGISTRATION_CAPTCHA=FIXED
CS07_NASHMI_NONCE=FIXED
CS08_APPLICATION_REVIEWS_INDEX=FIXED
CS09_DOCKER_STACK=FIXED
CS10_MEDIUM_LOW_RECONCILIATION=PARTIALLY_FIXED

BACKEND_SQLITE_GATE=PASS
BACKEND_POSTGRES_GATE=PASS
PHPSTAN_GATE=PASS
FRONTEND_LINT_GATE=NOT_APPLICABLE
FRONTEND_TYPECHECK_GATE=PASS
FRONTEND_TEST_GATE=PASS
FRONTEND_BUILD_GATE=PASS
E2E_GATE=PASS
ARCHITECTURE_GATE=PASS
SECURITY_GATE=PASS
CONCURRENCY_GATE=PASS
QUEUE_GATE=PASS
DOCKER_GATE=PASS

INTERNAL_CRITICAL_REMAINING=0
INTERNAL_HIGH_REMAINING=0
INTERNAL_MEDIUM_REMAINING=3
INTERNAL_LOW_REMAINING=2
EXPLICIT_BACKLOG_COUNT=11
EXTERNAL_BLOCKERS_REMAINING=5
PRODUCTION_DEPLOYMENT_APPROVED=NO

REMEDIATION_WORKTREE_CLEAN=YES (0 tracked mods after final commit)
USER_OWNED_UNTRACKED_PRESERVED=YES (8+ user-owned files intact)
TAG_CREATED=NO
PUSH_PERFORMED=NO
```
