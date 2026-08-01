# Service Architecture — Risks and Decisions

**Handoff:** `ESP_V2_SERVICE_ARCHITECTURE_RUNTIME_MODEL`
**HEAD:** `f3fc366d8effed8f11fa2787fb6629a339ebfbfb`

This document catalogues architectural risks discovered by the runtime + data + frontend audits, plus the decisions the codebase currently makes (explicitly or implicitly) that shape those risks. **Nothing on this page proposes a change to code.** Change proposals belong in `09-Proposed-Service-Package-Contract.md`.

Each risk is classified as:

* **CURRENT_IMPLEMENTATION** — the code as it is at HEAD.
* **CURRENT_RUNTIME_BEHAVIOR** — the observable production behavior implied.
* **BUSINESS_APPROVAL_STATUS** — whether the current behavior is JEA-approved.
* **TARGET_REQUIREMENT** — the state the architecture should reach.
* **MIGRATION_REQUIREMENT** — the step needed to close the gap.

---

## R-01 · Schema versioning is not implemented

* **CURRENT_IMPLEMENTATION:** `service_definitions` has no `schema_version` column and no snapshot table. `SchemaValidator`, `FeeCalculator`, `WorkflowEngine`, and Apply.tsx all read `ServiceDefinition::find(id)->schema` at request-time.
* **CURRENT_RUNTIME_BEHAVIOR:** An admin edit to a service's `schema.workflow.stages[]`, `schema.fee`, or `schema.fields[]` immediately affects every draft application that has not yet reached a terminal state. Applicants can see a different fee amount, a different set of required fields, or a different reviewer role between two logins on the same draft.
* **BUSINESS_APPROVAL_STATUS:** Undocumented — the `is_locked` flag + copy-edit-swap admin convention is the current mitigation but is not a JEA-signed protocol.
* **TARGET_REQUIREMENT:** In-flight applications must resolve to the schema at the time of application creation (or, more strictly, at each state transition).
* **MIGRATION_REQUIREMENT:** Add `schema_version` + `service_definition_versions` table; snapshot schema on submit; teach `FeeCalculator` and `WorkflowEngine` to prefer the snapshot when present.

## R-02 · No rule-version stamping on derived values

* **CURRENT_IMPLEMENTATION:** `Srv001Guard::meetingDerivedValues()` writes derived keys (`meeting_wells_count`, `meeting_wells_band`, `meeting_net_depth_third_m`, `meeting_net_depth_two_thirds_m`, `meeting_net_depth_total_m`, `technical_review_required`, `exploration_requirement_status`) into `applications.data`. Neither the calculator version nor the input snapshot is captured.
* **CURRENT_RUNTIME_BEHAVIOR:** A future change to `WellsCountCalculator` bands or `NetDepthTable` values produces new results for future applications; historical rows keep stale values with no traceability back to which version of the calculator produced them.
* **BUSINESS_APPROVAL_STATUS:** Not approved — the underlying calculators are labeled PROVISIONAL in their headers (سرد محضر اجتماع 2026-07-26).
* **TARGET_REQUIREMENT:** Every derived value should be reproducible from its inputs and its rule-version.
* **MIGRATION_REQUIREMENT:** Introduce a small rule-registry (`calculator_id`, `version`, `source_ref`) and stamp derived values with `__rule_version` alongside the value; freeze the version at submit time.

## R-03 · SRV-001 is the only end-to-end wired service

* **CURRENT_IMPLEMENTATION:** Only SRV-001 has (a) real per-service fields via `Srv001PilotSeeder`, (b) a real service-specific guard (`Srv001Guard`), and (c) real calculators (`WellsCountCalculator`, `NetDepthTable`, `ExplorationRequirementMatrix`).
* **CURRENT_RUNTIME_BEHAVIOR:** The remaining 56 services accept a submission with any form data because they lack fields, and their business rules (if any exist in the source documents) are not enforced. Their workflows are template placeholders from `CatalogWorkflowsSeeder`.
* **BUSINESS_APPROVAL_STATUS:** Explicitly a pilot — SRV-001 was picked first because it maps to كتاب التعليمات الفنية 2025. UAT for other services is not tracked in code.
* **TARGET_REQUIREMENT:** Every service the JEA intends to open publicly must have (a) real fields, (b) a workflow derived from a source-of-truth flowchart, (c) documented guards where source documents demand them, (d) a real fee, and (e) tested submission.
* **MIGRATION_REQUIREMENT:** Per-service triage using the maturity matrix (`02-Service-Inventory-and-Maturity-Matrix.csv`); pilot the next service through the same pipeline as SRV-001.

## R-04 · Provisional calculators cite meeting minutes instead of JEA-approved documents

