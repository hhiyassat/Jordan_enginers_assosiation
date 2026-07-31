# ESP v2 — Security Controls

**Document ID:** ESP-SEC-001
**Version:** 1.0
**Status:** ACTIVE (authored 2026-07-31)
**Methodology:** Eqratech IEEE-Aligned Decision Assurance Methodology v1.1
**Cited by:** `BUILD_CONTRACT.md` §2 row 4, §8.1

---

## Scope

Enumerates every security control implemented in this codebase, grouped
into the 8 control groups the Build Contract references. Each control
names its **file(s)**, **test(s)**, and **status**. This document is
the authoritative inventory — any security-relevant change updates
this file in the same commit.

## Status vocabulary

```
ACTIVE                       control enforced in code + covered by tests
FIXED_PENDING_PRODUCTION_VALIDATION   control implemented; needs real prod verification
BLOCKED_EXTERNAL_INPUT      needs vendor/agency secret to activate; fail-closed until then
```

---

## Group 1 — Identity & Access

| # | Control | Files | Tests | Status |
|---|---|---|---|---|
| 1.1 | Sanctum bearer-token auth (via httpOnly cookie) | `backend/app/Http/Middleware/ReadTokenFromCookie.php`, `backend/config/sanctum.php` | full suite | ACTIVE |
| 1.2 | Absolute token lifetime = 480 min | `backend/config/sanctum.php` (M-22) | `ProductionSafetyTest::test_sanctum_expiration_null_is_a_violation` | ACTIVE |
| 1.3 | Idle-timeout token revocation | `backend/app/Http/Middleware/TokenInactivityCheck.php` | `HttpOnlyCookieAuthTest`, `SessionTimeoutTest` | ACTIVE |
| 1.4 | Role tiers (applicant, staff, auditor, admin, superuser) | `backend/app/Http/Middleware/CheckRole.php`, `backend/app/Models/User.php` role helpers | `UserManagementTest`, `SuperuserScopeTest` | ACTIVE |
| 1.5 | Superuser = user-management only (C-01) | routes + `User::isReviewer/canEditServices` | `SuperuserScopeTest` (36 data cases) | ACTIVE |
| 1.6 | `RequiresAdminTier` trait for JEA-business admin endpoints | `backend/app/Http/Concerns/RequiresAdminTier.php` | `SuperuserScopeTest`, `UserManagementTest` | ACTIVE |
| 1.7 | Password change enforced first-login + at expiry | `backend/app/Http/Middleware/EnforcePasswordPolicy.php` | `SuperuserFirstLoginTest`, `PasswordPolicyTest` | ACTIVE |

## Group 2 — Session Management

| # | Control | Files | Tests | Status |
|---|---|---|---|---|
| 2.1 | httpOnly + SameSite=Strict cookie carrying token | `backend/app/Http/Controllers/Api/AuthController::buildSessionCookie` | `HttpOnlyCookieAuthTest` | ACTIVE |
| 2.2 | Secure-cookie flag auto-on in production, overrideable | `AuthController::cookieSecureFlag` | manual | ACTIVE |
| 2.3 | Single-session policy on login (existing tokens revoked) | `AuthController::login` — `tokens()->delete()` | full suite | ACTIVE |
| 2.4 | Session-expired 401 with `session_expired` code | `TokenInactivityCheck.php` | `SessionTimeoutTest` | ACTIVE |

## Group 3 — Password Policy (P1-08)

| # | Control | Files | Tests | Status |
|---|---|---|---|---|
| 3.1 | Min 12 chars + mixedCase + numbers + symbols | `backend/app/Support/PasswordPolicy::baseRule` | `PasswordPolicyTest` | ACTIVE |
| 3.2 | Optional HIBP `->uncompromised()`; production-mandatory | `PasswordPolicy` + `ProductionSafety::checkPasswordCompromisedGate` | `ProductionSafetyTest` | FIXED_PENDING_PRODUCTION_VALIDATION |
| 3.3 | Rolling reuse-history (last 5 hashes) | `backend/app/Support/PasswordHistory.php` + migration `2026_07_31_000001_create_password_history_table` | `PasswordPolicyTest::test_change_password_rejects_reuse_of_current_password` | ACTIVE |
| 3.4 | Password rotated ⇒ history seeded | `AuthController` + `UserManagementController` + `Console\Commands\UserCredentials` | `PasswordPolicyTest` | ACTIVE |

