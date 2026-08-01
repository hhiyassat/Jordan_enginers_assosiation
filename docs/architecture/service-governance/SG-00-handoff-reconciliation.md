# SG-00 · Handoff Factual Reconciliation

**Program:** `ESP_V2_SERVICE_GOVERNANCE_VERSIONING_FOUNDATION`
**Phase:** SG-00
**Baseline HEAD:** `83d3a45960755c95c157d70932b6e81e010be9e2`
**Type:** documentation-only

This document corrects four material overstatements or imprecisions in the service-architecture handoff (`docs/handoff/service-architecture/`) before that handoff is used as the implementation baseline for phases SG-01..SG-06.

Every correction below is supported by a Judgment Record in `judgments/`.

---

## A. Source-of-truth model (was: single-source; now: distributed)

**Superseded phrasing (`docs/handoff/service-architecture/06-Service-Definition-Source-of-Truth-Analysis.md §1`):**

> "The runtime source of truth for a service is the `service_definitions.schema` JSON column."

**Corrected model (per `judgments/JDG-SG00-01-source-of-truth-model.md`):**

```text
SERVICE_CONFIGURATION_SOURCE = DATABASE_SCHEMA_ROW
BUILD_BASELINE_SOURCE        = SEEDERS
COMPLETE_JUDGMENT_SOURCE     = DISTRIBUTED
DRIFT_RISK                   = PRESENT
```

The complete runtime behaviour of a service is jointly produced by:

* `service_definitions.schema` (configuration data: fields, documents, fee, workflow, certificate template).
* Generic engine code version (`SchemaValidator`, `FeeCalculator`, `WorkflowEngine`, `StageActions`).
* Cross-cutting guards (`CadastralConflictGuard`, `OwnerMatchClearanceGuard`, `SanctionGuard`, `CapacityGuard`).
* Optional service-specific extensions (`Srv001Guard`, `WellsCountCalculator`, `NetDepthTable`, `ExplorationRequirementMatrix`).
* Sibling-module + external state (quota_consumptions, sanctions, payment_callbacks, external integrations).

Build-time baseline lives in seeders. Runtime current state lives in the database. Drift risk between the two is present today because no snapshot mechanism exists (SG-03 addresses this).

## B. SRV-001 terminology (was: fully wired end-to-end; now: pilot with partial approval)

**Superseded phrasing** (`docs/handoff/service-architecture/01-...md §1`, factual ending block `FULLY_WIRED_END_TO_END=1`):

> "SRV-001 مكتمل من طرف إلى طرف"

**Corrected classification set (per `judgments/JDG-SG00-02-srv001-classification.md`):**

```text
SRV001_RUNTIME_WIRING          = PILOT_COMPLETE
SRV001_DOMAIN_REQUIREMENTS     = PARTIAL
SRV001_BUSINESS_APPROVAL       = PARTIAL_OR_UNCONFIRMED
SRV001_TARGET_SRS_CONFORMANCE  = INCOMPLETE
```

**Rationale:** The pilot runtime wiring exists (fields, workflow, guard, calculators, tests). However:

* `WellsCountCalculator.php` and `NetDepthTable.php` are labeled PROVISIONAL in their own headers; the source cited is meeting minutes (محضر اجتماع 2026-07-26), not a JEA-signed technical reference.
* `NetDepthTable.php` acknowledges an unresolved arithmetic invariant (`third + two_thirds ≠ total`).
* No `uat_*` field exists on `service_definitions` — the repository carries no UAT sign-off attestation for SRV-001 as a whole.
* The target SRS `JEA-ESP2-SRS-SITE-SURVEY-001` (referenced in prior conversation) adds fields, attachments, borehole rules, depth rules, and building rules not present in the current pilot.

The permitted classifications supersede any phrasing that suggests SRV-001 is `FULLY_IMPLEMENTED`, `SRS_COMPLETE`, `BUSINESS_APPROVED`, or `PRODUCTION_READY`.

## C. Count reconciliation (was: DEFINED/ABSENT; now: approved/unapproved/template/placeholder/absent/unknown)

**Superseded columns** (`docs/handoff/service-architecture/02-Service-Inventory-and-Maturity-Matrix.csv` columns `FEES`, `WORKFLOW`, `DOCUMENTS` using DEFINED / ABSENT).

**Corrected model** (per `judgments/JDG-SG00-03-count-reconciliation.md`):

Fee statuses: `REAL_APPROVED`, `REAL_UNAPPROVED`, `PLACEHOLDER`, `ABSENT`, `UNKNOWN`.
Workflow statuses: `SOURCE_DERIVED_APPROVED`, `SOURCE_DERIVED_UNAPPROVED`, `TEMPLATE`, `PLACEHOLDER`, `ABSENT`, `UNKNOWN`.
Document statuses: `SERVICE_SPECIFIC_APPROVED`, `SERVICE_SPECIFIC_UNAPPROVED`, `FAMILY_INHERITED`, `PLACEHOLDER`, `ABSENT`, `UNKNOWN`.

**Restated totals:**

