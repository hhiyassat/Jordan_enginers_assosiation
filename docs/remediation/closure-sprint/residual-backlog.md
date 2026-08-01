# Closure Sprint — Residual Backlog

Items the closure sprint deliberately did NOT fix. Each is either
substantially larger than a sprint item, dependent on external input
that isn't available today, or a UX/refactor that is not a current
correctness or security defect.

**No item on this page is described as fixed or closed.** Items that
were closed live under their CS-* report; items that remain external
blockers live under BLK-* in `docs/DECISION_REGISTER.md`.

---

## Backend refactor backlog

### BL-M14 — leading-wildcard admin search

| Field | Value |
|---|---|
| BACKLOG_ID | BL-M14 |
| CURRENT_RISK | Slow admin dashboard search on `applications.reference_number` / `users.name` / `users.email` / service `code`+`name_*`. Read-only, no security exposure, tenant-scoped. |
| WHY_NOT_FIXED_IN_THIS_SPRINT | The current query `LIKE '%needle%'` cannot use a btree index. The correct fix is either a Postgres pg_trgm GIN index or a materialized full-text-search column — both are non-trivial and change query shape on both drivers (SQLite + PostgreSQL). |
| OWNER | backend platform |
| DEPENDENCIES | Postgres 15 in production (already committed); a decision on whether to keep SQLite in dev or drop it entirely (affects choice of pg_trgm vs FTS). |
| ACCEPTANCE_CRITERIA | The admin search endpoint returns in <200ms p95 on a 100k-application dataset via an indexed strategy — either GIN on trigrams or a `tsvector` column with `to_tsvector('arabic', name || …)`. Include a benchmark that FAILS if the plan is a seq scan. |
| PRIORITY | Medium |
| ESTIMATED_EFFORT | 1–2 dev days (index design + migration + benchmark). |

### BL-M16 — `Apply.tsx` God component

| Field | Value |
|---|---|
| BACKLOG_ID | BL-M16 |
| CURRENT_RISK | Testability + maintainability of the applicant-facing form. Not a correctness defect — 12 targeted vitest specs already cover the branch behaviour today. |
| WHY_NOT_FIXED_IN_THIS_SPRINT | Substantial refactor (~663 LOC, deep useEffect coupling with the schema-driven engine, route-param coupling to project context). Splitting into a container + step components without breaking any of the 12 existing tests is a multi-day change. |
| OWNER | frontend platform |
| DEPENDENCIES | An agreement on the target structure (context provider vs. Zustand vs. lifted state). |
| ACCEPTANCE_CRITERIA | No single component in the Apply flow exceeds 250 LOC; existing 12 tests pass unchanged; the DynamicForm + DocumentUploader engines are called from a container that owns state, and the leaf components are presentational. |
| PRIORITY | Low |
| ESTIMATED_EFFORT | 3–5 dev days. |

### BL-M17 — `WorkflowEngine` responsibility split

| Field | Value |
|---|---|
| BACKLOG_ID | BL-M17 |
| CURRENT_RISK | Test surface + change safety. Every transition (submit / claim / release / decide / confirmPayment / issueCertificate) lives in one 738-LOC file. |
| WHY_NOT_FIXED_IN_THIS_SPRINT | The workflow is the most-tested part of the codebase (60+ feature tests); a split risks introducing subtle differences in the audit-log shape, notification emission, and transaction boundaries. |
| OWNER | backend platform |
| DEPENDENCIES | None. |
| ACCEPTANCE_CRITERIA | A `WorkflowEngine` façade delegates to per-verb command classes (SubmitCommand, DecideCommand, ConfirmPaymentCommand, IssueCertificateCommand); no command exceeds 200 LOC; every existing test passes unchanged. |
| PRIORITY | Low |
| ESTIMATED_EFFORT | 2–3 dev days. |

### BL-L01 — JEA widgets in `frontend/src/components/ui/`

