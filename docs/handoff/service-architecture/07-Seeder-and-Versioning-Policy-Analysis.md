# Seeder-and-Versioning-Policy Analysis

**Handoff:** `ESP_V2_SERVICE_ARCHITECTURE_RUNTIME_MODEL`
**HEAD:** `f3fc366d8effed8f11fa2787fb6629a339ebfbfb`

---

## 1. Where service data lives at build time

Every service definition is emitted by a **seeder** at build time. There is no service catalog file on disk consumed at runtime — the seeded row on `service_definitions` is the runtime source of truth (see `06-Service-Definition-Source-of-Truth-Analysis.md`).

The build-time seeder tree (JeaServices module) discovered in the audit contains 18 seeders. The ones responsible for populating `service_definitions.schema` fall into three roles:

### 1.1 Catalog seeder (one row per service)

| Seeder | Responsibility |
|---|---|
| `ServicePlan2026Seeder` | Base row for all 57 services plus 7 JEA-* parent tiles. Fills each with `placeholderSchema` (empty fields / documents / workflow) and a placeholder 50,000 JOD fee. |

### 1.2 Section-specific overrides (write-into-`schema`-JSON)

| Seeder | Section overridden | Scope |
|---|---|---|
| `Srv001PilotSeeder` | `fields[]` + `documents[]` | SRV-001 only |
| `SurveyWorkflowsSeeder` | `workflow.stages[]` | 7 SRV codes with real drawio-derived workflows |
| `CatalogWorkflowsSeeder` | `workflow.stages[]` | 43 templates for the remaining catalog |
| `DrawingsDocumentsSeeder` | `documents[]` | DRW-P-001..010 (15-doc manifest) |
| `DrawingEngineerPickerSeeder` | `fields[]` | Adds engineer_picker field to DRW-P-* |
| `DrawingFeeMatrixSeeder` | `fee` | 12 DRW-P matrix fees |
| `SolarFeeSeeder` | `fee` | DRW-P-006 solar override |
| `ExcavationFeeSeeder` | `fee` | SRV-007 + SRV-012 |
| `SiteSurveyFeesSeeder` | `fee` | SRV-001..006 |

### 1.3 Ancillary reference data (external tables)

| Seeder | Table |
|---|---|
| `ManualReferencesSeeder` | `manual_references` |
| `ManualReferenceLinksSeeder` | `manual_reference_links` |

## 2. Application order

`DatabaseSeeder::run` invokes the seeders in the order needed for the overrides to see the base rows already present. The pattern is:

1. `ServicePlan2026Seeder` (base rows).
2. Section overrides — each is idempotent because it updates an existing row (`ServiceDefinition::where('code', ...)->first()->update([...])`).
3. Ancillary reference seeders.

Because overrides use `Model::update` rather than `updateOrCreate`, running an override before the base row exists would silently do nothing — the order is load-bearing.

## 3. Versioning — current state

**There is no service-definition versioning table.** The audit specifically checked for `CalculationSnapshot` and `RuleVersion` tables and confirmed neither exists.

The consequences (documented in `/tmp/svc-data.txt` RISK 1):

* In-progress applications see the **current** schema every time `ApplicationController::show` recomputes fees or `SchemaValidator::validateData` runs — not the schema at the moment the applicant started drafting.
* When an admin changes a workflow stage, in-flight applications may re-route to a different reviewer role.
* When an admin changes a fee row, in-flight drafts see a different fee amount than the applicant did last session.

The only current mitigation is the **`is_locked`** flag on `service_definitions`:

* `ServiceCatalogController::lock` / `unlock` allow admins to freeze a service. While locked, `ServiceCatalogController::update` and `store` refuse edits (implemented via the `RespondsWithLockedService` trait on the controller — see the DG-10 register).
* The typical admin flow (documented informally) is: copy → edit copy → swap active code. This is not enforced in code.

**Frozen fields inside the application record:**

| Field | Frozen at | Consequence |
|---|---|---|
| `fee_amount` | submit | Immutable — subsequent schema-fee edits do not re-price. Documented as BY DESIGN in `/tmp/svc-data.txt` RISK 3. |
| `reference_number` | create | Immutable |
| `sla_deadline` | submit | Immutable, computed from stage config at submit-time |
| `data` (JSON form fields) | user-editable in draft / modifications_requested | Recomputes derived values (Srv001Guard) on each submit |

## 4. Versioning — gaps and known limitations

| Gap | Impact | Current mitigation | Recommended addition |
|---|---|---|---|
| No `service_definition_versions` / `schema_snapshots` table | Applications reading `service_definitions.schema` see current schema, not submission-time schema | `is_locked` + convention (copy→edit→swap) | Add `schema_version` column; snapshot schema copy on submit; ApplicationController reads the snapshot |
| No `RuleVersion` for cross-cutting calculators | If `WellsCountCalculator` bands change, historical derived values in `applications.data` remain but reasoning is not reproducible | Provenance comments in each calculator's header (mention meeting-minute source) | Add rule-registry with `calculator_id + version` and stamp `applications.data.<derived_key>.__rule_version` |
| No `CalculationSnapshot` for Srv001Guard-derived values | `meeting_wells_count`, `meeting_net_depth_*` persisted but not tied to which version of the calculator produced them | None | Same rule-registry approach |
| Seeder-driven schemas are not diffed against DB state | An admin edit through `ServiceCatalogController::update` can drift from the seeded baseline | `SchemaStructureValidator` on admin edits (structural only, not content) | Add a "seeder-diff" report to CI + a `catalog_baseline_hash` column so drift is visible |
| No published contract for `schema` JSON | A new field type or fee type added to seeders can silently break FeeCalculator / SchemaValidator dispatch | `SchemaStructureValidator` enforces top-level structure | Publish a JSON Schema for the `schema` payload; validate on seed + on admin update |

## 5. Data-drift risks in seeders

* **`ServicePlan2026Seeder` uses a placeholder 50,000 JOD fee** for services without a real fee seeder (35 services). If this ever reaches production without being overridden, applicants would see a false fee. Recommendation: reject seed if any service ends with the placeholder fee still present (fail-loud in a non-dev environment).
* **`Srv001PilotSeeder` writes 28 fields** and 2 documents — none of the other 56 services has a comparable per-service fields seeder. This asymmetry is the root cause of "SRV-001 works end-to-end, other services are catalog-only."
* **Provisional calculators** (`WellsCountCalculator`, `NetDepthTable`) are seeded implicitly by their code presence — they run at submit time. If they turn out to be wrong per JEA later, changing them changes historical derived values on next re-submit but does not re-compute historical rows.

## 6. What "activation" means today

A service is considered `PRODUCTION_ACTIVE` in the inventory when **all** are true:

1. A row exists in `service_definitions` for the code.
2. The row has a non-null `schema.workflow.stages[]` (real or template).
3. The row has a non-null `schema.fee` (real or placeholder).
4. The row has `is_active = true`.

By this definition all 57 services are `PRODUCTION_ACTIVE`. That is a **schema-population** definition, not a **UAT-approved** definition. UAT approval status is not tracked in code.

## 7. Confidence

* Seeder tree completeness (**HIGH**) — enumerated via inventory agent (`/tmp/svc-inventory.txt`).
* Absence of versioning tables (**HIGH**) — data agent verified `CalculationSnapshot` and `RuleVersion` do not exist.
* Placeholder-fee count of 35 services (**HIGH**) — derived from the inventory rows that lack a real fee seeder.
