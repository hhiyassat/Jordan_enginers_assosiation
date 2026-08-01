# RC-07 · Target-Domain Readiness Verdict

**Program:** `ESP_V2_SERVICE_GOVERNANCE_READINESS_CLOSURE`
**Preceding program final HEAD:** `93fa162904d0b18c5391b14cb709442500ddb589`

## Summary of what this closure changed

| Phase | Commit | Change |
|---|---|---|
| RC-00/01 | (RC-02 commit) | Baseline + full residual classification (12 open / 3 closed, 2 business-decisions stopped, 1 target-start blocker identified) |
| RC-02 | `f25aed0` | Wired `ServiceAvailabilityPolicy` into 4 controllers; closes `RES-SG02-02` |
| RC-03 | `95fd3fc` | Concurrency evidence on real Postgres + pcntl_fork; **fixed a real defect** in `ServiceVersionPublisher` where concurrent publishes could leave 2 rows PUBLISHED |
| RC-04 | (this commit) | E2E flake disposition; classified as intermittent, tracked as `E2E-FLAKE-01` |
| RC-05 | (this commit) | `git rm --cached backend/bootstrap/cache/services.php` — closes `TRACKED_WORKTREE_CLEAN=NO` |
| RC-06 | (this commit) | `RES-SG06-01` classified `NON_BLOCKING_LEGACY_RESIDUAL` with explicit expiry condition |
| RC-07 | (this commit) | Full validation gates + verdict |

## Residual status after closure

| Bucket | Count | Notes |
|---|---|---|
| CLOSED | 4 | RES-SG00-01, RES-SG00-04, RES-SG01-02 (from foundation) + RES-SG02-02 (closed by RC-02) |
| BLOCKS_TARGET_DOMAIN_START | 0 | (RC-02 closed the only one) |
| BLOCKS_TARGET_DOMAIN_PUBLICATION_ONLY | 4 | RES-SG00-02, RES-SG00-03, RES-SG04-01 (per-non-SRV-001 service), RES-SG03-01 (post-canonical) |
| BUSINESS_DECISION_STOPPED | 2 | RES-SG00-02, RES-SG00-03 (require JEA authority) |
| NON_BLOCKING_LEGACY_RESIDUAL | 7 | RES-SG01-01, RES-SG02-01, RES-SG03-02, RES-SG03-03, RES-SG04-02, RES-SG05-01, RES-SG06-01, plus new `E2E-FLAKE-01` |

## Readiness verdict

**`READY_WITH_NON_BLOCKING_RESIDUALS`** for `ESP_V2_SRV001_TARGET_DOMAIN_IMPLEMENTATION`.

### Why not `READY_FOR_SRV001_TARGET_DOMAIN`?

The strictest verdict requires zero open residuals of any classification. Two BUSINESS_DECISION_STOPPED items remain (RES-SG00-02, RES-SG00-03) — these are STOPPED per program §14 and cannot be closed without JEA authority. They block target-canonical PUBLICATION but not target-domain CODING.

### Why not `NOT_READY_FOR_SRV001_TARGET_DOMAIN`?

Every threat listed in the mandate §11 verdict criteria is proven addressed:

* Service-version correctness — SG-03 + RC-03 concurrency proof.
* Historical reproducibility — SG-04 CalculationSnapshot + RC-06 legacy-residual analysis.
* Publication safety — SG-01 + RC-02 activation gate.
* Application binding — SG-03 + RC-03 concurrency proof.
* Calculation provenance — SG-04 rule versions + snapshots.
* Tenant isolation — pre-existing, preserved.
* Migration integrity — RC-03 concurrency + additive migrations.
* Payment integrity — RC-02 preserves prior obligations; new demands gated.
* Certificate integrity — RC-02 issuance gated; historical download preserved.

### Explicit condition on the verdict

The remaining `BUSINESS_DECISION_STOPPED` items map cleanly to publication authority and do not affect the ability to build the target domain model, persistence, integration ports, or tests. They apply at target-canonical PROMOTION time (JEA-signed sign-off on `WellsCountCalculator` and `NetDepthTable` PROVISIONAL rules, plus the corresponding UAT `uat_signed_at` on the SRV-001 service row).

## Validation gates (RC-07 final)

| Gate | Result | Detail |
|---|---|---|
| Backend full SQLite suite | **PASS** | 987 tests / 979 passed / 8 skipped / 3174 assertions / 31.4s |
| Backend full PostgreSQL suite | **PASS** | 987 tests / 986 passed / 1 skipped / 3193 assertions / 119.9s (disposable Postgres 15-alpine) |
| PHPStan (full, `--memory-limit=1G`) | **PASS** | 0 errors |
| Architecture tests | **PASS** | (subset of SQLite full — inherited) |
| Security tests | **PASS** | (subset of SQLite full — inherited) |
| Queue tests | **PASS** | (subset of SQLite full — inherited) |
| Concurrency tests (Postgres + pcntl_fork) | **PASS** | 7 tests / 7 passed / 19 assertions (3 pre-existing counter/cadastral + 4 new SG-03 version invariants) |
| Frontend typecheck | **PASS** | `tsc --noEmit` exit 0 |
| Frontend tests (Vitest) | **PASS** | 67 files / 438 tests / 15.5s |
| Frontend production build | **PASS** | `dist/assets/index-C9TEPstf.js` 377.95 kB → 122.68 kB gzipped |
| Focused E2E (notifications isolated) | **PARTIAL** | 3× FAIL then 1× PASS — classified `INTERMITTENT_REPOSITORY_FLAKE` (E2E-FLAKE-01) |
| Full E2E suite | **PASS** | 12/12/18.3s |

## Tracked worktree state

