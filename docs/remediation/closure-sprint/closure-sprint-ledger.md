# Closure Sprint Ledger

Sprint start branch: `remediation/architecture-security-production-readiness`
Sprint start HEAD:   `a4224fcceff08a73d7c348b4a3324417fe66a413`

## Item log

| Item | Report | Status | Commit | Notes |
| ---- | ------ | ------ | ------ | ----- |
| CS-01 | [CS-01-new-a1-production-safety.md](CS-01-new-a1-production-safety.md) | FIXED | `5105257` | Nashmi signing-secret config key corrected + regression tests. |
| CS-02 | [CS-02-queue-runtime-integration.md](CS-02-queue-runtime-integration.md) | FIXED | `5d0252f` | Wired both jobs to real dispatchers + shipped jobs/failed_jobs/job_batches migrations + WithoutOverlapping idempotency + real worker integration test. |
| CS-03 | [CS-03-payment-gateway-runtime.md](CS-03-payment-gateway-runtime.md) | FIXED | `89bfc40` | Wired PaymentGateway into runtime: initiate + callback + admin-only manual reconciliation + signed-test gateway + idempotent callback table. |
| CS-04 | [CS-04-application-lookup-contract.md](CS-04-application-lookup-contract.md) | FIXED | `3f7883a` | Contract adopted by SanctionGuard (first production consumer). snapshotOf() helper added; SM_ALLOWED_IMPORTS down from 15 → 14. |
| CS-05 | [CS-05-cross-module-boundaries.md](CS-05-cross-module-boundaries.md) | PARTIALLY_FIXED | `0f33728` | Detector strengthened to catch app()/resolve()/new; 4 hidden resolves now allowlisted; frontend cross-module import removed; optional-module boot test added. 14 documented backend couplings still in retirement backlog. |
| CS-06 | [CS-06-office-registration-captcha.md](CS-06-office-registration-captcha.md) | FIXED | `ee31466` | Captcha middleware wired to public office-registration submit; 6 tests covering missing/invalid/expired/replayed/valid + ProductionSafety guard. |
| CS-07 | [CS-07-nashmi-required-nonce.md](CS-07-nashmi-required-nonce.md) | FIXED | `dff547c` | Nonce required in production; atomic Cache::add() dedupe; namespaced by signing-secret fingerprint so key rotation invalidates old nonces. |
| CS-08 | [CS-08-application-reviews-index.md](CS-08-application-reviews-index.md) | FIXED | `484b363` | Composite (reviewer_id, created_at) index; EXPLAIN on Postgres 15 confirms both dashboard queries use it (~9500x speedup on recent-decisions). |
| CS-09 | [CS-09-docker-runtime-stack.md](CS-09-docker-runtime-stack.md) | FIXED | `5f1d305` | Repaired Dockerfile (nginx/supervisord/php-fpm configs, redis ext, .dockerignore); six-service compose (app+worker+scheduler+pg+redis+minio) up-tested; live queued job round-trip. |
| CS-10 | [CS-10-remaining-medium-low-findings.md](CS-10-remaining-medium-low-findings.md) | PARTIALLY_FIXED | `aa3c019` | 4 findings FIX_NOW (L-04 env→config, NEW-A4/A5 dead-code deletion, NEW-A11 password-change rate limit); 5 explicit backlog; 2 NOT_REPRODUCIBLE with counter-evidence; residual-backlog.md written. |

## Final report

[final-closure-sprint-report.md](final-closure-sprint-report.md) — sprint truth table + gate results.

## Governance

- Working under P0 R2 emergency single-human amendment (Hussein sole signatory; Claude Code executor; ChatGPT reviewer).
- Local commits only. No push, no tag, no merge, no force-reset.
- User-owned untracked files preserved throughout.
