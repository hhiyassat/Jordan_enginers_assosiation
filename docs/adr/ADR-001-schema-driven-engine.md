# ADR-001 — Schema-Driven Service Engine

**Status:** ACCEPTED (retroactively documented — decision predates this record)
**Date:** 2025-Q4 (original decision); 2026-07-31 (this write-up)
**Deciders:** ESP v2 platform architect
**Consulted:** JEA product owner
**Informed:** All ESP v2 contributors

---

## 1. Context

Every JEA e-service — building licence, drawing approval, discipline complaint, dues payment — has the same shape at the platform level:

- An applicant fills a form.
- The form has some fields, some documents, and a fee.
- The submission flows through a set of review stages.
- On approval, a certificate is issued.

The specifics — which fields, which stages, what the fee formula is, whether the certificate has a QR — vary per service. The original build proposal was one controller + one model + one route per service. That would have produced ~57 near-identical controllers by the time the ServicePlan2026 catalog was seeded.

## 2. Decision

Every JEA service is defined by a single JSON schema stored in `service_definitions.schema`. The schema drives:

- **Form rendering** (`DynamicForm.tsx` reads `schema.sections[].fields[]`).
- **Field validation** (`SchemaValidator` reads `schema.fields[].validation`).
- **Fee calculation** (`FeeCalculator` reads `schema.fee`).
- **Workflow routing** (`WorkflowEngine` reads `schema.workflow.stages`).
- **Certificate rendering** (`CertificatesController::renderPdf` reads `schema.certificate`).

Adding a new service = seeding a new `service_definitions` row + optionally registering a per-service submission guard in `ServiceSubmissionGuardRegistry`. No new controllers, routes, or models.

## 3. Alternatives considered

1. **Controller-per-service** — clearer per-service typing, but explodes to 57+ controllers and hides shared logic behind copy-paste. Rejected.
2. **Service-registry with typed classes per service** — a middle ground where each service is a PHP class implementing a common interface. Considered viable, but would still require code deployment for every new service. Rejected in favor of schema so JEA product owners can author services without a code release.
3. **External BPM engine (Camunda / Zeebe / etc.)** — full-fat workflow engines. Rejected: too much operational cost for a modest state machine, and moves the source of truth away from the codebase.

## 4. Consequences

### Positive

- Adding a new service is a schema PR, not a code PR.
- Common concerns (audit log, tenant scope, transaction envelope) are enforced once in `WorkflowEngine` and inherited by every service.
- Testing surface stays small — the schema is the input to a generic engine.

### Negative

- Schema is untyped at write time; validation is by `SchemaStructureValidator` at author-time and `SchemaValidator` at runtime. A malformed schema surfaces later than a compile error would.
- Cross-service refactors (e.g., changing the reference-number format) touch every schema.
- Per-service business logic that can't be expressed in the schema needs a `ServiceSubmissionGuard` implementation (`Srv001Guard` is the current example).

### Neutral

- The 30 files in `backend/modules/JeaServices/Engine/` are the reusable primitives that make the schema-driven approach possible.

## 5. Compliance

- **B-5 Critical Difference Test** — `ALLOWED_TRANSITIONS` in `WorkflowEngine` is the single enforcement point across every service.
- **B-9 Effect Recorded** — every transition writes `audit_logs` with `rule_id`, regardless of which service triggered it.
- **NFR-002 Multi-Tenancy** — org scope enforced by `BelongsToOrganization` on the shared Application model.
- **P-7 No Auto-Approvals** — every transition is an explicit HTTP action; the schema cannot force auto-transitions.

## 6. Verification

- `SchemaStructureValidator` test suite (`backend/tests/Unit/Engine/SchemaStructureValidatorTest.php` and related).
- `Srv001EndToEndFlowTest` — proves a real service flows end-to-end through the generic engine.
- `WorkflowEngine` unit tests — pin the state machine irrespective of service.
- Adding a new seeded service must not require any code change in `WorkflowEngine`, `FeeCalculator`, `SchemaValidator`, or any controller.

## 7. Follow-up

- Schema versioning (schemas can drift; there's no migration path yet).
- Author-time schema linter as a CLI command.
- Extract `CertificateIssuer` + `PaymentReconciler` from `WorkflowEngine` (queued as P2 in the remediation ledger).

## 8. Related

- `docs/architecture/00-baseline.md`, `01-refactoring-plan.md`, `04-modules.md`.
- `docs/architecture/cross-cutting-submission-pipeline.md` — describes how per-service guards compose with the shared engine.
- `docs/handoffs/2026-07-30_esp-v2-platform-and-services-handoff.md`.

---

## Attribution

- **Prepared by:** Platform architect (original decision); Claude Code (retroactive write-up 2026-07-31 to close the phantom-file reference in `BUILD_CONTRACT.md` §2 row 8).
- **Reviewed by:** (pending)
- **Ratified in:** Historical practice — the entire codebase is built on this decision.