| Feature | Count | Status |
|---|---|---|
| Total services | 57 | (unchanged) |
| Services with `REAL_APPROVED` fee | **0** | (no repository evidence of signed JEA fee attestation) |
| Services with `REAL_UNAPPROVED` fee | **22** | (technically specified from manuals — JORD-63, JORD-64, JORD-78; not JEA-signed) |
| Services with `PLACEHOLDER` fee | **35** | (50,000 JOD default applied by `ServiceFeeDefaultsSeeder`) |
| Services with `SOURCE_DERIVED_APPROVED` workflow | **0** | (no signed flowchart in repo) |
| Services with `SOURCE_DERIVED_UNAPPROVED` workflow | **7** | (SRV-001, 002, 007, 008, 009, 012, 014 from `SurveyWorkflowsSeeder`) |
| Services with `TEMPLATE` workflow | **50** | (from `CatalogWorkflowsSeeder`) |
| Services with `SERVICE_SPECIFIC_APPROVED` documents | **0** | |
| Services with `SERVICE_SPECIFIC_UNAPPROVED` documents | **1** | (SRV-001 — 2 documents from `Srv001PilotSeeder`) |
| Services with `FAMILY_INHERITED` documents | **10** | (DRW-P-001..010 share the 15-doc manifest) |
| Services with `ABSENT` documents | **46** | |

**Placeholder-fee mechanism correction:** The prior handoff described the placeholder as directly seeded by `ServicePlan2026Seeder`. In reality:

1. `ServicePlan2026Seeder.php:381-408` writes `fee = ['type' => 'fixed', 'amount' => 0]` for every service.
2. Fee-specific seeders (`DrawingFeeMatrixSeeder`, `SolarFeeSeeder`, `ExcavationFeeSeeder`, `SiteSurveyFeesSeeder`) override 22 services with real (but unapproved) fees.
3. `ServiceFeeDefaultsSeeder.php:37,54-71` runs after and replaces every remaining `amount === 0` with `50000` JOD. Seeder order verified in `backend/database/seeders/DatabaseSeeder.php:59,121`.

The 35 / 22 / 50000 counts remain correct; the seeding *pathway* is the correction.

## D. Service Package Contract — persistence & mutation correction

**Superseded phrasing** (`docs/handoff/service-architecture/09-Proposed-Service-Package-Contract.md §2.8`):

> "MAY mutate `$app->data` (derived values), MUST persist via `$app->save()` before returning."

**Corrected contract** (per `judgments/JDG-SG00-04-service-package-contract-correction.md`):

```text
Policies (Submission / Calculation / Eligibility / IntegrationAdapter):
  - accept typed input (Application entity + form data + context)
  - return typed decision object containing:
      - error array (field-id keyed)
      - derived values (name → value)
      - warnings
      - rule-version references
      - snapshot payload for CalculationSnapshot (SG-04)
  - MUST NOT call $app->save
  - MUST NOT mutate the passed Application entity's persistent state
  - MUST NOT dispatch jobs / emit events / call HTTP transports

Use cases (Application submission orchestrators):
  - load the entity, invoke policies, persist, write snapshots, audit, transition workflow
  - own the transaction boundary
  - are the only writers to Application, application_documents, calculation_snapshots
```

The current `Srv001Guard` behaviour (which calls `$app->save` inside itself) is a legacy pattern preserved for pilot wiring. **This contract is aspirational**; SG-06 introduces a legacy adapter that preserves observable behaviour while migrating to the corrected contract.

## E. Not this program's scope

The following are **explicitly out of scope** for this program:

* Implementing the target `JEA-ESP2-SRS-SITE-SURVEY-001` SRS.
* Changing any SRV-001 numeric output (wells count, net depth, exploration matrix, fee amount).
* Changing any SRV-001 workflow behaviour.
* Reclassifying any current `_UNAPPROVED` status to `_APPROVED` without a signed JEA decision.
* Deleting or renaming existing seeders, guards, calculators, or workflows.

## Deliverables produced by SG-00

* `SG-00-handoff-reconciliation.md` (this file).
* `SG-00-corrected-maturity-model.csv`.
* `judgments/JDG-SG00-01-source-of-truth-model.md`.
* `judgments/JDG-SG00-02-srv001-classification.md`.
* `judgments/JDG-SG00-03-count-reconciliation.md`.
* `judgments/JDG-SG00-04-service-package-contract-correction.md`.

## Residuals

| RESIDUAL_ID | OWNER | RISK | BLOCKS | CLOSURE_EVIDENCE |
|---|---|---|---|---|
| RES-SG00-01 | SG-03 | HIGH — partial snapshots may create false reproducibility | SG-03 completion | SG-03 judgment record deciding snapshot scope |
| RES-SG00-02 | Product owner / JEA | HIGH — provisional calculators lack sign-off | Migration of SRV-001 from LEGACY_PILOT to canonical | Signed calculator source attached to service UAT |
| RES-SG00-03 | Product / JEA | MEDIUM — every `_UNAPPROVED` fee/workflow/document pending sign-off | Publication of the affected service (SG-01) | Signed decision on UAT record |
| RES-SG00-04 | SG-06 | LOW — legacy pattern still writes inside `Srv001Guard` | SG-06 completion | SG-06 characterization tests + refactor commit |

## Gates

| Gate | Result |
|---|---|
| `git diff --check` | PASS (docs-only) |
| PHPStan | NOT_APPLICABLE (no PHP changes) |
| Focused tests | NOT_APPLICABLE (no code changes) |
| Architecture tests | NOT_APPLICABLE |

## Verdict

Phase SG-00 verdict: **PASS** — all four required corrections issued as text + one corrected maturity CSV + four judgment records. No runtime code, migration, seeder, guard, workflow or test changes were made.