* **CURRENT_IMPLEMENTATION:** `WellsCountCalculator.php` header cites *محضر اجتماع 2026-07-26 §X*; `NetDepthTable.php` header cites *محضر اجتماع 2026-07-26 §XI* and acknowledges the invariant `third + two_thirds ≠ total` needs JEA clarification.
* **CURRENT_RUNTIME_BEHAVIOR:** Submissions to SRV-001 receive derived depths that are numerically stable but not JEA-signed. If JEA later publishes different values, historical applications carry the meeting-minutes derivation.
* **BUSINESS_APPROVAL_STATUS:** NOT_JEA_APPROVED (per file-header language).
* **TARGET_REQUIREMENT:** Both calculators need JEA-signed source references and a resolved invariant.
* **MIGRATION_REQUIREMENT:** Escalate to product owner; obtain signed technical reference; either correct the tables and stamp them with a new rule-version, or add a formal validation step for `third + two_thirds` when JEA confirms the intended relationship.

## R-05 · Fee immutability is designed but not documented

* **CURRENT_IMPLEMENTATION:** `applications.fee_amount` is set at `store`/`update` and frozen at `submit`. Later schema-fee edits do not re-price.
* **CURRENT_RUNTIME_BEHAVIOR:** Two applicants who submit the same service on different days may pay different amounts if the admin edited the fee in between — but neither will see the other's amount retroactively.
* **BUSINESS_APPROVAL_STATUS:** Marked BY_DESIGN in `/tmp/svc-data.txt` RISK 3.
* **TARGET_REQUIREMENT:** Publish this design as a documented invariant so a future admin does not attempt to "reconcile" old fees to a new rate.
* **MIGRATION_REQUIREMENT:** Add a note to `ServiceCatalogController::update` response (or to the admin UX) indicating that fee edits do not affect submitted applications.

## R-06 · Sanction → supervision_transfer synchronous trigger is not atomic

* **CURRENT_IMPLEMENTATION:** When a sanction is issued, `SupervisionTransferService` synchronously creates `supervision_transfers` rows for all affected approved-not-yet-issued applications. Race exists (`/tmp/svc-data.txt` RACE 4) if one of the target applications is being transitioned in parallel.
* **CURRENT_RUNTIME_BEHAVIOR:** The unique constraint on `supervision_transfers.application_id` prevents duplicates, but one branch of the race fails; the caller can retry.
* **BUSINESS_APPROVAL_STATUS:** Not explicitly signed as an acceptable behavior.
* **TARGET_REQUIREMENT:** Sanction issuance and transfer creation should be independently retryable and observable.
* **MIGRATION_REQUIREMENT:** Convert the synchronous trigger to a queued job dispatched after the sanction commit; add a monitoring counter.

## R-07 · Sanction `effective_until` NULL semantics rely on caller discipline

* **CURRENT_IMPLEMENTATION:** Permanent sanctions store `effective_until = NULL`. The gate must branch on `IS NULL` (permanent) OR `now() < effective_until`.
* **CURRENT_RUNTIME_BEHAVIOR:** ASSUMED CORRECT — requires code-review verification of `SanctionGuard::validate` at HEAD (documented in `/tmp/svc-data.txt` RISK 4). This handoff performs no code changes; verification is left to a follow-up.
* **BUSINESS_APPROVAL_STATUS:** N/A (correctness question).
* **TARGET_REQUIREMENT:** Deregistered offices must be blocked from submitting.
* **MIGRATION_REQUIREMENT:** Add a targeted test that inserts a permanent sanction (`effective_until = NULL`) and asserts `SanctionGuard::validate` returns an error.

## R-08 · Extending the engine requires code contributions (five extension points)

* **CURRENT_IMPLEMENTATION:** New service-specific guards, new pre-persist calculators, new stage actions, new fee formula families, and new notification kinds all require modifying PHP code — they cannot be expressed inside `schema` alone (see `06-…` §6).
* **CURRENT_RUNTIME_BEHAVIOR:** Any of the above needs a code deploy and a test suite pass; no admin-only path exists.
* **BUSINESS_APPROVAL_STATUS:** Implicit — the codebase is a modular monolith with strict PHPStan gates.
* **TARGET_REQUIREMENT:** Extension points should be explicit, discoverable contracts.
* **MIGRATION_REQUIREMENT:** Publish the "Service Package Contract" (see deliverable 09) that names each extension point and prescribes how a new service contributes to it.

## R-09 · Cross-JEA-module `app(FQCN)` hidden resolves

* **CURRENT_IMPLEMENTATION:** Documented in `docs/remediation/cleanup-sprint/duplicate-consolidation-backlog.md` as BL-DG-14 (four sites): `ApplicationController::show:126 → QuotaLedger`, `ApplicationController::submit:305 → CapacityGuard`, `ApplicationController::submit:325 → SanctionGuard`, `Application::booted::deleted:75 → QuotaLedger`.
* **CURRENT_RUNTIME_BEHAVIOR:** Works correctly but couples JeaServices to JeaProjects and JeaDiscipline behind runtime container resolution rather than compile-time imports.
* **BUSINESS_APPROVAL_STATUS:** Documented + allowlisted by `SiblingModuleBoundariesTest`. Backlogged with acceptance criteria.
* **TARGET_REQUIREMENT:** Extended `CrossCuttingSubmissionGuardRegistry` accepting contributions from sibling module providers; domain events for lifecycle hooks (e.g. `ApplicationDeleted`).
* **MIGRATION_REQUIREMENT:** Follow the acceptance criteria in BL-DG-14 (one PR per site).

