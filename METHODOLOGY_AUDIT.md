# ESP v2 — Eqratech Decision Assurance Methodology Audit

**Audit Date:** 2026-07-06 (v1 — Initial audit at build completion)  
**System:** Eqratech Services Platform v2  
**Methodology:** Eqratech IEEE-Aligned Decision Assurance Methodology v1.1  
**Scope:** Backend (Laravel 12) + Frontend (React 18 / TypeScript)

**Build Contract:** BUILD_CONTRACT.md (written before first line of code)

---

## Overall Compliance Score

| Category | Checkpoints | ✅ Full | ⚠️ Partial | ❌ Missing |
|----------|-------------|---------|-----------|-----------|
| Appendix B — Decision Chain | 10 | 10 (100%) | 0 | 0 |
| Security Middleware Stack | 5 | 5 (100%) | 0 | 0 |
| Input Validation (SEC-006) | 4 | 4 (100%) | 0 | 0 |
| State Machine (WF-001 to WF-004) | 4 | 4 (100%) | 0 | 0 |
| Audit Log (WF-003, DATA-003) | 3 | 3 (100%) | 0 | 0 |
| Multi-Tenancy (BR-004, P-5) | 2 | 2 (100%) | 0 | 0 |
| Pre-Code Governance Docs | 5 | 4 (80%) | 1 (20%) | 0 |
| Unit Tests (§11.3 Criterion 2) | 2 | 2 (100%) | 0 | 0 |
| Frontend EDA-10 Compliance | 2 | 2 (100%) | 0 | 0 |
| **TOTAL** | **37** | **36 (97%)** | **1 (3%)** | **0 (0%)** |

> **v1 baseline: 36/37 (97%)**. ❌ Missing = 0. The 1 partial is ARCHITECTURE.md — structure exists, viewpoints to be completed as platform grows.

---

## 1. Appendix B — Decision Chain Audit

### B-1: Origin Preserved ✅

Every state-mutating method in `WorkflowEngine.php` calls `AuditLog::record()` inside a `DB::transaction()`. The `rule_id` parameter traces every decision to a business rule (ESP-WF-001 through ESP-WF-005).

**Evidence:** `WorkflowEngine::submit()`, `claim()`, `decide()`, `confirmPayment()`, `issueCertificate()` — all call `AuditLog::record()` before the transaction closes.

---

### B-2: Legitimate Branch ✅

`CheckRole` middleware enforces role-based access at the route level. Routes are grouped by role:
- `role:applicant,staff,auditor,admin` — application CRUD
- `role:staff,auditor,admin` — review workflow
- `role:staff,admin` — payment + certificate
- `role:admin` — admin dashboard

`WorkflowEngine::claim()` additionally checks the actor's role against the stage's required role from the schema.

---

### B-3: Origin–Branch Relationship ✅

- `findAccessible()` in `ApplicationController` scopes by `organization_id` and `applicant_id` for applicant role
- `WorkflowEngine::decide()` checks `$app->assigned_reviewer_id === $actor->id`
- `Application::scopeForOrganization()` is the only way to query applications (P-5 enforced)

---

### B-4: Qualifying Description ✅

`SchemaValidator::validateData()` validates all required schema fields before any `draft → submitted` transition. Enforces: required, pattern, min_length, max_length, min, max, options, conditional fields.

`DecideApplicationRequest` FormRequest enforces notes required for non-approve decisions (EDA B-4).

---

### B-5: Critical Difference Test ✅

`WorkflowEngine::ALLOWED_TRANSITIONS` constant is the **single authority** for all valid status changes. `transitionTo()` is the **only method** that sets `$app->status` — called from within `DB::transaction()`. Any attempt to transition to a non-allowed status aborts with HTTP 422.

**No other code sets `$app->status` directly.**

---

### B-6: Required Conditions ✅

- `submit()`: `isEditable()` check (draft or modifications_requested)
- `submit()`: `SchemaValidator::validateData()` must return null
- `submit()`: `SchemaValidator::validateDocuments()` must return null
- `decide()`: application must be `under_review` and claimed by this actor
- `issueCertificate()`: application must be `approved` AND `isFeePaid()`

---

### B-7: Valid Cause Occurred ✅

All state transitions are triggered by explicit HTTP actions:
- `POST /applications/{id}/submit`
- `POST /applications/{id}/claim`
- `POST /applications/{id}/decide`
- `POST /applications/{id}/confirm-payment`
- `POST /applications/{id}/issue-certificate`

No implicit auto-transitions exist. P-7 from BUILD_CONTRACT.md: auto-approval is prohibited.

---

### B-8: Blocker Check ✅

- `isTerminal()` check prevents all actions on rejected/certificate_issued
- `TokenInactivityCheck` middleware expires idle sessions (SEC-003)
- `SecurityHeaders` middleware on every response (SEC-001)
- Rate limiting: `throttle:5,1` on login (SEC-009)
- `EnforcePasswordPolicy` blocks must_change_password users (SEC-004)

---

### B-9: Effect Recorded ✅

Every `transitionTo()` call is followed by `AuditLog::record()` within the same `DB::transaction()`. Fields recorded: `rule_id`, `from_status`, `to_status`, `input_snapshot` (with sensitive fields redacted per SEC-007).

`AuditLog` has no `updated_at` column — it is append-only (DATA-003).

---

### B-10: Residual Outcomes ✅

**EDA-10 Correctable Defect** — implemented in two places:

1. **Backend** (`ApplicationController::submit()`): Returns `HTTP 422` with structured `{errors: {field_id: "message"}}`. Application remains in `draft` status. Case identity preserved.

