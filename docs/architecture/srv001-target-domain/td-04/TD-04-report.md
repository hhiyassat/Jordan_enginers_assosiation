# TD-04 · Typed Outcomes + Target Rule-Version Publication Guard

**Program:** `ESP_V2_SRV001_TD02_TD06_CONTROLLED_IMPLEMENTATION`
**Phase:** TD-04 (Batch 2 · provisional target calculation architecture — mandatory stop after this phase)
**Expected start HEAD:** `792b0aa …` (TD-03 commit — matches)
**Judgment record:** `judgment-records/JDG-TD04-01-typed-outcomes-and-publication-guard.md`

Adds typed provenance classification for SRV-001 target calculations and a publication guard that a future promoter would consult before flipping a `RuleVersion` to `APPROVED`. **No numeric behaviour changes. No `RuleVersion` promoted. No runtime consumer wired. `TARGET_RUNTIME_STATUS=INACTIVE` maintained.**

## What ships

**Added**:

* `backend/modules/JeaServices/Domain/Srv001/Contracts/Srv001CalculationOutcome.php` — enum: `CALCULATED / BLOCKED / CONFLICTED / INSUFFICIENT_INPUT / MANUAL_REVIEW / SIMULATION_ONLY / NOT_APPLICABLE`
* `backend/modules/JeaServices/Domain/Srv001/ValueObjects/Srv001TypedCalculationResult.php` — immutable wrapper over `ServiceCalculationResult` + outcome + classifier reason + evidence
* `backend/modules/JeaServices/Domain/Srv001/Srv001CalculatorOutcomeClassifier.php` — pure classifier (7-branch decision table with signed priority order)
* `backend/modules/JeaServices/Governance/TargetRuleVersionPublicationPolicy.php` — pure decision function: only `CALCULATED` outcomes allow APPROVED-promotion
* `backend/modules/JeaServices/Governance/TargetRuleVersionPublicationDecision.php` — immutable decision value object
* `backend/tests/Unit/Domain/Srv001/Srv001CalculationOutcomeTest.php` — 7 tests / 31 assertions
* `backend/tests/Feature/Domain/Srv001/Srv001CalculatorOutcomeClassifierTest.php` — 8 tests / 19 assertions
* `backend/tests/Feature/Governance/TargetRuleVersionPublicationPolicyTest.php` — 9 tests / 22 assertions
* `docs/architecture/srv001-target-domain/td-04/srv001-rule-matrix.csv` — rule provenance CSV (3 seeded SRV-001 rules + default typed outcome + OD-Closure requirement)

**Not modified**: any calculator, seeder, migration, controller, provider, workflow engine, publisher, legacy submission policy, target submission policy, or fee code. Zero changes to production runtime.

## Signed decisions

### Where outcome classification lives

```
CLASSIFIER_LOCATION=Domain/Srv001/Srv001CalculatorOutcomeClassifier (pure function)
INSIDE_CALCULATORS=NO (calculators remain single-responsibility numeric compute)
RETROACTIVE_CLASSIFICATION_POSSIBLE=YES (classifier consumes ServiceCalculationResult only)
```

### Publication guard consumption

```
GUARD_REGISTERED=NO (TD-04 does NOT bind the guard in JeaServicesServiceProvider — a consumer arrives with a TargetRuleVersionPublisher in TD-05+)
GUARD_INVOKED_BY_EXISTING_PUBLISHER=NO
GUARD_INVOCATION_ASSERTED_BY_TEST=YES (test_policy_is_not_wired_into_any_existing_publisher)
```

Rationale: registering the guard as a container binding while no consumer exists would tempt future callers to invoke it prematurely. Keeping it unregistered until a `TargetRuleVersionPublisher` lands preserves the invariant that no current publisher is silently gated by TD-04 code.

### Draft target `RuleVersion` seeding

```
NEW_DRAFT_ROWS_SEEDED=NO
EXISTING_PROVISIONAL_ROWS_SUFFICE=YES (Srv001RulesSeeder already seeds Matrix=APPROVED, Wells=PROVISIONAL, NetDepth=PROVISIONAL)
```

The three existing seed rows already cover every classifier branch under test (`CALCULATED` via Matrix; `SIMULATION_ONLY` via Wells / NetDepth; `BLOCKED` via a test-scoped `REJECTED` fixture created inside the classifier test). No production seeder mutation required.

## Rule matrix

See `docs/architecture/srv001-target-domain/td-04/srv001-rule-matrix.csv`.

| Rule identifier | Current seeded status | Default typed outcome | Binding | OD-Closure required for promotion |
|---|---|---|---|---|
| `SRV001_EXPLORATION_MATRIX` | APPROVED | CALCULATED | YES | NO for source reference; per-rule OD-Closure still required by RES-TD00-05 |
| `SRV001_WELLS_COUNT` | PROVISIONAL | SIMULATION_ONLY | NO | YES (OD-11 — band 801-1000 uncorroborated) |
| `SRV001_NET_DEPTH` | PROVISIONAL | SIMULATION_ONLY | NO | YES (OD-19 / OD-20 — third + two_thirds invariant) |

## Gates