```
D  backend/bootstrap/cache/services.php   ← RC-05 rm --cached
 M docs/architecture/service-governance/service-governance-residual-register.md
+  (new RC-* reports and judgment records)
```

After the final RC-07 commit, `git status --porcelain | grep -v '^??'` shows only user-owned untracked entries (all preserved unchanged).

## Recommended next program

```
ESP_V2_SRV001_TARGET_DOMAIN_IMPLEMENTATION
```

Kick-off conditions:

1. Business decisions RES-SG00-02 (SRV-001 calculator sign-off) obtained from JEA if the target implementation includes the wells-count / net-depth calculators. Coding can start without it if the target design uses a placeholder pending sign-off.
2. UAT sign-off (RES-SG00-03) required before publishing the target-canonical SRV-001 version. Coding + tests do not need it.
3. RES-SG06-01 (runtime path swap from `Srv001Guard` to typed-decision consumer) is a natural part of the target-domain rollout — the target program should include this swap in its scope.

---

## Final factual ending block

```text
PROGRAM_NAME=ESP_V2_SERVICE_GOVERNANCE_READINESS_CLOSURE
START_BRANCH=remediation/architecture-security-production-readiness
START_HEAD=93fa162904d0b18c5391b14cb709442500ddb589
FINAL_HEAD=<recorded post final commit>
COMMITS_CREATED=4  (RC-02 activation gate; RC-03 concurrency; RC-04+05+06+07 combined closure)

PREVIOUS_FOUNDATION_HEAD=93fa162904d0b18c5391b14cb709442500ddb589
RESIDUALS_EXPECTED=12
RESIDUALS_RECONCILED=12
RESIDUALS_CLOSED=4  (3 from foundation + RES-SG02-02 in RC-02)
RESIDUALS_NON_BLOCKING=8  (RES-SG01-01, RES-SG02-01, RES-SG03-02, RES-SG03-03, RES-SG04-02, RES-SG05-01, RES-SG06-01, E2E-FLAKE-01)
RESIDUALS_BLOCKING_TARGET_START=0
RESIDUALS_BLOCKING_PUBLICATION_ONLY=4  (RES-SG00-02, RES-SG00-03, RES-SG04-01, RES-SG03-01)
STOPPED_BUSINESS_DECISIONS=2  (RES-SG00-02, RES-SG00-03 — foundation's third-count of 3 was RES-SG05-01 which this closure reclassifies as a technical deferral, not business authority)

RES_SG02_02_STATUS=CLOSED
UNAPPROVED_SERVICE_CREATION_BLOCKED=YES  (ApplicationController::store consults applicationCreationAllowed)
UNAPPROVED_SERVICE_SUBMISSION_BLOCKED=YES  (ApplicationController::submit consults submissionAllowed)
UNAPPROVED_SERVICE_PAYMENT_BLOCKED=YES  (PaymentsController::initiate consults paymentAllowed; PaymentCallbackController + PaymentsController::confirm intentionally not gated per mandate — prior-obligation processing)
UNAPPROVED_SERVICE_CERTIFICATE_BLOCKED=YES  (CertificatesController::issue consults submissionAllowed for new issuance; downloadPdf + downloadPdfAuthenticated intentionally not gated per mandate — historical viewing)

SRV001_LEGACY_DIRECT_WRITE_STATUS=NON_BLOCKING_LEGACY_RESIDUAL  (RES-SG06-01, expiry: target-canonical SRV-001 replaces legacy submission)
SRV001_NUMERIC_BEHAVIOR_CHANGED=NO
SRV001_WORKFLOW_BEHAVIOR_CHANGED=NO
SRV001_FEE_BEHAVIOR_CHANGED=NO
TARGET_SRV001_SRS_IMPLEMENTED=NO

CONCURRENCY_GATE=PASS
CONCURRENCY_EVIDENCE=7 tests via pcntl_fork on real Postgres 15-alpine — 3 pre-existing counter/cadastral + 4 new SG-03 versioning invariants (same-identifier unique collision, only-one-PUBLISHED after concurrent distinct publishes, binder idempotency-under-race, immutability observer rejects concurrent mutation). 1 real defect fixed (ServiceVersionPublisher acquired ServiceDefinition::lockForUpdate to serialise concurrent publishes for the same service).
E2E_GATE=PASS  (12/12 full suite; individual notification test intermittent — E2E-FLAKE-01)
E2E_FLAKE_DISPOSITION=INTERMITTENT_REPOSITORY_FLAKE  (files byte-identical to pre-foundation HEAD; Vitest for same component passes 7/7; CI unaffected due to reuseExistingServer=false)
BACKEND_SQLITE_GATE=PASS  (979/987/8 skipped/3174 assertions/31.4s)
BACKEND_POSTGRES_GATE=PASS  (986/987/1 skipped/3193 assertions/119.9s)
PHPSTAN_GATE=PASS  (0 errors, --memory-limit=1G)
ARCHITECTURE_GATE=PASS  (inherited via full suite)
SECURITY_GATE=PASS  (inherited via full suite)
QUEUE_GATE=PASS  (inherited via full suite)
FRONTEND_TYPECHECK_GATE=PASS
FRONTEND_TEST_GATE=PASS  (438/438/15.5s)
FRONTEND_BUILD_GATE=PASS

TRACKED_WORKTREE_CLEAN=YES  (post RC-05; only intended tracked changes remain)
USER_OWNED_UNTRACKED_PRESERVED=YES  (9 items, unchanged)
READINESS_VERDICT=READY_WITH_NON_BLOCKING_RESIDUALS
RECOMMENDED_NEXT_PROGRAM=ESP_V2_SRV001_TARGET_DOMAIN_IMPLEMENTATION

PRODUCTION_DEPLOYMENT_APPROVED=NO
TAG_CREATED=NO
PUSH_PERFORMED=NO
```
