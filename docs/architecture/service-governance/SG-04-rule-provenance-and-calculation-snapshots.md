# SG-04 · Rule Provenance and Calculation Snapshots

**Program:** `ESP_V2_SERVICE_GOVERNANCE_VERSIONING_FOUNDATION`
**Phase:** SG-04
**Baseline HEAD:** `2210893...` (post SG-03)

Introduces `rule_definitions`, `rule_versions`, and `calculation_snapshots` tables. Registers the three SRV-001 calculators as versioned rules with explicit business approval status. Provides `CalculationSnapshotWriter` that enforces the DRAFT-overwrite / SUBMIT-immutable / MANUAL_RECALC-supersedes policy.

**Does NOT change SRV-001 numeric behaviour.** The wrapping is additive — SG-06 will actually wire snapshot writes into `Srv001Guard`.

## Data model

Migration `2026_08_01_000030_create_rule_provenance_tables.php`:

* `rule_definitions` — one row per calculator: `rule_identifier` (unique), `display_name`, `description`.
* `rule_versions` — versioned implementation: `implementation_identity` (FQCN), `source_reference`, `business_approval_status` enum(APPROVED, PROVISIONAL, REJECTED, PENDING), `effective_from/to`.
* `calculation_snapshots` — per-execution record: `application_id`, `rule_version_id`, `purpose` enum(DRAFT, SUBMIT, MANUAL_RECALC), `inputs` JSON, `outputs` JSON, `intermediate_values` JSON, `warnings` JSON, `open_decisions` JSON, `superseded_snapshot_id` FK to self.

Unique constraint `(application_id, rule_version_id, purpose)` — one DRAFT and one SUBMIT per rule per application. MANUAL_RECALC is uniquely constrained per triple as well (so two manual recalcs of the same rule on the same application would violate the DB — a conscious limitation; multiple recalcs would need a purpose_qualifier column, deferred).

## Judgment decisions

* **JDG-SG04-01** (granularity): per-calculator. Three rule definitions for SRV-001.
* **JDG-SG04-02** (recalculation policy): DRAFT overwrites; SUBMIT insert-only immutable; MANUAL_RECALC insert-only with `superseded_snapshot_id` link.

## SRV-001 rule classifications

Seeded by `Srv001RulesSeeder`:

| rule_identifier | Implementation | Business approval status | Source reference |
|---|---|---|---|
| `SRV001_EXPLORATION_MATRIX` | `ExplorationRequirementMatrix` | **APPROVED** | كتاب التعليمات الفنية 2025 ص 230-231 (JEA-signed) |
| `SRV001_WELLS_COUNT` | `WellsCountCalculator` | **PROVISIONAL** | محضر اجتماع 2026-07-26 §X (unsigned) |
| `SRV001_NET_DEPTH` | `NetDepthTable` | **PROVISIONAL** | محضر اجتماع 2026-07-26 §XI (unresolved invariant) |

The classifications mirror the file-header disclaimers in the calculator classes themselves — this seeder makes those provenance claims queryable rather than only readable in comments.

## Immutability enforcement

`CalculationSnapshot` uses a `saving` observer that:

* Allows all fields to be modified on rows with `purpose='DRAFT'`.
* Rejects any modification to rows with `purpose IN (SUBMIT, MANUAL_RECALC)` — throws `RuntimeException`.

`CalculationSnapshotWriter::writeForManualRecalc` refuses to supersede a DRAFT snapshot (throws `RuntimeException`).

## Historical reproduction query

Reproducing the calculation history for an application:

```php
CalculationSnapshot::query()
    ->where('application_id', $app->id)
    ->whereIn('purpose', [CalculationSnapshot::PURPOSE_SUBMIT, CalculationSnapshot::PURPOSE_MANUAL_RECALC])
    ->orderBy('calculated_at')
    ->orderBy('id')
    ->get();
```

The double order-by is intentional: `calculated_at` may collide when a manual recalc runs immediately after a submit; the id tiebreaker preserves creation order.

## Preserved semantic distinctions

The mandate calls out that these semantic outputs must remain separate:

* `minimum_exploration_point_count` — computed by `ExplorationRequirementMatrix`
* `actual_exploration_point_count` — user-supplied form field (not derived)
* `meeting_wells_count` — computed by `WellsCountCalculator`
* `minimum_total_depth_lm` — computed by `ExplorationRequirementMatrix`
* `meeting_net_depth_total_m` — computed by `NetDepthTable`

Each corresponds to a distinct rule identifier (or is user-supplied). Snapshotting is per-rule, so the outputs never collapse into each other.

## Wiring into `Srv001Guard`

SG-04 does NOT wire `Srv001Guard` to write snapshots. That is SG-06's responsibility — refactoring the guard into a `LegacySrv001SubmissionPolicy` that returns a decision object, with the calling use case writing the snapshot inside its transaction.

Until SG-06, `CalculationSnapshotWriter` is available to any caller that wants to record a computation; the guard continues to work exactly as before.

## Files added

* `backend/modules/JeaServices/Database/Migrations/2026_08_01_000030_create_rule_provenance_tables.php`
* `backend/modules/JeaServices/Models/RuleDefinition.php`
* `backend/modules/JeaServices/Models/RuleVersion.php`
* `backend/modules/JeaServices/Models/CalculationSnapshot.php`
* `backend/modules/JeaServices/Governance/CalculationSnapshotWriter.php`
* `backend/modules/JeaServices/Database/Seeders/Srv001RulesSeeder.php`
* `backend/tests/Feature/Governance/CalculationSnapshotWriterTest.php`
* `docs/architecture/service-governance/judgments/JDG-SG04-01-rule-model-granularity.md`
* `docs/architecture/service-governance/judgments/JDG-SG04-02-recalculation-policy.md`

## Files modified

* `backend/database/seeders/DatabaseSeeder.php` — registers `Srv001RulesSeeder` after `Srv001PilotSeeder`.

## Gates

| Gate | Result |
|---|---|
| Focused snapshot writer tests | PASS (8 / 8 / 16 assertions) |
| Wide regression (Governance / Srv001 / Workflow / ServiceDefinition / Application / Submission / SharedCatalog / Fee) | PASS (300 / 301 / 1 skipped / 1498 assertions) |
| PHPStan (full, `--memory-limit=1G`) | PASS (0 errors) |

## Residuals

| RESIDUAL_ID | Owner | Status | Notes |
|---|---|---|---|
| RES-SG04-01 | per-service onboarding | OPEN | Future services need their own rule definitions |
| RES-SG04-02 | ops follow-up | OPEN | Manual recalc UX + audit event definition |

## Verdict

**PASS** — Rule-version registry, immutable calculation snapshots, and SRV-001 rule provenance seeding all in place. SRV-001 numeric behaviour unchanged. Wiring into `Srv001Guard` deferred to SG-06 per the phased-delivery plan.
