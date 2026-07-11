# ESP v2 — Build Contract

**Document ID:** ESP-BC-001  
**Version:** 1.0  
**Date:** 2026-07-06  
**Methodology:** Eqratech IEEE-Aligned Decision Assurance Methodology v1.1  
**Status:** ACTIVE — all code must satisfy every clause before merge

This contract is written **before line one of code**. Every implementation decision is checked against it. Retrofitting compliance is explicitly prohibited.

---

## 1. What This Platform Is

A generic, schema-driven e-government services platform. One JSON schema file → one fully running e-service with no additional code beyond the schema. The Business License service (رخصة تجارية) is the first proof of concept.

**Stack:** Laravel 12 · PHP 8.2+ · MySQL 8 · React 18 · TypeScript · Tailwind CSS v3  
**Auth:** Laravel Sanctum bearer tokens  
**Directory:** `/tenders/esp-v2/`

---

## 2. Pre-Code Governance Artifacts (Must Exist Before Backend Scaffold)

These documents are created in this order, before `composer create-project`:

| # | Artifact | File | Purpose |
|---|----------|------|---------|
| 1 | Build Contract | `BUILD_CONTRACT.md` (this file) | Compliance authority |
| 2 | Requirements Register | `REQUIREMENTS.md` | §6 — all BR/FR/NFR/SEC |
| 3 | Architecture Document | `ARCHITECTURE.md` | §7.1 — 7 viewpoints |
| 4 | Security Controls | `docs/SECURITY_CONTROLS.md` | §8.1 — 8 control groups |
| 5 | EDA Decision Chain | `docs/EDA_DECISION_CHAIN.md` | §13.2 — per-operation EDA mapping |
| 6 | Business Rules Register | `docs/BUSINESS_RULES_REGISTER.md` | §4.3 — Fixed vs Derivable |
| 7 | ADR Template | `docs/adr/ADR-TEMPLATE.md` | §12.2 — all 12 fields |
| 8 | ADR-001 | `docs/adr/ADR-001-schema-driven-engine.md` | First architectural decision |
| 9 | Methodology Audit | `METHODOLOGY_AUDIT.md` | Compliance tracker — updated alongside code |

---

## 3. EDA Decision Chain — Mandatory for Every State Mutation

**Reference:** Appendix B, Eqratech Decision Assurance Methodology v1.1

Every workflow state transition in `WorkflowEngine.php` must satisfy all 10 EDA elements. The engine is built around this model, not bolted onto it.

| Element | Code Enforcement |
|---------|-----------------|
| B-1 Origin | `AuditLog::record()` called inside DB transaction; `rule_id` passed on every call |
| B-2 Legitimate Branch | `CheckRole` middleware + scoped Eloquent queries |
| B-3 Origin–Branch Relationship | `lockForUpdate()` on claim; compound ownership checks |
| B-4 Qualifying Description | `FormRequest` validation; required fields gated before any transition |
| B-5 Critical Difference Test | `ALLOWED_TRANSITIONS` constant is the single enforcement point; `transitionTo()` aborts 422 on illegal transitions |
| B-6 Required Conditions | Explicit precondition checks before transition (documents uploaded, fee paid, etc.) |
| B-7 Valid Cause Occurred | All state mutations are explicit HTTP actions; no implicit auto-transitions |
| B-8 Blocker Check | Terminal state guards; rate limiting; session timeout; security header enforcement |
| B-9 Effect Recorded | Every transition writes `audit_logs` with `from_status`, `to_status`, `rule_id`, `input_snapshot` |
| B-10 Residual Outcomes | Correctable Defect (EDA-10) returns 422 with field errors — never silently discarded |
| B-11 Decision Traceable | RTM.md traces requirement → code → test |

### EDA-10 Correctable Defect Rule (Hard Requirement)
When validation fails after submission:
- Return HTTP 422 with structured `{ errors: { field: "message" } }`
- Preserve the application in `draft` state — do NOT destroy it
- Frontend returns user to the form step with errors shown inline
- **PROHIBITED:** Removing validation to make the flow work (violation recorded in previous build)

---

## 4. Security Middleware Stack (Must Be Wired Before First Route)

The following middleware must be registered in `bootstrap/app.php` **before any route is defined**. No controller is written until the middleware stack is in place.

```
Request → SecurityHeaders → LogApiAccess → auth:sanctum → TokenInactivityCheck
       → EnforcePasswordPolicy → CheckRole → Controller
```

| Middleware | File | Requirement |
|------------|------|-------------|
| `SecurityHeaders` | `app/Http/Middleware/SecurityHeaders.php` | HSTS, CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy; §8.1 Transport |
| `LogApiAccess` | `app/Http/Middleware/LogApiAccess.php` | Structured JSON to `api_access` log channel; X-Request-ID correlation; mask password/token fields; §10.2 |
| `TokenInactivityCheck` | `app/Http/Middleware/TokenInactivityCheck.php` | Expire tokens after `SESSION_TIMEOUT_MINUTES` idle; §8.1 Identity |
| `EnforcePasswordPolicy` | `app/Http/Middleware/EnforcePasswordPolicy.php` | Block on `must_change_password` or expired password; §8.1 Identity |
| `CheckRole` | `app/Http/Middleware/CheckRole.php` | RBAC enforcement; fail-closed; §8.1 Authorization |

