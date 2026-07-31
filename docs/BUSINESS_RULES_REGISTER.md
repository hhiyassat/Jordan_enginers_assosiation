# ESP v2 — Business Rules Register

**Document ID:** ESP-BR-001
**Version:** 1.0
**Status:** ACTIVE (authored 2026-07-31)
**Cited by:** `BUILD_CONTRACT.md` §2 row 6, §4.3

---

## Purpose

Every business rule that the platform enforces is either **Fixed by
Rule** (immutable — changing it requires an ADR + release) or
**Derivable** (configurable via `.env` / config file / schema without
a code deploy). This register makes each rule explicit + traces to
the enforcing file + tests.

Rules that are per-service (schema-driven) are NOT enumerated here —
those live in `service_definitions.schema` and are the point of the
schema-driven engine. This register covers rules that apply above
the schema layer, i.e. platform-wide invariants and cross-cutting
policies.

## Schema

```
ID           BR-<num>
Category     Fixed | Derivable
Rule         one-sentence English statement
Enforced in  file + line pointer
Tests        pinning test class(es)
Change gate  ADR | migration | config | schema
```

## Register

| ID | Category | Rule | Enforced in | Tests | Change gate |
|---|---|---|---|---|---|
| BR-001 | Fixed | Application status must follow `ALLOWED_TRANSITIONS`; no auto-transitions. | `Modules\JeaServices\Engine\WorkflowEngine::ALLOWED_TRANSITIONS` | `WorkflowEngine*`, `StageActionsTest` | ADR |
| BR-002 | Fixed | Every workflow mutation writes an `audit_logs` row with `rule_id` in the SAME `DB::transaction`. | `WorkflowEngine::submit/claim/release/decide/issueCertificate` | full suite | ADR |
| BR-003 | Fixed | Tenant isolation: every tenant-scoped model uses `BelongsToOrganization`; null-org auth users see zero rows. | `App\Models\Concerns\OrganizationScope`, `BelongsToOrganization` | `BelongsToOrganizationTest`, `CrossTenantIsolationTest` | ADR |
| BR-004 | Fixed | Superuser role scope is **user-management only** — no service-catalog / discipline / dues / audit-log write. | routes + `User::isReviewer/canEditServices` | `SuperuserScopeTest` (36 cases) | ADR |
| BR-005 | Fixed | Application reference numbers are `YY(2) + SVC(4) + SEQ(4)`, allocated via an atomic per-(service, year) counter. | `Application::generateReference` + `application_counters` migration | `ApplicationReferenceSerialTest`, `RealConcurrencyOnPostgresTest` | ADR |
| BR-006 | Fixed | Certificate serial format is `CERT-{code}-{YYYY}-{5-digit-seq}`, allocated via an atomic per-(org, year) counter. | `WorkflowEngine::allocateCertificateSerial` + `certificate_counters` migration | `CertificateSerialAllocationTest`, `RealConcurrencyOnPostgresTest` | ADR |
| BR-007 | Fixed | Cadastral conflict guard: two applications from different orgs may not share `(basin, parcel, location)` in a committed status. | `CadastralConflictGuard` + `CrossCuttingSubmissionPipeline` + Postgres advisory lock | `CadastralConflictGuardTest`, `RealConcurrencyOnPostgresTest` | ADR |
| BR-008 | Fixed | Same-owner conflicting parcel requires clearance document (OwnerMatchClearanceGuard). | `OwnerMatchClearanceGuard` | `OwnerMatchClearanceGuardTest` | ADR |
| BR-009 | Fixed | Post-init superuser credential rotation is CLI-only (`php artisan user:credentials`). | `AuthController::changePassword` | `SuperuserFirstLoginTest` | ADR |
| BR-010 | Fixed | `MockPaymentGateway` and `FakeJeaMembershipVerifier` may NOT be bound in `APP_ENV=production`. | `App\Support\ProductionSafety` | `ProductionSafetyTest` | ADR |
| BR-011 | Fixed | Password reuse of the last N (configurable, default 5) hashes is rejected. | `App\Support\PasswordPolicy::distinctFromHistoryFor` + `password_history` table | `PasswordPolicyTest` | ADR |
| BR-012 | Fixed | The Nashmi inbound webhook requires HMAC + timestamp + nonce + IP allowlist (in prod). | `ValidateIntegrationKey` middleware | `NashmiSecurityTest` | ADR |
| BR-013 | Fixed | GSB IP allowlist must be non-empty in `APP_ENV=production`. | `GsbIpWhitelist` middleware + `ProductionSafety` | `GsbSecurityTest`, `ProductionSafetyTest` | ADR |
| BR-014 | Fixed | Applications marked `certificate_issued` are terminal — no further transitions. | `WorkflowEngine::ALLOWED_TRANSITIONS`, `Application::TERMINAL_STATUSES` | full suite | ADR |
| BR-015 | Fixed | `ServiceDefinition::is_locked = true` blocks every write (edit, fee-update, AI-schema). | `RespondsWithLockedService`, `ServiceLockLookup` | `ServiceLockingTest` | ADR |
| BR-016 | Fixed | Boundary: Platform (`backend/app`) MAY NOT import from `Modules\Jea*`. | `tests/Architecture/BoundariesTest` allowlist reduced to 1 (composition root) | `BoundariesTest`, `SiblingModuleBoundariesTest` | ADR |
| BR-101 | Derivable | Session absolute lifetime — default 480 min. | `config/sanctum.php` — `SANCTUM_EXPIRATION_MINUTES` env | manual | config |
| BR-102 | Derivable | Session idle timeout — default 30 min. | `config/esp.php` — `SESSION_TIMEOUT_MINUTES` env | `SessionTimeoutTest` | config |
| BR-103 | Derivable | Password expiry — default 90 days. | `config/esp.php` — `PASSWORD_EXPIRY_DAYS` env | manual | config |
| BR-104 | Derivable | Login rate limit — 5/min per IP (raise for E2E). | `AppServiceProvider::registerRateLimiters` + `LOGIN_RATE_LIMIT_PER_MINUTE` env | manual | config |
| BR-105 | Derivable | AI schema generation rate limit — 10/hour per user. | `AppServiceProvider::registerRateLimiters` | manual | config |
| BR-106 | Derivable | Audit-log retention window — default 10 years. | `config/esp.php` — `AUDIT_LOG_RETENTION_YEARS` env | `AuditLogPruneTest` | config |
| BR-107 | Derivable | Notification retention: read = 180 days, unread = 365 days. | `config/esp.php` | `NotificationsPruneTest` | config |
| BR-108 | Derivable | Password rolling-history size — default 5. | `config/esp.php` — `PASSWORD_HISTORY_SIZE` env | `PasswordPolicyTest` | config |
| BR-109 | Derivable | Supervision-contract binding window — default 6 months. | `config/esp.php` — `SUPERVISION_WINDOW_MONTHS` env | `SupervisionExpiryTest` | config |
| BR-110 | Derivable | Nashmi replay-window seconds — default 300. | `config/nashmi.php` — `NASHMI_REPLAY_WINDOW_SECONDS` env | `NashmiSecurityTest` | config |
| BR-111 | Derivable | Nashmi nonce TTL — default 600. | `config/nashmi.php` — `NASHMI_NONCE_TTL_SECONDS` env | `NashmiSecurityTest` | config |
| BR-112 | Derivable | Slow-request SLO log threshold — default 500 ms. | `config/esp.php` — `SLOW_REQUEST_MS` env | manual | config |
| BR-113 | Derivable | Captcha default enabled (P0-E validator forces true in prod). | `config/esp.php` — `CAPTCHA_ENABLED` env | manual | config |

## Per-schema rules

Every JEA service ships its own JSON schema in
`service_definitions.schema` and carries its own fee formula, workflow
stages, field validation, document requirements, and certificate
metadata. Those are enforced by:

- `SchemaValidator` — per-field validation.
- `SchemaStructureValidator` — author-time schema linter.
- `WorkflowEngine` — reads `schema.workflow.stages` at runtime.
- `FeeCalculator` — reads `schema.fee`.

Changing a per-service rule = author-side schema PR + JORD ticket
(if reference material is affected). Not tracked here.

## Change protocol

- Fixed rule: propose an ADR under `docs/adr/`, get review, land the
  ADR + code together, update this register in the same commit.
- Derivable rule: change the config value + document in
  `deployment/env.production.template` if the change affects
  production defaults.