| Field | Value |
|---|---|
| BACKLOG_ID | BL-L01 |
| CURRENT_RISK | Ownership boundary confusion — a "shared UI" folder holds JEA-specific components (QuotaCard, PhaseBadge, WorkflowStepper, ExpiryBadge, ServiceInfoCard, ComplianceNotesBanner, RolePathBadge, ManualReferenceIcon). If a non-JEA module ever spun up it would inherit JEA vocabulary from the shared bucket. |
| WHY_NOT_FIXED_IN_THIS_SPRINT | Each widget is imported by 3-8 pages; the move is mechanical but touches every JEA feature module. Doing it during the closure sprint would collide with CS-05's boundary-detector strengthening in a way that requires per-file investigation. |
| OWNER | frontend platform |
| DEPENDENCIES | None. |
| ACCEPTANCE_CRITERIA | `frontend/src/components/ui/` contains only domain-neutral primitives (Captcha, Button, Modal). Every JEA-flavoured widget lives under a JEA module. A vitest passes iff every file under `components/ui/` compiles without importing any `modules/Jea*` type. |
| PRIORITY | Low |
| ESTIMATED_EFFORT | 1 dev day. |

### BL-L08 — `ReportsPanel.tsx` imports JEA types

| Field | Value |
|---|---|
| BACKLOG_ID | BL-L08 |
| CURRENT_RISK | `frontend/src/platform/ui/ReportsPanel.tsx` imports JEA types from the shared `types/index.ts` barrel; violates the platform/JEA import boundary. |
| WHY_NOT_FIXED_IN_THIS_SPRINT | Same class as BL-L01 — a mechanical move that needs a typing plan (either widen `ReportsPanel` to accept a generic Row prop, or move the component to a JEA module). |
| OWNER | frontend platform |
| DEPENDENCIES | Depends on BL-L01 direction. |
| ACCEPTANCE_CRITERIA | `ReportsPanel.tsx` no longer imports any JEA type. Component either lives inside a JEA module or takes a generic `Row` prop with the columns declared inline by the caller. |
| PRIORITY | Low |
| ESTIMATED_EFFORT | 0.5 dev day. |

### BL-CS05-1 — Sibling coupling retirement (14 entries)

| Field | Value |
|---|---|
| BACKLOG_ID | BL-CS05-1 |
| CURRENT_RISK | 14 files remain in `SM_ALLOWED_IMPORTS` after CS-04's SanctionGuard migration. jea-services / jea-projects / jea-discipline cannot be disabled independently. |
| WHY_NOT_FIXED_IN_THIS_SPRINT | Each entry needs either a contract extension (QuotaLedger + CapacityGuard → `QuotaCheck` contract) or an architectural move (FK relations on `LegalFine` / `SupervisionTransfer` → drop the Eloquent relation, keep the FK column, expose reads via a new lookup contract). Every migration is a small refactor with its own tests. |
| OWNER | backend platform |
| DEPENDENCIES | None. |
| ACCEPTANCE_CRITERIA | `SM_ALLOWED_IMPORTS` reaches 0 entries. Every sibling module can be disabled in `config/modules.enabled` without any other module failing to boot. |
| PRIORITY | Medium |
| ESTIMATED_EFFORT | 1 dev week (spread across 6+ smaller PRs). |

### BL-CS03-1 — Real payment provider adapter

| Field | Value |
|---|---|
| BACKLOG_ID | BL-CS03-1 |
| CURRENT_RISK | `MockPaymentGateway` is the only `PaymentGateway` implementation. ProductionSafety refuses to boot production, so this is a hard-blocker gate rather than a silent risk. |
| WHY_NOT_FIXED_IN_THIS_SPRINT | Depends on external inputs the sprint cannot create: provider contract (eFAWATEERcom / JoMoPay / etc.), callback protocol, credentials, IP allowlist. Marked `BLOCKED_EXTERNAL_INPUT`. |
| OWNER | backend platform + business |
| DEPENDENCIES | BLK-01 in DECISION_REGISTER. |
| ACCEPTANCE_CRITERIA | A `ProviderPaymentGateway` implements `PaymentGateway::verifyCallback` against the real provider's signature scheme, is bound in production via a ServiceProvider, and passes a signed-callback contract test. |
| PRIORITY | High (blocks production go-live) |
| ESTIMATED_EFFORT | 2–4 dev days once the provider contract is available. |

### BL-CS03-2 — Refund operator surface

| Field | Value |
|---|---|
| BACKLOG_ID | BL-CS03-2 |
| CURRENT_RISK | `PaymentGateway::refund()` exists as an interface method but no controller invokes it. Ops have no in-app way to trigger a refund. |
| WHY_NOT_FIXED_IN_THIS_SPRINT | Refund UX + authorization rules + business rules on partial refunds are not yet specified. |
| OWNER | product + backend |
| DEPENDENCIES | Business decision on refund policy. |
| ACCEPTANCE_CRITERIA | An admin-only endpoint invokes `PaymentGateway::refund()`, records the operator + rationale, and writes a `application.payment_refunded` audit entry. |
| PRIORITY | Medium |
| ESTIMATED_EFFORT | 1 dev day. |

