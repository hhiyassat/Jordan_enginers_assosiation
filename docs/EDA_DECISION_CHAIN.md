# ESP v2 — EDA Decision Chain

**Methodology Reference:** §13.2 Application to Platform  
**Date:** 2026-07-06

This document maps every workflow operation to the 10-element EDA decision chain from Appendix B of the Eqratech IEEE-Aligned Decision Assurance Methodology v1.1.

---

## Primary Operation: Application Submission

**Operation:** `WorkflowEngine::submit(Application $app, User $actor)`  
**Transition:** `draft → submitted`  
**Business Rule:** ESP-WF-001

| EDA Element | What Satisfies It | Code Location |
|-------------|-------------------|---------------|
| B-1 Origin | The applicant who owns the draft application | `findAccessible()` scopes by `applicant_id` |
| B-2 Legitimate Branch | `applicant` role required | `CheckRole::handle()` middleware |
| B-3 Origin–Branch Relationship | Application must belong to the acting applicant | `findAccessible()` compound query |
| B-4 Qualifying Description | All required schema fields must be present and valid | `SchemaValidator::validateData()` |
| B-5 Critical Difference Test | `ALLOWED_TRANSITIONS['draft']` must contain `submitted` | `transitionTo()` enforces constant |
| B-6 Required Conditions | All required documents must be uploaded | `SchemaValidator::validateDocuments()` |
| B-7 Valid Cause Occurred | Explicit HTTP POST to `/applications/{id}/submit` | `ApplicationController::submit()` |
| B-8 Blocker Check | Application must be in `draft` or `modifications_requested` status | `isEditable()` check before processing |
| B-9 Effect Recorded | AuditLog written inside DB transaction | `AuditLog::record(..., 'rule_id' => 'ESP-WF-001')` |
| B-10 Residual Outcomes | Validation failure → 422 with field errors (EDA-10 Correctable Defect); success → application moves to submitted | `SchemaValidator` returns errors; frontend returns to form step |

---

## Operation: Reviewer Claim

**Operation:** `WorkflowEngine::claim(Application $app, User $actor)`  
**Transition:** `submitted → under_review` (partial — assigns reviewer)  
**Business Rule:** ESP-WF-002

| EDA Element | What Satisfies It |
|-------------|-------------------|
| B-1 Origin | The reviewer claiming the application |
| B-2 Legitimate Branch | `staff` or `auditor` role required per schema stage |
| B-3 Origin–Branch Relationship | Application must be at the stage matching the actor's role |
| B-4 Qualifying Description | Application must have all required data (already validated at submit) |
| B-5 Critical Difference Test | Application must be in `submitted` status |
| B-6 Required Conditions | Application must not already be claimed by another reviewer |
| B-7 Valid Cause Occurred | Explicit HTTP POST to `/applications/{id}/claim` |
| B-8 Blocker Check | `lockForUpdate()` prevents race condition; terminal state guard |
| B-9 Effect Recorded | AuditLog written with `rule_id` = `ESP-WF-002` |
| B-10 Residual Outcomes | Claim conflict → 409; success → reviewer assigned, status = `under_review` |

---

## Operation: Reviewer Decision

**Operation:** `WorkflowEngine::decide(Application $app, User $actor, string $decision, ...)`  
**Transitions:** `under_review → approved | rejected | modifications_requested`  
**Business Rule:** ESP-WF-003

| EDA Element | What Satisfies It |
|-------------|-------------------|
| B-1 Origin | The reviewer who claimed the application |
| B-2 Legitimate Branch | Role must match the current stage's required role |
| B-3 Origin–Branch Relationship | Actor must be the assigned reviewer for this application |
| B-4 Qualifying Description | Decision notes required for non-approve decisions |
| B-5 Critical Difference Test | `ALLOWED_TRANSITIONS['under_review']` checked |
| B-6 Required Conditions | Application must be in `under_review` and claimed by this actor |
| B-7 Valid Cause Occurred | Explicit HTTP POST to `/applications/{id}/decide` |
| B-8 Blocker Check | Terminal state guard; role check |
| B-9 Effect Recorded | AuditLog with `rule_id`, `decision`, notes |
| B-10 Residual Outcomes | approved → fee notification; rejected → terminal; modifications_requested → returns to applicant |

---

## Operation: Certificate Issuance

**Operation:** `WorkflowEngine::issueCertificate(Application $app, User $actor)`  
**Transition:** `approved → certificate_issued`  
**Business Rule:** ESP-WF-004

| EDA Element | What Satisfies It |
|-------------|-------------------|
| B-1 Origin | Admin or authorized staff member |
| B-2 Legitimate Branch | `admin` or `staff` role required |
| B-3 Origin–Branch Relationship | Application belongs to actor's organization |
| B-4 Qualifying Description | Application must be in `approved` status |
| B-5 Critical Difference Test | `ALLOWED_TRANSITIONS['approved']` contains `certificate_issued` |
| B-6 Required Conditions | Payment must be confirmed (fee_paid = true) |
| B-7 Valid Cause Occurred | Explicit HTTP POST to `/applications/{id}/issue-certificate` |
| B-8 Blocker Check | Payment status check; terminal state guard |
| B-9 Effect Recorded | Certificate record created; AuditLog with `rule_id` = `ESP-WF-004` |
| B-10 Residual Outcomes | Certificate issued with QR token; status = `certificate_issued` (terminal) |

---

## EDA-10 Correctable Defect — Enforcement Pattern

EDA-10 states: a correctable defect must be returned to the origin for correction while preserving the case identity and audit trail.

**In ESP v2:**
- When `SchemaValidator::validateData()` fails: HTTP 422 with `{ errors: { field_id: "message" } }`
- Application status remains `draft` — not destroyed, not rejected
- Frontend navigates user back to the form step and shows errors inline
- On resubmit, the same application ID is used — case identity preserved
- Every validation attempt (pass or fail) is logged in `audit_logs`

**What is PROHIBITED (P-1 from BUILD_CONTRACT.md):**
Removing a validation rule from the schema or SchemaValidator to bypass this flow.

---

*ESP v2 EDA Decision Chain v1.0 | 2026-07-06*