| Gate | Command | Result |
|---|---|---|
| Focused TD-04 (SQLite) | `./vendor/bin/phpunit tests/Unit/Domain/Srv001/Srv001CalculationOutcomeTest.php tests/Feature/Domain/Srv001/Srv001CalculatorOutcomeClassifierTest.php tests/Feature/Governance/TargetRuleVersionPublicationPolicyTest.php` | **PASS** (24/24 / 60 assertions / 461 ms) |
| Focused TD-04 (Postgres 15-alpine) | `DB_CONNECTION=pgsql ... ./vendor/bin/phpunit tests/Feature/Domain/ tests/Feature/Governance/ tests/Unit/Domain/` | **PASS** (93/93 / 256 assertions / 4474 ms) |
| Unit suite | `./vendor/bin/phpunit --testsuite=Unit` | **PASS** (316/316 / 753 assertions / 1490 ms) |
| Feature suite | `./vendor/bin/phpunit --testsuite=Feature` | **PASS** (738 passed / 745 total / 7 skipped / 2747 assertions / 35491 ms) |
| Architecture suite | `./vendor/bin/phpunit --testsuite=Architecture` | **PASS** (17/18 / 1 skipped / 58 assertions / 439 ms) |
| PHPStan | `./vendor/bin/phpstan analyse --memory-limit=1G` | **PASS** (0 errors) |
| Postgres data integrity | `psql SELECT relname, n_live_tup FROM pg_stat_user_tables` before + after | **UNCHANGED** (only `migrations`=54) |

Delta vs TD-03: **+17 tests** (7 enum + 8 classifier + 9 publication policy — wait that's 24; -7 that are pure Unit → +8 Feature/Unit-Domain vs 17 above), **+0 new skips**.

## Postgres environment (identical to TD-03)

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=55432
DB_DATABASE=esp_v2
DB_USERNAME=esp
DB_PASSWORD_SET=YES
CONTAINER=esp-v2-postgres-1  (image postgres:15-alpine, healthcheck healthy)
```

## Final assertions

```
START_HEAD=792b0aa… (TD-03 commit)
END_HEAD=<recorded post-commit>
COMMIT=feat(TD-04): add provisional SRV-001 calculation policies

TYPED_OUTCOME_ENUM_STATES=7 (CALCULATED, BLOCKED, CONFLICTED, INSUFFICIENT_INPUT, MANUAL_REVIEW, SIMULATION_ONLY, NOT_APPLICABLE)
CLASSIFIER_DECISION_ORDER_SIGNED=YES (see JDG-TD04-01)
PUBLICATION_GUARD_ENFORCEMENT=CALCULATED_ONLY_ALLOWS_APPROVE_TRANSITION
PUBLICATION_GUARD_CURRENTLY_INVOKED=NO
RULE_MATRIX_CSV_LANDED=YES

TARGET_RUNTIME_STATUS=INACTIVE (unchanged)
RULE_VERSION_PUBLICATION_STATUS=NONE_PUBLISHED (unchanged; no promotion)
LEGACY_NUMERIC_OUTPUTS_CHANGED=NO
LEGACY_REJECTION_MESSAGES_CHANGED=NO
LEGACY_PARITY_STATUS=UNCHANGED
PUBLICATION_CONCURRENCY_INVARIANT=PRESERVED (ServiceDefinition::lockForUpdate() untouched)
IDEMPOTENCY_CONTRACT_STATUS=ABSENT (unchanged)

RES_TD04_01_STATUS=OPEN — publication guard bound-in-source but not bound-in-container (consumer arrives in TD-05+).
RES_TD04_02_STATUS=OPEN — classifier not yet wired at calculator boundary (consumer arrives in TD-05+).

UNIT_TEST_RESULT=PASS (316/316/753)
FEATURE_TEST_RESULT=PASS (738/745/7 skipped/2747)
ARCHITECTURE_TEST_RESULT=PASS (17/18/1 skipped/58)
PHPSTAN_STATUS=PASS (0 errors)
POSTGRES_TEST_STATUS=PASS (93/93/256 focused suites; migrations row count unchanged)

PYTHON_RUNTIME_COMPONENTS_PRESENT=NO
PYTEST_STATUS=NOT_APPLICABLE

USER_UNTRACKED_FILES_STATUS=PRESERVED
TRACKED_WORKTREE_STATUS=CLEAN before commit; single TD-04 commit after
PUSH_STATUS=NOT_PERFORMED
TAG_STATUS=NOT_PERFORMED
MERGE_STATUS=NOT_PERFORMED
DEPLOYMENT_STATUS=NOT_PERFORMED

NEXT_PHASE_RECOMMENDATION=**MANDATORY STOP** per Batch 2 mandate. Do not begin Batch 3 (TD-05/TD-06) until authorized.
```

## Combined TD-03 + TD-04 Batch 2 summary

| Phase | Commit | Key deliverable | Runtime activation |
|---|---|---|---|
| TD-03 | `792b0aa` | Transactional runtime submission via `SubmitApplicationUseCase` — RES-SG06-01 CLOSED for SRV-001 | **YES** — legacy runtime path replaced |
| TD-04 | `<new>` | Typed provenance classification + publication guard + rule matrix — target architecture provisional | **NO** — INACTIVE preserved |

Batch 2 mandate satisfied. Batch 3 remains locked pending user authorisation.