## Group 4 — Authorization / Tenancy

| # | Control | Files | Tests | Status |
|---|---|---|---|---|
| 4.1 | Global org scope via `BelongsToOrganization` trait | `backend/app/Models/Concerns/OrganizationScope.php` | `BelongsToOrganizationTest` | ACTIVE |
| 4.2 | Null-org authenticated users fail closed (H-01) | `OrganizationScope::apply` (`whereRaw('1 = 0')`) | `BelongsToOrganizationTest::test_null_org_authenticated_user_sees_zero_rows_via_global_scope` | ACTIVE |
| 4.3 | Applicant-own filter on Application access | `ApplicationController::findAccessible` | `Srv001EndToEndFlowTest`, `CrossTenantIsolationTest` | ACTIVE |
| 4.4 | Cross-tenant negative tests (admin/staff surface) | `backend/tests/Feature/CrossTenantIsolationTest.php` | 11 assertions | ACTIVE |
| 4.5 | Notification model uses `BelongsToOrganization` (L-10) | `backend/app/Models/Notification.php` | `BelongsToOrganizationTest` | ACTIVE |

## Group 5 — Input Validation

| # | Control | Files | Tests | Status |
|---|---|---|---|---|
| 5.1 | Per-service schema-driven `SchemaValidator` | `backend/modules/JeaServices/Engine/SchemaValidator.php` | `SchemaValidatorTest` | ACTIVE |
| 5.2 | Upload magic-byte + extension gate | `backend/app/Rules/PdfOrDwgFile.php` | `PdfOrDwgFileTest` | ACTIVE |
| 5.3 | `SubmitOfficeRegistrationRequest` for public signup | `backend/modules/JeaServices/Http/Requests/SubmitOfficeRegistrationRequest.php` | `OfficeRegistrationSubmitTest` | ACTIVE |
| 5.4 | Cross-cutting submission pipeline (cadastral, owner-match) | `CrossCuttingSubmissionPipeline` + guards | `CadastralConflictGuardTest`, `OwnerMatchClearanceGuardTest`, real-concurrency test | ACTIVE |

## Group 6 — Transport & Headers

| # | Control | Files | Tests | Status |
|---|---|---|---|---|
| 6.1 | HSTS + preload | `backend/app/Http/Middleware/SecurityHeaders.php` | manual | ACTIVE |
| 6.2 | Restrictive CSP for `/api/*` | `SecurityHeaders.php` | manual | ACTIVE |
| 6.3 | X-Frame-Options DENY, X-Content-Type-Options nosniff | `SecurityHeaders.php` | manual | ACTIVE |
| 6.4 | Permissions-Policy (camera/mic/geo/payment/usb disabled) | `SecurityHeaders.php` | manual | ACTIVE |
| 6.5 | Referrer-Policy strict-origin-when-cross-origin | `SecurityHeaders.php` | manual | ACTIVE |
| 6.6 | `Cache-Control: no-store` on `/api/*` | `SecurityHeaders.php` | manual | ACTIVE |

## Group 7 — Integration Security

| # | Control | Files | Tests | Status |
|---|---|---|---|---|
| 7.1 | Nashmi HMAC-SHA256 over raw body | `backend/integrations/Nashmi/Http/Middleware/ValidateIntegrationKey.php` | `NashmiSecurityTest` | ACTIVE |
| 7.2 | Nashmi 5-min timestamp replay window | same | `NashmiSecurityTest` | ACTIVE |
| 7.3 | Nashmi nonce dedup (cache TTL) | same | `NashmiSecurityTest` | ACTIVE |
| 7.4 | Nashmi IP allowlist (fail-closed in prod) | same | `NashmiSecurityTest` | ACTIVE / BLOCKED_EXTERNAL_INPUT for IP list |
| 7.5 | GSB IP whitelist (fail-closed in prod, H-05) | `backend/integrations/Gsb/Http/Middleware/GsbIpWhitelist.php` | `GsbSecurityTest` | ACTIVE / BLOCKED_EXTERNAL_INPUT for IP list |
| 7.6 | GSB error-path log excludes raw body (H-06) | `backend/integrations/Gsb/Services/GsbClient.php` | `GsbSecurityTest::test_gsb_error_log_does_not_contain_raw_response_body` | ACTIVE |
| 7.7 | Production boot fails if `MockPaymentGateway` bound (C-02) | `backend/app/Support/ProductionSafety.php` | `ProductionSafetyTest` | ACTIVE — production driver = BLOCKED_EXTERNAL_INPUT |
| 7.8 | Production boot fails if `FakeJeaMembershipVerifier` bound (C-03) | same | same | ACTIVE — real JEA endpoint = BLOCKED_EXTERNAL_INPUT |
| 7.9 | `HttpJeaMembershipVerifier` — real driver skeleton | `backend/modules/JeaServices/Engine/HttpJeaMembershipVerifier.php` | `HttpJeaMembershipVerifierTest` (7 cases) | FIXED_PENDING_PRODUCTION_VALIDATION |

