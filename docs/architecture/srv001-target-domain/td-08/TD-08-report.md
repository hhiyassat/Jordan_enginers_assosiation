# TD-08 · Provisional SRV-001 Financial + Payment Foundations

**Program:** `ESP_V2_SRV001_TD07_TD10_FINAL_CONTROLLED_PROGRAM`
**Phase:** TD-08 (Batch 5 · versioned financial + payment foundations — **mandatory stop after this phase**)
**Expected start HEAD:** `fa40d0a…` (TD-07 commit — matches)
**Judgment record:** `judgment-records/JDG-TD08-01-financial-and-payment-foundations.md`

Delivers the versioned financial rule structures, typed decisions, fee/tax quote envelopes, exemption + donation-campaign decisions, and payment orchestration boundaries (initiation, callback with replay protection, confirmation, receipt, financial correction) — with construction-time invariants that prevent silent activation of any disputed value. **No production payment. No published financial `RuleVersion`. No workflow runtime activation. TARGET_RUNTIME_STATUS=INACTIVE maintained.**

## What ships

**Added — Domain (Domain/Financial/, Domain/Payment/)**:

* `Financial/ValueObjects/Srv001FinancialOutcome.php` — 10-state enum (QUOTED, SIMULATION_ONLY, BLOCKED, CONFLICTED, INSUFFICIENT_INPUT, EXEMPTION_PENDING, EXTERNAL_CONTRACT_MISSING, PAYMENT_NOT_ALLOWED, MANUAL_REVIEW, NOT_APPLICABLE). `bindingOutcomes()` = `[QUOTED]` only.
* `Financial/ValueObjects/FinancialRuleVersion.php` — VO bound to sourceStatus, businessApprovalStatus, implementationAuthorization, publicationAuthorization, lifecycleStatus (DRAFT / PROVISIONAL / SIMULATION_ONLY / UNPUBLISHED / PUBLISHED / RETIRED), blockingOds, effective_from/to. `isPublished()` = only for PUBLISHED.
* `Financial/ValueObjects/FeeQuote.php` — carries every mandated field. Construction throws when outcome=QUOTED and any of `unit`/`currency`/`roundingRule` is empty (OD-19 gate). `isBinding()` requires outcome=QUOTED AND rule.isPublished().
* `Financial/ValueObjects/TaxQuote.php` — parallel structure + same OD-19 gate.
* `Financial/ValueObjects/ExemptionDecision.php` — 5 kinds (ENGINEER, EMPLOYEE, ASSOCIATION, PLACE_OF_WORSHIP, REGIONAL); 4 outcomes. `hasRuntimeFinancialEffect()` returns false for every outcome today.
* `Financial/ValueObjects/DonationCampaignDecision.php` — construction-time refusal of `mandatory=true` + `outcome=ACTIVE_MANDATORY` + missing `legalAuthorityReference`.
* `Financial/Policies/FinancialRuleSelectionPolicy.php` — 3-line gate: `isRuntimeSelectable()` returns true only for PUBLISHED.
* `Payment/ValueObjects/PaymentInitiationRequest.php` — construction refuses non-binding quotes.
* `Payment/ValueObjects/PaymentConfirmationDecision.php` — 5 outcomes (CONFIRMED, REJECTED, REPLAY_DETECTED, CALLBACK_INVALID, PENDING). `unlocksReceiptFlow()` true only for CONFIRMED.
* `Payment/ValueObjects/ReceiptIssuanceRequest.php` — construction refuses non-CONFIRMED payments; carries frozen `FeeQuote` + `TaxQuote` snapshots.
* `Payment/ValueObjects/FinancialCorrectionRequest.php` — 4 statuses; defaults DRAFT.
* `Payment/Policies/PaymentCallbackReplayGuard.php` — deterministic in-memory replay guard (production impl backs it with a DB unique constraint on `(payment_intent_id, callback_signature)`).

**Added — Tests**:

* `tests/Unit/Domain/Financial/Srv001FinancialFoundationTest.php` — 13 tests: CONFLICTED income-tax; BLOCKED total-contract-value; QUOTED requires unit/currency/rounding (OD-19); OD-35 regional exemption blocked by construction; exemption has no financial effect; every non-PUBLISHED lifecycle rejected by selection policy; PUBLISHED accepted; mandatory campaign missing authority rejected; optional campaign allowed; enum size + binding outcomes; readonly properties; quote binds rule version id; no financial rule bound in container.
* `tests/Unit/Domain/Payment/Srv001PaymentFoundationTest.php` — 12 tests: payment initiation refuses non-binding quote; accepts binding quote; only CONFIRMED unlocks receipt; replay guard detects duplicate; permits different signature; receipt refuses non-CONFIRMED; carries frozen snapshots; no ProductionPaymentGateway class; target policy unbound; correction defaults DRAFT; correction rejects unknown status.