---

## 5. State Machine Contract

### Application Statuses
```
draft → submitted → under_review → approved → certificate_issued
                                → modifications_requested → draft
                                → rejected
```

### ALLOWED_TRANSITIONS (Authoritative)
```php
const ALLOWED_TRANSITIONS = [
    'draft'                    => ['submitted'],
    'submitted'                => ['under_review'],
    'under_review'             => ['approved', 'rejected', 'modifications_requested'],
    'modifications_requested'  => ['submitted'],
    'approved'                 => ['certificate_issued'],
    'rejected'                 => [],
    'certificate_issued'       => [],
];
```

### WorkflowEngine EDA Structure
Every public method in `WorkflowEngine.php` must:
1. Verify the actor's role against the stage's required role (`B-2`)
2. Assert the transition is in `ALLOWED_TRANSITIONS` (`B-5`)
3. Check all required preconditions (`B-6`)
4. Wrap the state change in `DB::transaction()` (`B-9`)
5. Call `AuditLog::record()` with `rule_id` and `input_snapshot` inside the transaction (`B-1, B-9`)
6. Return the updated application

---

## 6. Input Validation Contract

- ALL input is validated through `FormRequest` classes — never inline `$request->validate()` in a controller
- Validation rules are derived from the JSON schema's field constraints (`required`, `pattern`, `min_length`, `max_length`, `min`, `max`, `options`)
- `SchemaValidator.php` in the Engine layer translates schema constraints to Laravel validation rules
- Validation failures are **EDA-10 Correctable Defects**: return 422 with field-level errors, never silently stripped

---

## 7. Audit Log Contract

Every state mutation must call:
```php
AuditLog::record(
    user:      $actor,
    subject:   $application,
    action:    'application.status_changed',
    extra: [
        'rule_id'        => 'ESP-WF-001',  // BRR reference
        'from_status'    => $previous,
        'to_status'      => $new,
        'input_snapshot' => [...],          // non-sensitive fields only
    ]
);
```

- `audit_logs` is append-only — no UPDATE, no DELETE
- `input_snapshot` must have sensitive fields (`national_id`, etc.) replaced with `[REDACTED]`
- Override entries must set `is_manual_override = true` with `override_reason`

---

## 8. Multi-Tenancy Contract

- Every query must be scoped by `organization_id`
- No cross-organization data leakage is possible — enforced at the Eloquent query level, not in the controller
- `Application::forOrganization($id)` scope is the only way to query applications

---

## 9. Schema Engine Contract

- One JSON schema file → one running service, no code changes
- The schema is the **source of truth** for: fields, validation rules, workflow stages, fees, documents, certificate fields
- `WorkflowEngine`, `SchemaValidator`, `FeeCalculator` all read from the schema — they contain no service-specific logic
- If adding a new service requires touching PHP or TypeScript code (other than adding the schema file), that is a contract violation

---

## 10. Definition of Done (Per Increment)

An increment is Done only when ALL hold:
1. ✅ Implements a traced requirement (RTM.md updated)
2. ✅ All EDA elements documented in the method's docblock
3. ✅ FormRequest class used for all input; SchemaValidator for schema-driven validation
4. ✅ AuditLog::record() called in every state mutation
5. ✅ Unit test written and passing
6. ✅ PHPStan level 6 passes; Laravel Pint passes
7. ✅ METHODOLOGY_AUDIT.md updated to reflect the new component's compliance status
8. ✅ No validation removed to make a flow work — ever

---

## 11. Prohibited Actions

The following are explicitly prohibited and constitute a contract violation:

| # | Prohibition | Reason |
|---|-------------|--------|
| P-1 | Removing a validation rule to unblock a flow | Violates EDA-04, EDA-10, SDLC security policy |
| P-2 | Writing a controller before its security middleware is wired | Violates §8.1 Secure by Default |
| P-3 | Using `$request->validate()` inline instead of FormRequest | Violates §6 requirements traceability |
| P-4 | State transition without `DB::transaction()` + `AuditLog::record()` | Violates §9.2 decision controls |
| P-5 | Querying applications without `organization_id` scope | Violates multi-tenancy contract |
| P-6 | Writing METHODOLOGY_AUDIT.md after code is complete | Violates the principle that compliance is structural, not cosmetic |
| P-7 | Auto-approving or auto-transitioning without human action | Violates §4.3-D non-decidable branch routing |

---

*ESP v2 — Build Contract v1.0 | Eqratech | 2026-07-06*  
*This document is the compliance authority for all code in this repository.*