## Group 8 — Audit & Observability

| # | Control | Files | Tests | Status |
|---|---|---|---|---|
| 8.1 | `AuditLog` append-only trail (per-request writes inside DB::transaction) | `backend/app/Models/AuditLog.php`, WorkflowEngine | full suite | ACTIVE |
| 8.2 | Audit-log retention pruner (10y default) | `Console\Commands\AuditLogPrune`, `routes/console.php` | `AuditLogPruneTest` | ACTIVE |
| 8.3 | `security` log channel (365-day retention, P0-E-2) | `backend/config/logging.php` | `SecurityEventLoggingTest` | ACTIVE |
| 8.4 | `App\Support\SecurityEvents` typed emitters | `backend/app/Support/SecurityEvents.php` | `SecurityEventLoggingTest` | ACTIVE |
| 8.5 | AuthController emits login_success/failed/logout/password_changed | `AuthController` | `SecurityEventLoggingTest` | ACTIVE |
| 8.6 | CheckRole emits authorization_denied | `CheckRole` | `SecurityEventLoggingTest` | ACTIVE |
| 8.7 | Nashmi middleware emits integration_signature_failure (6 reasons) | `ValidateIntegrationKey` | `NashmiSecurityTest` (indirectly) | ACTIVE |
| 8.8 | Correlation-id middleware + LogApiAccess unified (L-11) | `backend/app/Http/Middleware/CorrelationId.php` + `LogApiAccess.php` | manual | ACTIVE |
| 8.9 | Readiness probe (`/api/ready`) — DB + cache checks (L-12) | `backend/app/Http/Controllers/Api/HealthController.php` | `HealthReadinessTest` | ACTIVE |
| 8.10 | ProductionSafety validator (12 boot-time invariants) | `backend/app/Support/ProductionSafety.php` | `ProductionSafetyTest` (18 cases) | ACTIVE |

---

## Cross-index — findings closure

Every finding from the 2026-07-30 architecture review that has a
security consequence appears here at least once:

- **C-01** → Group 1 row 1.5
- **C-02** → Group 7 row 7.7
- **C-03** → Group 7 rows 7.8 / 7.9
- **C-04** → Group 5 row 5.4 + real-concurrency test
- **C-05** → tracked in `docs/remediation/architecture-review-remediation-ledger.md`
- **H-01** → Group 4 row 4.2
- **H-02, H-03** → concurrency test + Group 5 tenant flows
- **H-04** → Group 7 rows 7.1–7.4
- **H-05, H-06** → Group 7 rows 7.5 / 7.6
- **H-10** → operations (queue jobs, deployment/supervisor/queue-worker.conf)
- **M-09** → Group 8 (notification retention) + Group 4 row 4.5
- **M-22** → Group 1 row 1.2
- **P0-E, P0-E-2** → Group 8 rows 8.3–8.10
- **P1-08** → Group 3 entire
- **P1-09** → `DocumentDownloadTest`
- **P1-10** → `CertificateAuthenticatedDownloadTest`

## Change protocol

Any commit that changes an authentication path, adds a role, changes
a middleware order, introduces an external integration, adjusts a
production-boot invariant, or touches an audit-log emission MUST
update this file in the SAME commit. `docs/remediation/architecture-review-remediation-ledger.md`
holds the operational rollups; this file holds the security-control
inventory.