### BL-CS02-1 — Notification async wiring for JEA emitters

| Field | Value |
|---|---|
| BACKLOG_ID | BL-CS02-1 |
| CURRENT_RISK | `JeaNotificationService` writes `Notification` rows synchronously inside `WorkflowEngine`'s DB transactions. Failures on the notification insert would roll back the workflow decision. |
| WHY_NOT_FIXED_IN_THIS_SPRINT | Making the emitters async requires `dispatchAfterCommit` plumbing throughout WorkflowEngine + coordinating with the JEA notification dedupe logic. CS-02 wired the ONE natural async caller (password change) rather than restructuring the JEA flow. |
| OWNER | backend platform |
| DEPENDENCIES | Redis in production (BL-OPS-1). |
| ACCEPTANCE_CRITERIA | Every JEA notification is dispatched via `ProcessNotificationJob::dispatch()->afterCommit()`. Rolling back a workflow transaction MUST cancel the dispatch (afterCommit accomplishes this). Tests fail iff a WorkflowEngine transaction rollback still enqueues the notification. |
| PRIORITY | Medium |
| ESTIMATED_EFFORT | 1 dev day. |

---

## Operations backlog

### BL-OPS-1 — Redis in production

| Field | Value |
|---|---|
| BACKLOG_ID | BL-OPS-1 |
| CURRENT_RISK | `QUEUE_CONNECTION=database` works (CS-02 shipped the migrations) but redis is preferred for latency and predictability. |
| WHY_NOT_FIXED_IN_THIS_SPRINT | Provisioning decision. |
| OWNER | ops |
| ACCEPTANCE_CRITERIA | Redis endpoint reachable from the app + worker containers; `QUEUE_CONNECTION=redis` set in the production env; queue-worker smoke test passes against the real infra. |
| PRIORITY | Medium |
| ESTIMATED_EFFORT | 0.5 ops day. |

### BL-OPS-2 — Metrics + tracing + error reporting

| Field | Value |
|---|---|
| BACKLOG_ID | BL-OPS-2 |
| CURRENT_RISK | No Prometheus/OpenTelemetry exporter; no Sentry/Bugsnag; a production incident today reads only from stdout logs. |
| WHY_NOT_FIXED_IN_THIS_SPRINT | Not a correctness defect; needs vendor selection + budget approval. |
| OWNER | ops + backend |
| ACCEPTANCE_CRITERIA | `/api/metrics` exposes application counters; unhandled exceptions ship to an error-reporting vendor; a p95 latency dashboard is available. |
| PRIORITY | Medium |
| ESTIMATED_EFFORT | 2 dev days once vendors are chosen. |

### BL-OPS-3 — Backup + DR runbook

| Field | Value |
|---|---|
| BACKLOG_ID | BL-OPS-3 |
| CURRENT_RISK | No documented backup / restore / DR procedure for Postgres, MinIO, or Redis. |
| WHY_NOT_FIXED_IN_THIS_SPRINT | Depends on the deploy target (self-hosted vs cloud). |
| OWNER | ops |
| ACCEPTANCE_CRITERIA | A one-page runbook covers: point-in-time recovery for Postgres, S3-side bucket replication for MinIO, and a restore drill signed off within the last 90 days. |
| PRIORITY | High |
| ESTIMATED_EFFORT | 1–2 ops days per deploy target. |

---

## External blockers (already recorded in `docs/DECISION_REGISTER.md`)

| BLK-ID | Blocker | Sprint action |
|---|---|---|
| BLK-01 | Real payment provider contract + credentials | ProductionSafety continues to abort boot; see BL-CS03-1 above |
| BLK-02 | JEA membership endpoint URL + auth | ProductionSafety continues to abort boot |
| BLK-03 | Nashmi signing-secret rotation policy | CS-07 added atomic nonce dedupe + secret-fingerprint namespacing; policy still owed by ops |
| BLK-04 | GSB IP allowlist values | ProductionSafety continues to abort boot when the list is empty |
| BLK-05 | GitHub Actions CI run of the Postgres matrix job | The sprint verified locally against Docker Postgres 15; CI has not been observed running on a PR |
