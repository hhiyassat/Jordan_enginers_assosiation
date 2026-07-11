# ESP v2 — Requirements Register

**System:** Eqratech Services Platform v2  
**Version:** 1.0 | **Date:** 2026-07-06  
**Methodology:** §6 Requirements Engineering — Eqratech IEEE-Aligned Decision Assurance Methodology v1.1

---

## Requirement Prefixes

| Prefix | Category |
|--------|----------|
| BR | Business Rule |
| FR | Functional Requirement |
| NFR | Non-Functional Requirement |
| SEC | Security Requirement |
| INT | Integration Requirement |
| DATA | Data Requirement |
| WF | Workflow Requirement |
| UI | User Interface Requirement |

---

## Business Rules

| ID | Statement | Priority | Source |
|----|-----------|----------|--------|
| BR-001 | One JSON schema file defines a complete e-service with no additional code | Must | Platform design |
| BR-002 | Schema fields define validation rules; SchemaValidator enforces them at submission | Must | EDA §6 |
| BR-003 | Fees are calculated from the schema fee config, not hardcoded in code | Must | Platform design |
| BR-004 | Every application belongs to exactly one organization; cross-org access is prohibited | Must | Multi-tenancy |
| BR-005 | Applications advance through stages defined in the schema workflow, not hardcoded | Must | Platform design |
| BR-006 | A certificate is issued only after all workflow stages are approved | Must | EDA B-6 |
| BR-007 | An application in modifications_requested status returns to submitted when resubmitted | Must | EDA B-5 |
| BR-008 | Rejected and certificate_issued applications are terminal — no further transitions | Must | EDA B-5 |

---

## Functional Requirements

| ID | Statement | Priority |
|----|-----------|----------|
| FR-001 | Applicants can browse the active service catalog | Must |
| FR-002 | Applicants can create a draft application for any active service | Must |
| FR-003 | Applicants can save form data to a draft application | Must |
| FR-004 | Applicants can upload documents against schema-defined document slots | Must |
| FR-005 | Applicants can submit a completed application for review | Must |
| FR-006 | Submission validates all schema fields and returns field-level errors on failure | Must |
| FR-007 | Applicants can view all their own applications and their current status | Must |
| FR-008 | Reviewers see a queue of applications at their assigned stage | Must |
| FR-009 | Reviewers can claim an application (lockForUpdate prevents concurrent claims) | Must |
| FR-010 | Reviewers can decide: approve / reject / request_modifications | Must |
| FR-011 | Authorized staff can confirm payment for approved applications | Must |
| FR-012 | Authorized staff can issue a certificate after payment | Must |
| FR-013 | Issued certificates are verifiable via a public endpoint | Must |
| FR-014 | Admins can view a dashboard with aggregate statistics | Must |
| FR-015 | Admins can manage users (create, update role, activate/deactivate) | Must |
| FR-016 | Admins can view a full audit log | Must |
| FR-017 | Admins can create new service definitions via the service catalog API | Should |

---

## Non-Functional Requirements

| ID | Statement | Priority |
|----|-----------|----------|
| NFR-001 | API response time ≤ 500ms for all read endpoints under normal load | Should |
| NFR-002 | Platform supports multiple organizations (tenants) from a single codebase | Must |
| NFR-003 | All UI text must be bilingual (Arabic RTL primary, English secondary) | Must |
| NFR-004 | UI must comply with WCAG 2.1 AA accessibility standard | Must |
| NFR-005 | System must support soft-delete for all user records | Must |
| NFR-006 | Audit log retention: 7 years minimum | Must |

---

## Security Requirements

| ID | Statement | Priority |
|----|-----------|----------|
| SEC-001 | All API responses must include HSTS, CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy headers | Must |
| SEC-002 | All API requests must be logged with structured JSON to a dedicated api_access channel | Must |
| SEC-003 | Tokens expire after SESSION_TIMEOUT_MINUTES of inactivity (default: 30) | Must |
| SEC-004 | Users must change password after admin reset; password expires after PASSWORD_EXPIRY_DAYS (default: 90) | Must |
| SEC-005 | RBAC is enforced at middleware layer; fail-closed (403 on missing role, never 200) | Must |
| SEC-006 | All input is validated through FormRequest classes; SchemaValidator enforces schema constraints | Must |
| SEC-007 | Sensitive fields (national_id, password, tokens) are redacted in audit logs and api_access logs | Must |
| SEC-008 | File uploads validated for MIME type and size against schema limits | Must |
| SEC-009 | Rate limiting applied: 5/min login, 60/min auth routes, 120/min general | Must |
| SEC-010 | CORS: no wildcard origin; configured via CORS_ALLOWED_ORIGINS env variable | Must |
| SEC-011 | Static analysis (PHPStan level 6) must pass; no critical findings | Must |
| SEC-012 | Password minimum 8 characters, complexity enforced (uppercase, lowercase, number) | Must |

---

## Workflow Requirements

| ID | Statement | Priority |
|----|-----------|----------|
| WF-001 | State machine defined by ALLOWED_TRANSITIONS constant; transitionTo() is the only mutation point | Must |
| WF-002 | Every state transition wrapped in DB::transaction() | Must |
| WF-003 | Every state transition writes AuditLog with rule_id, from_status, to_status, input_snapshot | Must |
| WF-004 | Reviewer claim uses lockForUpdate() to prevent concurrent claims | Must |
| WF-005 | Submission validates schema fields first (SchemaValidator); validation failure is EDA-10 Correctable Defect | Must |
| WF-006 | Submission validates required documents second; missing documents return structured errors | Must |
| WF-007 | Workflow stages and required roles are defined in the schema, not hardcoded | Must |
| WF-008 | SLA deadline is set on application when it enters a review stage | Should |

---

## Data Requirements

| ID | Statement | Priority |
|----|-----------|----------|
| DATA-001 | Application form data stored in a JSON `data` column; no new migration per service | Must |
| DATA-002 | Service definitions stored in `service_definitions` with `schema` JSON column | Must |
| DATA-003 | Audit log is append-only; no UPDATE or DELETE operations | Must |
| DATA-004 | All user records use soft deletes | Must |
| DATA-005 | Certificate QR token is SHA-256 HMAC-signed | Must |

---

*ESP v2 Requirements Register v1.0 | 2026-07-06 | Total: 43 requirements*