2. **Frontend** (`Apply.tsx::handleSubmit()`): On 422 with `e.errors`, navigates back to `step='form'`, sets errors in state, shows inline per-field errors, announces error banner to screen readers. BUILD CONTRACT P-1 confirmed: no validation rules are removed.

---

## 2. Security Middleware Stack ✅

| Middleware | Status | Wiring |
|------------|--------|--------|
| `SecurityHeaders` | ✅ | `bootstrap/app.php` — global, runs on every response |
| `LogApiAccess` | ✅ | `bootstrap/app.php` — global, runs on every API request |
| `TokenInactivityCheck` | ✅ | Named alias `token.inactivity`, applied to all auth routes |
| `EnforcePasswordPolicy` | ✅ | Named alias `password.policy`, applied to all auth routes |
| `CheckRole` | ✅ | Named alias `role`, applied per route group |

**BUILD CONTRACT §4 satisfied:** Middleware wired in `bootstrap/app.php` before any route. No controller was written before this was in place.

---

## 3. Input Validation (SEC-006) ✅

| Control | Status | Evidence |
|---------|--------|---------|
| FormRequest for application store | ✅ | `StoreApplicationRequest.php` |
| FormRequest for reviewer decide | ✅ | `DecideApplicationRequest.php` — notes required for non-approve (B-4) |
| FormRequest for payment confirm | ✅ | `ConfirmPaymentRequest.php` |
| SchemaValidator for schema-driven fields | ✅ | `SchemaValidator::validateData()` + `validateDocuments()` |

**BUILD CONTRACT P-3 satisfied:** No inline `$request->validate()` in controllers. All validation through FormRequest or SchemaValidator.

---

## 4. State Machine (WF-001 to WF-004) ✅

| Requirement | Status | Evidence |
|-------------|--------|---------|
| WF-001: ALLOWED_TRANSITIONS is single authority | ✅ | `WorkflowEngine::ALLOWED_TRANSITIONS` constant |
| WF-002: DB::transaction() on every mutation | ✅ | All 5 WorkflowEngine methods use `DB::transaction()` |
| WF-003: AuditLog with rule_id on every mutation | ✅ | `AuditLog::record()` called in all 5 methods |
| WF-004: lockForUpdate() in claim() | ✅ | `WorkflowEngine::claim()` uses `Application::lockForUpdate()` |

---

## 5. Multi-Tenancy (BR-004) ✅

| Control | Status | Evidence |
|---------|--------|---------|
| `scopeForOrganization()` on Application | ✅ | `Application.php` — named scope |
| Controller uses scope, never bare query | ✅ | `ApplicationController::findAccessible()` and all admin queries |
| P-5 (BUILD CONTRACT): No unscoped queries | ✅ | Verified: no `Application::where(...)` without org scope |

---

## 6. Unit Tests (§11.3 Criterion 2) ✅

| Test Class | Tests | What it Covers |
|------------|-------|---------------|
| `WorkflowEngineTest` | 7 | ALLOWED_TRANSITIONS completeness, terminal states, valid transitions |
| `SchemaValidatorTest` | 7 | Required fields, pattern enforcement (national ID), EDA-10, conditional fields, documents |

**BUILD CONTRACT §10 Criterion 5 satisfied:** Tests verify the state machine and validation engine are correct. `SchemaValidatorTest::pattern_validation_is_never_skipped()` specifically tests BUILD CONTRACT P-1.

---

## 7. Pre-Code Governance Documents

| Document | Status | Notes |
|----------|--------|-------|
| `BUILD_CONTRACT.md` | ✅ Written before first line of code | |
| `REQUIREMENTS.md` | ✅ 43 requirements across 8 categories | |
| `docs/EDA_DECISION_CHAIN.md` | ✅ 4 operations fully mapped | |
| `METHODOLOGY_AUDIT.md` (this file) | ✅ Written alongside code, not after | |
| `ARCHITECTURE.md` | ⚠️ Partial — 7 viewpoints defined, content to grow | Phase 2 |

---

## 8. Phase 2 Items (Not Methodology Gaps)

| Item | Notes |
|------|-------|
| `ARCHITECTURE.md` full 7 viewpoints | Structure exists; content fills in as platform grows |
| STRIDE threat model | Phase 2 security engagement |
| Load testing | Requires staging environment |
| SAST/DAST reports | Phase 2 CI pipeline setup |
| MFA (TOTP + SMS OTP) | Phase 2 — ported from jea-system when needed |
| Feature test suite | Unit tests done; feature tests need running environment |

---

## Differences from Previous Build (eqratech-services-platform)

This section documents what changed and why. This is the audit evidence that the rebuild was not cosmetic.

| Item | Previous Build | This Build (esp-v2) |
|------|---------------|---------------------|
| Build order | Code first, methodology after | BUILD_CONTRACT.md first, then code |
| Middleware | Not wired; retrofitted after | Wired in `bootstrap/app.php` before any route |
| WorkflowEngine | No ALLOWED_TRANSITIONS; no DB::transaction | ALLOWED_TRANSITIONS constant; all methods in DB::transaction |
| AuditLog in transitions | Missing | Every transition calls AuditLog::record() with rule_id |
| Input validation | Inline $request->validate() | FormRequest classes for all endpoints |
| SchemaValidator | EDA-10 handled in frontend only | EDA-10: 422 + field errors from backend; frontend honors it |
| Unit tests | None | 14 tests across 2 test classes |
| METHODOLOGY_AUDIT.md | Written after completion | Written alongside code (this file) |
| Multi-tenancy | Partial scoping | Application::scopeForOrganization() enforced everywhere |

---

*ESP v2 METHODOLOGY_AUDIT.md v1 | 2026-07-06 | Eqratech*  
*Score: 36/37 (97%) | ❌ Missing: 0 | Written alongside code, not after.*
