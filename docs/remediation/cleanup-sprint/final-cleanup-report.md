# Cleanup Sprint · Final Report

**Branch:** `remediation/architecture-security-production-readiness`
**Start HEAD:** `89833fb070cc71fa0c11cb27e5ac7f8ce550122a`
**Date range:** 2026-08-01

Executed the seven cleanup phases strictly one at a time, using the
six audit deliverables under `/tmp/esp-v2-*` as authoritative
decision inputs. Every phase produced one focused commit and one
report; every commit was preceded by focused + containing + static
gates.

## Per-phase summary

| Phase | Report | Commit | Result |
|---|---|---|---|
| Phase 0 | `cleanup-ledger.md` | included with CL-01 | Decision table matches audit totals (1 dead file + 4 dead config keys + 1 unfinished scaffold + 3 CONSOLIDATE + 8 KEEP_SEPARATE + 3 BACKLOG). |
| CL-01 | [CL-01-dead-service-definition-snapshot.md](CL-01-dead-service-definition-snapshot.md) | `38a39a4` | Deleted `App\Contracts\Services\ServiceDefinitionSnapshot` + updated stale doc-comment in `BoundariesTest`. |
| CL-02 | [CL-02-unused-config-keys.md](CL-02-unused-config-keys.md) | `3be07ed` | The four dead `esp.*` keys were already absent from the active config array. Tightened the P2 cleanup comment to lock the deprecation closed. `SUPERSEDED_WITH_EVIDENCE`. |
| CL-03 | [CL-03-unfinished-scaffold-decision.md](CL-03-unfinished-scaffold-decision.md) | `0a44c38` | Pinned `HttpJeaMembershipVerifier` as `RESERVED_EXTENSION_WITH_CONTRACT`; documented the BLK-02 activation contract. Doc-only. |
| CL-04 | [CL-04-dg01-org-scoped-lookups.md](CL-04-dg01-org-scoped-lookups.md) | `bb748fb` | Added `BelongsToOrganization::findForOrganizationOrFail(int $orgId, int|string $id): static`. Converted 7 Application sites in `PaymentsController` + `CertificatesController` + `ReviewQueueController`. Added 5 tests (same-org / cross-org / null-org / no-scope-bypass). |
| CL-05 | [CL-05-dg09-engineer-project-scoped-lookups.md](CL-05-dg09-engineer-project-scoped-lookups.md) | `fcdb7a0` | DG-09 uses `office_user_id` / `owner_user_id` (JORD-77 office-owner scope), not `organization_id`. Column-parameterised helper matches the audit's warned-against generic pattern. `SUPERSEDED_TO_KEEP_SEPARATE_WITH_EVIDENCE`. |
| CL-06 | [CL-06-dg13-user-management-scoped-lookups.md](CL-06-dg13-user-management-scoped-lookups.md) | `4896e29` | DG-13 superset. Application sites → CL-04. Engineer/Project → CL-05. Residual `User::where('organization_id', ...)` sites: `User` is the auth entity and deliberately doesn't use `BelongsToOrganization`. `SUPERSEDED_TO_KEEP_SEPARATE_WITH_EVIDENCE`. |
| Phase 6 | [justified-duplication-register.md](justified-duplication-register.md) | `45a0318` | 8 backend + 3 frontend KEEP_SEPARATE groups documented with review triggers. |
| Phase 7 | [duplicate-consolidation-backlog.md](duplicate-consolidation-backlog.md) | `cfd92cd` | 3 BACKLOG entries (BL-DG-04 submission gate, BL-DG-08 admin tables, BL-DG-14 hidden sibling resolves) with acceptance criteria + effort. |

Eight commits total.

## Final validation gates

| Gate | Result | Detail |
|---|---|---|
| Backend SQLite full suite | PASS | 907 tests / 903 passed / 4 skipped / 2992 assertions / 31.1s |
| Backend PostgreSQL full suite | PASS | 907 tests / 906 passed / 1 skipped / 3000 assertions / 96.6s (disposable Docker Postgres 15, disposed after run) |
| PHPStan | PASS | 0 errors on full analyse (default config) |
| Frontend typecheck | PASS | `tsc --noEmit` exit 0 |
| Frontend tests | PASS | 67 files / 438 tests / 0 failures / 11.7s |
| Frontend production build | PASS | Vite build exit 0; `dist/assets/index-C9TEPstf.js` 377.95 kB → 122.68 kB gzipped |
| Playwright E2E | PASS | 12 tests / 12 passed / 16.9s (chromium) |
| Architecture tests | PASS | 16 tests / 15 passed / 1 skipped |
| Security tests (composite) | PASS | 117 tests / 117 passed / 168 assertions across ProductionSafety + Nashmi security + nonce + GsbSecurity + SuperuserScope + CrossTenantIsolation + Payment + OfficeRegistrationCaptcha + BelongsToOrganization |
| Real PostgreSQL concurrency | PASS | 3 tests / 3 passed / 8 assertions (pcntl_fork on Postgres 15) |
| Queue worker integration | PASS | 1 test / 9 assertions (`RealWorkerIntegrationTest` on database driver) |
| Docker smoke | NOT_APPLICABLE | No deployment/Dockerfile changes in this sprint |

