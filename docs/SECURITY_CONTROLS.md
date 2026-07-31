# ESP v2 — Security Controls

**Document ID:** ESP-SEC-001
**Version:** 0.1 (STUB — pending authorship)
**Status:** PLACEHOLDER — cited by `BUILD_CONTRACT.md` §2 row 4

---

## Purpose

Cited in `BUILD_CONTRACT.md` as artifact #4, "Security Controls — 8 control groups per §8.1". Created as a stub to resolve the phantom-file reference the 2026-07-30 architecture review flagged. Authoring the full inventory is a follow-up task tracked as `P4-2` in the remediation ledger.

## Current authoritative sources

Every security control implemented today is:

- **Enforced in code**: `backend/app/Http/Middleware/*` (SecurityHeaders, TokenInactivityCheck, EnforcePasswordPolicy, CheckRole, ReadTokenFromCookie, CorrelationId, LogApiAccess); `backend/app/Support/*` (ProductionSafety, SecurityEvents, PasswordPolicy, PasswordHistory); `backend/integrations/Gsb/Http/Middleware/GsbIpWhitelist`; `backend/integrations/Nashmi/Http/Middleware/ValidateIntegrationKey`.
- **Audited via `security` log channel**: dedicated log at `storage/logs/security-YYYY-MM-DD.log`, 365-day retention. Events: `login_success`, `login_failed`, `logout`, `password_changed`, `token_revoked`, `authorization_denied`, `integration_signature_failure`, `payment_callback_failure`.
- **Tested**: `SuperuserScopeTest`, `SecurityEventLoggingTest`, `GsbSecurityTest`, `NashmiSecurityTest`, `HttpJeaMembershipVerifierTest`, `PasswordPolicyTest`, `CrossTenantIsolationTest`, `ProductionSafetyTest`.

## 8 control groups (to be authored)

1. **Identity & Access** — Sanctum tokens + cookie auth + role tiers + superuser scope (C-01).
2. **Session Management** — Sanctum absolute expiration (M-22), TokenInactivityCheck, cookie flags.
3. **Password Policy** — min 12 + symbols + HIBP toggle + rolling history (P1-08).
4. **Authorization** — CheckRole + `RequiresAdminTier` + BelongsToOrganization global scope + null-org fail-closed (H-01).
5. **Input Validation** — FormRequest classes, `PdfOrDwgFile` upload rule, SchemaValidator.
6. **Transport & Headers** — SecurityHeaders (HSTS, CSP, X-Frame-Options, Permissions-Policy).
7. **Integration Security** — Nashmi HMAC + timestamp + nonce + IP allowlist (H-04); GSB IP whitelist fail-closed (H-05); GSB error-path PII redaction (H-06); Nashmi + Payment production-boot guards (C-02, C-03).
8. **Audit & Observability** — AuditLog append-only + retention pruner; SecurityEvents channel; CorrelationId propagation; readiness health endpoint.

Each group should map to specific files + tests when this document is properly authored.