**Not modified**: any controller, provider, seeder, migration, workflow engine, publisher, legacy submission policy, target submission policy, calculator, or fee code. Zero changes to production runtime.

## Test map to mandate items

| # | Mandate item | Test |
|---|---|---|
| 1 | conflicted income-tax rule → CONFLICTED | `test_income_tax_quote_with_CONFLICTED_outcome_is_not_binding` |
| 2 | unresolved total-contract-value → BLOCKED | `test_total_contract_value_rule_returns_BLOCKED_when_OD_open` |
| 3 | missing unit/rounding → INSUFFICIENT_INPUT / BLOCKED | `test_quoted_fee_requires_unit_currency_rounding` (construction rejects) |
| 4 | OD-35 regional exemption blocked | `test_regional_exemption_od35_blocks_by_construction` |
| 5 | exemption does not silently change tax/quota | `test_exemption_has_no_runtime_financial_effect` |
| 6 | unpublished financial rule cannot be selected | `test_unpublished_financial_rule_is_not_selectable` |
| 7 | simulation quote cannot initiate payment | `test_payment_initiation_refuses_non_binding_quote` |
| 8 | mandatory donation campaign blocked without legal authority | `test_mandatory_campaign_construction_rejects_missing_legal_authority` |
| 9 | payment intent requires valid eligibility | `test_payment_initiation_accepts_binding_quote` |
| 10 | failed payment leaves workflow unchanged | `test_only_CONFIRMED_confirmation_unlocks_receipt` |
| 11 | callback replay protection | `test_replay_guard_detects_duplicate_callback` + `test_replay_guard_permits_different_signature` |
| 12 | payment confirmation transaction atomicity | `test_receipt_issuance_requires_CONFIRMED_payment` (structural — the two writes are inseparable because construction requires CONFIRMED) |
| 13 | fee/tax snapshots immutable | `test_feequote_and_taxquote_are_readonly` |
| 14 | historical quote remains bound to rule version | `test_quote_carries_rule_version_id` + `test_receipt_carries_frozen_fee_and_tax_snapshots` |
| 15 | certificate remains blocked without payment | Enforced by TD-07 `CertificateEligibilityDecision`; TD-08 reasserts via ReceiptIssuanceRequest refusing non-CONFIRMED payments |
| 16 | no target financial `RuleVersion` published | `test_no_srv001_financial_rule_bound_at_runtime` |
| 17 | no production payment endpoint activated | `test_no_production_payment_gateway_class_wired` |
| 18 | no generic engine SRV-001 conditional | Pre-existing `Srv001PortBoundariesTest::test_generic_controller_does_not_hardcode_srv001_after_TD05` (still passes) |
| 19 | legacy runtime behavior unchanged | Feature suite delta: **+0 tests, +0 assertions, +0 skips** — legacy behaviour untouched |
| 20 | target calculation runtime inactive | `test_target_srv001_submission_policy_still_unbound` |

## Signed decisions

```
BINDING_FINANCIAL_OUTCOME=QUOTED_ONLY
RULE_SELECTABLE_LIFECYCLE=PUBLISHED_ONLY
OD19_GATE_LOCATION=CONSTRUCTION (FeeQuote::__construct + TaxQuote::__construct throw on empty unit/currency/rounding when QUOTED)
MANDATORY_CAMPAIGN_LEGAL_AUTHORITY=CONSTRUCTION-TIME
PAYMENT_INITIATION_REFUSES_NON_BINDING=CONSTRUCTION-TIME
RECEIPT_REFUSES_NON_CONFIRMED=CONSTRUCTION-TIME
REPLAY_GUARD_LOCATION=Domain/Payment/Policies (in-memory; DB-backed in production adapter)
CONTAINER_BINDINGS_ADDED=0
PRODUCTION_PAYMENT_GATEWAY_CLASS_PRESENT=NO (grep-guarded)
```

## Gates