## Sprint truth (not "no duplication remains" — see mandate)

* **ALL_CONFIRMED_DEAD_CODE_REMOVED** — Yes. Only DEAD_CONFIRMED
  item in scope (`ServiceDefinitionSnapshot`) was deleted; the four
  config keys were already absent (comment tightened to lock the
  deprecation closed).
* **ALL_AUDIT_CONSOLIDATE_DECISIONS_RECONCILED** — Yes. DG-01 fully
  consolidated (7 sites → helper). DG-09 + DG-13 explicitly
  reclassified `SUPERSEDED_TO_KEEP_SEPARATE_WITH_EVIDENCE` per the
  sprint mandate's escape hatch.
* **JUSTIFIED_DUPLICATION_DOCUMENTED** — Yes. 11 groups (8 backend
  + 3 frontend) recorded with review triggers.
* **BACKLOG_DUPLICATION_EXPLICIT** — Yes. 3 backlog groups recorded
  with acceptance criteria + effort estimates.

## Scope discipline

* No files deleted or modified outside the audit's exact findings.
* No user-owned untracked material touched.
* No new architectural changes introduced.
* No consolidation attempted where invariants differed
  (DG-09 + DG-13 residual).

## Required factual ending

```
CLEANUP_START_HEAD=89833fb070cc71fa0c11cb27e5ac7f8ce550122a
CLEANUP_FINAL_HEAD=cfd92cd20a4ed195bd21bc85fa217ae8b71f3580
CLEANUP_BRANCH=remediation/architecture-security-production-readiness
CLEANUP_COMMITS_CREATED=8

DEAD_CONFIRMED_FILE_REMOVED=YES (ServiceDefinitionSnapshot; commit 38a39a4)
DEAD_CONFIG_KEYS_REMOVED=SUPERSEDED_WITH_EVIDENCE (keys were already absent; deprecation comment locked in commit 3be07ed)
UNFINISHED_SCAFFOLD_DECISION=RESERVED_EXTENSION_WITH_CONTRACT (HttpJeaMembershipVerifier; commit 0a44c38)
DG01_ORG_LOOKUP=CONSOLIDATED (7 sites → BelongsToOrganization::findForOrganizationOrFail; commit bb748fb)
OTHER_CONSOLIDATE_GROUP_1=SUPERSEDED_TO_KEEP_SEPARATE_WITH_EVIDENCE (DG-09; commit fcdb7a0)
OTHER_CONSOLIDATE_GROUP_2=SUPERSEDED_TO_KEEP_SEPARATE_WITH_EVIDENCE (DG-13 residual User sites; commit 4896e29)
JUSTIFIED_DUPLICATE_GROUPS_DOCUMENTED=11 (8 backend + 3 frontend; commit 45a0318)
BACKLOG_GROUPS_DOCUMENTED=3 (BL-DG-04, BL-DG-08, BL-DG-14; commit cfd92cd)

BACKEND_SQLITE_GATE=PASS
BACKEND_POSTGRES_GATE=PASS
PHPSTAN_GATE=PASS
FRONTEND_TYPECHECK_GATE=PASS
FRONTEND_TEST_GATE=PASS
FRONTEND_BUILD_GATE=PASS
E2E_GATE=PASS
ARCHITECTURE_GATE=PASS
SECURITY_GATE=PASS
CONCURRENCY_GATE=PASS
QUEUE_GATE=PASS

ALL_CONFIRMED_DEAD_CODE_REMOVED=YES
ALL_AUDIT_CONSOLIDATE_DECISIONS_RECONCILED=YES
JUSTIFIED_DUPLICATION_PRESERVED=YES
EXPLICIT_DUPLICATION_BACKLOG_COUNT=3

REMEDIATION_WORKTREE_CLEAN=YES (0 tracked modifications; 9 user-owned untracked preserved)
USER_OWNED_UNTRACKED_PRESERVED=YES
PRODUCTION_DEPLOYMENT_APPROVED=NO

TAG_CREATED=NO
PUSH_PERFORMED=NO
```
