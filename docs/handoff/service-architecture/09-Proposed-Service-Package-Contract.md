# Proposed Service Package Contract

**Handoff:** `ESP_V2_SERVICE_ARCHITECTURE_RUNTIME_MODEL`
**HEAD:** `f3fc366d8effed8f11fa2787fb6629a339ebfbfb`
**Nature of this document:** proposal only — describes the contract a future "one folder per service" architecture would satisfy. **No code changes were made as part of this handoff.**

---

## 1. What is a "service" for this contract?

For the purposes of this contract, a **service** is a distinct workflow that the JEA offers to applicants, identified by a `SERVICE_CODE` (e.g. `SRV-001`, `DRW-P-006`). It has:

* a public identity (code + Arabic/English name + parent category);
* a set of form fields;
* a set of documents;
* one fee formula;
* one workflow (ordered stages with roles, SLAs, notifications);
* zero or more service-specific business rules (guards + calculators);
* zero or more manual references and certificate templates;
* a lifecycle status (draft / active / retired).

The contract must be expressible **as one folder per service** so that adding a new service is a bounded scope of work with a clear checklist.

## 2. Contract sections

Each service folder would carry the following declarations. Sections marked `[schema]` live inside `service_definitions.schema` today (i.e. no code needed); sections marked `[code]` require a class contribution.

### 2.1 Identity `[schema]`

```
code: SRV-001
parent_code: JEA-SURV
names:
  ar: تقارير استطلاع الموقع للأبنية المقترحة
  en: Site Survey — Proposed Buildings
phase: 1
```

### 2.2 Fields `[schema]`

```
fields:
  - id: area_m2
    type: number
    label: {ar: المساحة, en: Area}
    required: true
    validation: {min: 0.01}
    auto_fill_from: project.area_m2   # closes R-12
  - id: floor_count
    type: integer
    label: {ar: عدد الطوابق, en: Floor count}
    required: true
    validation: {min: 1}
```

### 2.3 Documents `[schema]`

```
documents:
  - id: survey_contract
    type: CONTRACT
    required: true
    max_size_mb: 25
```

### 2.4 Fee `[schema]`

```
fee:
  type: per_unit   # one of fixed | tiered | formula | matrix | per_unit
  unit: lm
  rate: 0.150
  surcharges:
    - {kind: percent, value: 0.01, label: نقابة}
```

### 2.5 Workflow `[schema]`

```
workflow:
  stages:
    - id: applicant_submit
      role: applicant
      sla_hours: null
    - id: office_first_auditor
      role: reviewer
      external: true
      sla_hours: 72
      notifications: [reviewer_new_assignment]
    - id: office_head_review
      role: office_head
      sla_hours: 24
      can_request_modifications: true
    - id: chairman_sign
      role: chairman
      sla_hours: 48
```

### 2.6 Certificate template `[schema]`

```
certificate_template: templates/srv-001.blade.php   # optional
```

### 2.7 Reference links `[schema]`

```
manual_references: [JORD-77, JORD-91]
```

### 2.8 Submission guard `[code]` — extension point

* Contract: implement `ServiceSubmissionGuard` (the interface used by `Srv001Guard`).
* Registration: contribute to `ServiceSubmissionGuardRegistry` from the service's own provider (not from `JeaServicesServiceProvider`).
* Signature (illustrative — reflects existing `Srv001Guard`):
  ```
  public function validate(Application $app): array; // returns field-id-keyed errors
  ```
* MAY mutate `$app->data` (derived values), MUST persist via `$app->save()` before returning.
* MUST NOT touch the workflow (that's the engine's job).

### 2.9 Pre-persist calculators `[code]` — extension point

* Contract: pure functions/classes, no DB access. Consumed by the guard.
* Registration: constructor-injected from the guard (no container registration required).
* MUST expose a `RULE_VERSION` string; the guard MUST stamp derived values with it (closes R-02).

### 2.10 Stage actions `[code]` — extension point (rare)

* Contract: extend `StageActions::run($action, $app)` with a new action name.
* Registration: currently in-code; a future improvement is to move to a registry.

### 2.11 Fee formula `[code]` — extension point (rare)

* Contract: extend `FeeCalculator::calculateBreakdown` with a new `type` branch.
* Registration: currently in-code; a future improvement is to move to a strategy registry keyed by `type`.

### 2.12 Notification emitter `[code]` — extension point (rare)

* Contract: extend `JeaNotificationService` with a new `emit*` method.
* Registration: currently in-code.

## 3. What a "new service folder" would look like

A directory following the contract:

```
backend/modules/JeaServices/Services/Srv001/
  ├── seed.php                 # emits ServicePlan2026Seeder-equivalent row + all sections
  ├── Guard.php                # extends BaseSubmissionGuard, registers itself in Provider
  ├── Provider.php             # contributes Guard to ServiceSubmissionGuardRegistry
  ├── Calculators/
  │   ├── WellsCountCalculator.php
  │   ├── NetDepthTable.php
  │   └── ExplorationRequirementMatrix.php
  └── tests/
      ├── SchemaTest.php       # schema payload round-trip
      ├── GuardTest.php
      └── SubmitEndToEndTest.php
```

Existing files that would move into this folder for SRV-001 (illustrative):
* `Srv001Guard.php`
* `WellsCountCalculator.php`
* `NetDepthTable.php`
* `ExplorationRequirementMatrix.php`
* `Srv001PilotSeeder.php`
* `SurveyWorkflowsSeeder.php::soilProposedWorkflow()` (extract just this method)
* `SiteSurveyFeesSeeder.php` (SRV-001 slice)

## 4. Snapshots and versioning (closes R-01 + R-02)

Every service package would ship with:

1. A JSON Schema file describing the shape of its `schema` payload (so `SchemaStructureValidator` can validate).
2. A `manifest.json` naming the calculator versions this service depends on.
3. On submit, the engine snapshots the service's `schema` into `service_definition_versions` and stamps the application with the version reference. Historical fee recomputation and workflow re-derivation always use the snapshot.

## 5. Cross-cutting responsibilities NOT owned by any service package

The following remain owned by the generic engine and are NOT part of any service folder:

* Cross-cutting submission pipeline (`CadastralConflictGuard`, `OwnerMatchClearanceGuard`, `SanctionGuard`, `CapacityGuard`).
* Reference number / certificate number allocation (`application_counters`, `certificate_counters`).
* Payment abstraction (`PaymentGateway` + `PaymentCallbackController`).
* Notification dispatch primitives (`NotificationService`).
* Audit log observer.
* Multi-tenant scope (`BelongsToOrganization` + `OrganizationScope`).
* Frontend renderer (`Apply.tsx`), reviewer console, catalog listing.

## 6. Migration order (proposed, not executed)

If the codebase later adopts this contract, a minimally disruptive path would be:

1. Add `schema_version` + snapshot table (closes R-01).
2. Add rule-registry (closes R-02).
3. Publish `SchemaStructureValidator` extension for the JSON Schema shape.
4. Extract SRV-001 into `backend/modules/JeaServices/Services/Srv001/` and prove the contract on the pilot.
5. Onboard the next service through the same pipeline; do not batch.

Each of steps 1-5 is a separate PR with its own tests.

## 7. Non-goals

* This contract does NOT propose changing controllers, WorkflowEngine, FeeCalculator, or SchemaValidator behaviour — only the way service packages contribute to them.
* This contract does NOT reduce the multi-tenant enforcement surface.
* This contract does NOT split JeaServices into per-service Laravel modules; services remain folders inside JeaServices.