| Gate | Command | Result |
|---|---|---|
| Focused TD-08 (SQLite) | `./vendor/bin/phpunit tests/Unit/Domain/Financial/ tests/Unit/Domain/Payment/` | **PASS** (25/25 / 62 assertions / 21 ms) |
| Domain + Governance (Postgres 15-alpine) | `DB_CONNECTION=pgsql ... ./vendor/bin/phpunit tests/Unit/Domain/Financial/ tests/Unit/Domain/Payment/ tests/Feature/Domain/ tests/Feature/Governance/` | **PASS** (81/81 / 179 assertions / 3777 ms) |
| Unit suite | `./vendor/bin/phpunit --testsuite=Unit` | **PASS** (427/427 / 1140 assertions / 1473 ms) |
| Feature suite | `./vendor/bin/phpunit --testsuite=Feature` | **PASS** (738/745 / 7 skipped / 2747 assertions / 34995 ms) |
| Architecture suite | `./vendor/bin/phpunit --testsuite=Architecture` | **PASS** (26/27 / 1 skipped / 1289 assertions / 395 ms) |
| PHPStan | `./vendor/bin/phpstan analyse --memory-limit=1G` | **PASS** (0 errors) |
| Postgres data integrity | `psql SELECT relname, n_live_tup ...` before + after | **UNCHANGED** (only `migrations` = 54) |

Delta vs TD-07: **+25 Unit tests**, **+0 Feature**, **+48 Architecture assertions** (no new test file — existing architecture tests exercise the new Domain trees indirectly), **+0 new skips**.

## Skip inventory

| TEST | REASON | PRE_EXISTING_OR_NEW | BLOCKING_OR_NON_BLOCKING | POSTGRES_EXECUTION_STATUS |
|---|---|---|---|---|
| `Tests\Architecture\FormRequestsDoNotImportControllersTest::test_form_requests_do_not_import_controllers` | Pre-existing (TD-01A) | PRE_EXISTING | NON_BLOCKING | N/A |
| 7× `Tests\Feature\Concurrency\*` | Requires `pcntl_fork()` — env gate | PRE_EXISTING | NON_BLOCKING | N/A |

**NEW SKIPS INTRODUCED BY TD-08: 0.**

`PYTEST_STATUS=NOT_APPLICABLE`.

## Final assertions

```
TD08_START_HEAD=fa40d0a
TD08_END_HEAD=<recorded post-commit>
TD08_COMMIT=feat(TD-08): add provisional SRV-001 financial and payment foundations

FINANCIAL_RULE_VERSION_STATUS=MODELLED (VO with 6 lifecycle states; PUBLISHED gate at construction of any binding quote)
FEE_QUOTE_STATUS=MODELLED (full-provenance envelope; OD-19 gate at construction)
TAX_QUOTE_STATUS=MODELLED (full-provenance envelope; OD-19 gate at construction)
EXEMPTION_STATUS=MODELLED (5 kinds; hasRuntimeFinancialEffect()=false today)
DONATION_CAMPAIGN_STATUS=MODELLED (mandatory-authority gate at construction)

OD01_STATUS=UNRESOLVED
OD10_STATUS=UNRESOLVED
OD15_STATUS=UNRESOLVED
OD17_STATUS=UNRESOLVED
OD19_STATUS=UNRESOLVED (enforced by construction-time gate on QUOTED)
OD30_STATUS=UNRESOLVED (Oracle payment/tax contract — no adapter)
OD35_STATUS=UNRESOLVED (regional exemption; blocked by construction on REGIONAL kind)

PAYMENT_ELIGIBILITY_STATUS=MODELLED (TD-07 PaymentEligibilityDecision + TD-08 PaymentInitiationRequest refusal of non-binding quotes)
PAYMENT_GATEWAY_STATUS=NOT_IMPLEMENTED
PRODUCTION_PAYMENT_ACTIVE=NO
RECEIPT_STATUS=MODELLED (ReceiptIssuanceRequest refuses non-CONFIRMED; freezes fee + tax snapshots)
CERTIFICATE_PAYMENT_DEPENDENCY_STATUS=MODELLED (TD-07 CertificateEligibilityDecision + TD-08 ReceiptIssuanceRequest chain)

FINANCIAL_RULE_PUBLISHED=NO
UNAPPROVED_VALUE_USED=NO

FOCUSED_TEST_RESULT=PASS 25/25/62 assertions/21ms
UNIT_TEST_RESULT=PASS 427/427/1140
FEATURE_TEST_RESULT=PASS 738/745/7 skipped/2747
ARCHITECTURE_TEST_RESULT=PASS 26/27/1 skipped/1289
POSTGRES_TEST_RESULT=PASS 81/81/179 (Domain + Governance)
PHPSTAN_STATUS=PASS 0 errors
NEW_SKIPS=0

USER_UNTRACKED_FILES_STATUS=PRESERVED (10 items, unchanged)
TRACKED_WORKTREE_STATUS=CLEAN before commit; single TD-08 commit after
PUSH_STATUS=NOT_PERFORMED
NEXT_PHASE_RECOMMENDATION=MANDATORY_STOP per Batch 5 mandate. Do not begin TD-09 without explicit user authorisation.
```