## R-10 · Placeholder 50,000 JOD fee is silently active on 35 services

* **CURRENT_IMPLEMENTATION:** `ServicePlan2026Seeder` writes a placeholder fee of 50,000 JOD to services that no real fee seeder overrides. 35 services currently carry this placeholder.
* **CURRENT_RUNTIME_BEHAVIOR:** If a placeholder-fee service is opened by an applicant, `FeeCalculator` returns 50,000 JOD.
* **BUSINESS_APPROVAL_STATUS:** Not approved as a real fee — it is a build-time placeholder.
* **TARGET_REQUIREMENT:** Services with placeholder fees should be hidden from applicants or marked draft, or the placeholder value should refuse-to-charge.
* **MIGRATION_REQUIREMENT:** Add a seeder-time check that rejects deploy-to-prod if any active service carries the placeholder fee; or gate the placeholder behind an env-specific guard.

## R-11 · No UAT / business-approval tracking in code

* **CURRENT_IMPLEMENTATION:** `ACTIVATION_STATUS=PRODUCTION_ACTIVE` in the inventory is defined by "row exists + schema populated + `is_active=true`". It does not attest that JEA UAT has approved the service.
* **CURRENT_RUNTIME_BEHAVIOR:** A service can be catalog-active and applicant-visible without UAT sign-off.
* **BUSINESS_APPROVAL_STATUS:** Missing tracking — decision-log lives outside the repo (product docs).
* **TARGET_REQUIREMENT:** Distinguish `CATALOG_ACTIVE` (rows populated) from `UAT_APPROVED` (JEA-signed) at the service level.
* **MIGRATION_REQUIREMENT:** Add `uat_status` + `uat_signed_at` + `uat_reference` columns (or an external attestation table). Hide un-UAT services from the applicant catalog until signed.

## R-12 · Frontend hardcodes two field IDs by convention

* **CURRENT_IMPLEMENTATION:** `frontend/src/modules/JeaServices/pages/Apply.tsx` auto-fills `area_m2` and `governorate` from `project` context when a `project_id` is present.
* **CURRENT_RUNTIME_BEHAVIOR:** Any service that names its area or governorate field differently loses the auto-fill.
* **BUSINESS_APPROVAL_STATUS:** Convention-only.
* **TARGET_REQUIREMENT:** Field-auto-fill rules should be declared in `schema.fields[].auto_fill_from` rather than hardcoded in the renderer.
* **MIGRATION_REQUIREMENT:** Add an `auto_fill_from` key to the field schema; teach the renderer to consult it; migrate the two hardcoded IDs.

## R-13 · Multi-tenant scope depends on `withoutOrgScope` discipline

* **CURRENT_IMPLEMENTATION:** All mutable tables carry `organization_id` + `BelongsToOrganization` trait. `withoutOrgScope()` is called deliberately in three well-known places: `service_definitions` reads (shared catalog), `CadastralConflictGuard` (cross-org conflict detection), `PaymentCallbackController` (gateway is not org-scoped).
* **CURRENT_RUNTIME_BEHAVIOR:** Cross-tenant leakage is prevented by trait + global scope; new places that need `withoutOrgScope` require explicit code + review.
* **BUSINESS_APPROVAL_STATUS:** Approved architecture (H-01 fail-closed pattern).
* **TARGET_REQUIREMENT:** No cross-tenant reads without an explicit `withoutOrgScope`.
* **MIGRATION_REQUIREMENT:** None — enforcement already exists via `CrossTenantIsolationTest`.

## Cross-cutting decisions currently frozen

The following are decisions the code makes today that this handoff neither questions nor changes:

* **DG-02** payment confirm split (manual vs webhook) — kept separate for proof-of-payment guarantee.
* **DG-03** application-reference vs certificate-serial counters — kept separate (different keys, different serial formats).
* **DG-05** SchemaValidator vs Srv001Guard — kept separate (generic vs service-specific).
* **DG-06** JeaNotificationService vs Platform NotificationService — kept separate (H-07 boundary).
* **DG-11** CapacityGuard (read-side) vs QuotaLedger (write-side) — kept separate (release-on-soft-delete path needs the ledger independent of the gate).
* **DG-12** FeeCalculator (schema fees) vs QuotaLedger::overflowSurchargeFor (quota overflow) — kept separate (different data sources).

These are recorded in the Justified Duplication Register (`docs/remediation/cleanup-sprint/justified-duplication-register.md`) and reproduced here so a reader of this handoff does not attempt to unify them.
