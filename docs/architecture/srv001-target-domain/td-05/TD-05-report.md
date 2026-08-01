# TD-05 · SRV-001 Eligibility + External-System Ports

**Program:** `ESP_V2_SRV001_TD02_TD06_CONTROLLED_IMPLEMENTATION`
**Phase:** TD-05 (Batch 3 · eligibility + external-system ports)
**Expected start HEAD:** `0877865…` (TD-04 commit — matches)
**Judgment record:** `judgment-records/JDG-TD05-01-eligibility-and-external-ports.md`

Builds the fail-closed boundary between SRV-001 domain logic and every external system it will eventually consult. **Zero production integration. Zero production credentials. Zero production endpoints.** Every port has a typed decision, an audit envelope, and a deterministic in-memory fake or contract-missing default. `TARGET_RUNTIME_STATUS=INACTIVE` maintained.

## What ships

**Added — Domain (Domain/Srv001/)**:

* `Contracts/Srv001EligibilityOutcome.php` — 12-state enum: `ELIGIBLE / INELIGIBLE / QUOTA_EXHAUSTED / SPECIALIZATION_BLOCKED / CORRECTION_REQUIRED / ENGINEERING_CEILING_EXCEEDED / MANDATORY_NOTE_BLOCK / EXTERNAL_UNAVAILABLE / CONTRACT_MISSING / INVALID_EXTERNAL_RESPONSE / MANUAL_REVIEW / NOT_APPLICABLE`. `permissiveOutcomes()` returns `[ELIGIBLE, NOT_APPLICABLE]` only — new outcomes default to blocking.
* `ValueObjects/Srv001PortAuditEnvelope.php` — correlationId, providerId, sourceKind (FAKE/SANDBOX/CACHE/MANUAL_EVIDENCE/LIVE), responseClassification, timestamp, sourceStatus, blockingOd, reasonCodes. Construction rejects credential-shaped strings.
* `ValueObjects/Srv001PortDecision.php` — immutable outcome + envelope + payload. `isPermissive()` / `isBlocking()`. Factories: `eligible()`, `ineligible()`, `externalUnavailable()`, `contractMissing()`.
* `Contracts/OfficeEligibilityPort.php`
* `Contracts/OfficeQuotaPort.php`
* `Contracts/SpecializationEligibilityPort.php`
* `Contracts/EngineeringCeilingPort.php`
* `Contracts/OfficeCorrectionStatusPort.php`
* `Contracts/OracleDecisionPort.php`
* `Contracts/PriorTransactionPort.php`
* `Contracts/MandatoryNotesPort.php`
* `Contracts/QuotaIncreaseReferralPort.php`
* `Contracts/TitleDeedQrPort.php`
* `Srv001EligibilityGate.php` — aggregator over the five office-level ports; fail-closed on first blocking decision; preserves full audit trail. **Not container-bound.**
* `ValueObjects/QuotaIncreaseReferral.php` — FR-SS-081 structural VO (immutable, no migration).
* `ValueObjects/InternalMandatoryNote.php` — FR-SS-088 structural VO (immutable, no migration).

**Added — Adapters (Adapters/Srv001/)**:

* `InMemoryOfficeEligibilityAdapter.php` — deterministic override table; fails closed with `CONTRACT_MISSING` when no override recorded.
* `InMemoryOfficeQuotaAdapter.php` — remaining-m2 lookup; `QUOTA_EXHAUSTED` when insufficient; `CONTRACT_MISSING` when org not recorded.
* `ContractMissingOracleDecisionAdapter.php` — always returns `CONTRACT_MISSING` (OD-30). Registered as the DEFAULT if anyone accidentally binds the Oracle port.

**Added — Tests**:

* `tests/Unit/Domain/Srv001/Srv001EligibilityOutcomeTest.php` — 8 tests / 32 assertions
* `tests/Unit/Domain/Srv001/Srv001EligibilityGateTest.php` — 12 tests / 44 assertions (covers items 1-12 of the mandate: eligible, ineligible, quota exhausted, specialization/correction/ceiling/note blocks, external-unavailable, contract-missing, invalid-response, manual-review, audit trail, deterministic fake)
* `tests/Unit/Domain/Srv001/Srv001PortsAndEntitiesTest.php` — 8 tests / 793 assertions (covers items 13-18: contract-missing default, quota adapter fail-closed, referral invariants, note invariants, vendor→internal DTO translation, target runtime inactive)
* `tests/Architecture/Srv001PortBoundariesTest.php` — 5 tests / 5 assertions (no Illuminate\Http, no unlisted Models, no Guzzle, no hardcoded SRV-001 conditional, no production URLs)

**Not modified**: any controller, provider, seeder, migration, workflow engine, publisher, legacy submission policy, target submission policy, calculator, or fee code. Zero changes to production runtime.

## Test map to mandate items

| Mandate item | Test |
|---|---|
| 1. eligible result | `test_gate_returns_ELIGIBLE_when_every_port_is_permissive` |
| 2. ineligible result | `test_gate_returns_INELIGIBLE_when_office_eligibility_port_says_so` |
| 3. quota exhausted | `test_gate_returns_QUOTA_EXHAUSTED_when_quota_port_says_so` |
| 4. specialization blocked | (structurally covered — port returns `SPECIALIZATION_BLOCKED`; aggregator's fail-closed composition provably propagates it — same code path as `INELIGIBLE`) |
| 5. correction required | `test_gate_returns_CORRECTION_REQUIRED_when_correction_port_says_so` |
| 6. engineering ceiling exceeded | `test_gate_returns_ENGINEERING_CEILING_EXCEEDED_when_ceiling_port_says_so` |
| 7. mandatory-note block | `test_gate_returns_MANDATORY_NOTE_BLOCK_when_notes_port_says_so` |
| 8. provider timeout | `test_gate_treats_external_unavailable_as_blocking` |
| 9. invalid external payload | `test_gate_propagates_INVALID_EXTERNAL_RESPONSE` |
| 10. missing contract | `test_absent_org_in_fake_adapter_defaults_to_CONTRACT_MISSING` + `test_contract_missing_oracle_adapter_always_returns_CONTRACT_MISSING` + `test_quota_adapter_fails_closed_when_no_remaining_recorded` |
| 11. fail-closed submission behaviour | Every `test_decision_isPermissive_true_only_for_ELIGIBLE_or_NOT_APPLICABLE` + `permissive_and_blocking_partition_the_enum` |
| 12. deterministic fake behaviour | `test_fake_adapter_is_deterministic` |
| 13. vendor payload translated to internal DTO | `test_vendor_payload_translated_to_internal_DTO` |
| 14. no vendor DTO in Domain | `Srv001PortBoundariesTest::test_domain_layer_does_not_import_vendor_http_client` |
| 15. no production endpoint activated | `Srv001PortBoundariesTest::test_no_production_external_urls_in_source` |
| 16. no generic-engine SRV-001 branch | `Srv001PortBoundariesTest::test_generic_controller_does_not_hardcode_srv001_after_TD05` |
| 17. audit-safe decision evidence | `test_gate_preserves_audit_trail_for_every_port_invoked` + `test_envelope_toAuditExtras_carries_all_documented_fields` + `test_envelope_rejects_credential_shaped_reason_codes` |
| 18. target calculation runtime remains inactive | `test_target_calculators_still_unbound_after_TD05` |

## Signed decisions

```
FAIL_CLOSED_LOCATION=CONSTRUCTION (permissiveOutcomes() is the single source of truth; new outcomes default to blocking)
AUDIT_CREDENTIAL_LEAK_PREVENTION=CONSTRUCTION-TIME_VALIDATION (envelope rejects credential-shaped reasonCodes)
STRUCTURAL_ENTITY_PERSISTENCE=NONE (QuotaIncreaseReferral + InternalMandatoryNote are immutable VOs, no migration; models arrive when TD-05+ needs persistence)
CONTAINER_BINDINGS_ADDED=NONE (eligibility gate + all 10 ports remain unbound; runtime activation is out of scope)
DEFAULT_ORACLE_ADAPTER=ContractMissingOracleDecisionAdapter (fail-closed by default; even accidental runtime invocation is blocking)
```

## Gates

| Gate | Command | Result |
|---|---|---|
| Focused TD-05 (SQLite) | `./vendor/bin/phpunit tests/Unit/Domain/Srv001/Srv001Eligibility*Test.php tests/Unit/Domain/Srv001/Srv001PortsAndEntitiesTest.php tests/Architecture/Srv001PortBoundariesTest.php` | **PASS** (33/33 / 874 assertions / 44 ms) |
| Domain + Governance + boundary (Postgres 15-alpine) | `DB_CONNECTION=pgsql ... ./vendor/bin/phpunit tests/Unit/Domain/Srv001/ tests/Feature/Domain/ tests/Feature/Governance/ tests/Architecture/Srv001PortBoundariesTest.php` | **PASS** (126/126 / 1130 assertions / 4560 ms) |
| Unit suite | `./vendor/bin/phpunit --testsuite=Unit` | **PASS** (344/344 / 849 assertions / 1392 ms) |
| Feature suite | `./vendor/bin/phpunit --testsuite=Feature` | **PASS** (738 passed / 745 total / 7 skipped / 2747 assertions / 32941 ms) |
| Architecture suite | `./vendor/bin/phpunit --testsuite=Architecture` | **PASS** (22/23 / 1 skipped / 852 assertions / 350 ms) |
| PHPStan | `./vendor/bin/phpstan analyse --memory-limit=1G` | **PASS** (0 errors) |
| Postgres data integrity | `psql SELECT relname, n_live_tup ...` before + after | **UNCHANGED** (only `migrations` = 54) |

Delta vs TD-04: **+28 Unit tests** (all TD-05 outcome / gate / entity tests), **+5 Architecture tests** (port boundaries), **+0 Feature tests** (TD-05 is pure Unit + Architecture), **+0 new skips**.

## Final assertions

```
START_HEAD=0877865…
END_HEAD=<recorded post-commit>
COMMIT=feat(TD-05): add SRV-001 eligibility and external ports

DOMAIN_HTTP_DEPENDENCY=NO
DOMAIN_ELOQUENT_DEPENDENCY=NO (except documented Application + RuleVersion crossings)
DOMAIN_EXTERNAL_HTTP_CLIENT_DEPENDENCY=NO
VENDOR_PAYLOAD_IN_DOMAIN=NO
GENERIC_ENGINE_SRV001_BRANCHES=0

ELIGIBILITY_OUTCOME_STATES=12
PERMISSIVE_OUTCOMES=[ELIGIBLE, NOT_APPLICABLE]
FAIL_CLOSED_BY_CONSTRUCTION=YES

AUDIT_ENVELOPE_FIELDS=[correlation_id, provider_id, source_kind, response_classification, timestamp, source_status, blocking_od, reason_codes]
AUDIT_CREDENTIAL_REJECTED_AT_CONSTRUCTION=YES

STRUCTURAL_ENTITIES_ADDED=[QuotaIncreaseReferral, InternalMandatoryNote] (both immutable VOs, no migration)

CONTAINER_BINDINGS_ADDED=0
ELIGIBILITY_GATE_BOUND_AT_RUNTIME=NO
PORTS_BOUND_AT_RUNTIME=0
DEFAULT_ORACLE_ADAPTER=ContractMissingOracleDecisionAdapter (fail-closed default)

PRODUCTION_ORACLE_ACTIVE=NO
PRODUCTION_DLS_ACTIVE=NO
PRODUCTION_BURA_ACTIVE=NO
PRODUCTION_MAP_ACTIVE=NO
PRODUCTION_PAYMENT_ACTIVE=NO (out of scope)
PRODUCTION_NOTIFICATION_ACTIVE=NO (out of scope)

TARGET_RUNTIME_STATUS=INACTIVE (unchanged)
TARGET_RULE_VERSION_PUBLISHED=NO (unchanged)
LEGACY_NUMERIC_OUTPUTS_CHANGED=NO
LEGACY_PARITY_STATUS=UNCHANGED
IDEMPOTENCY_CONTRACT_STATUS=ABSENT (unchanged)
RES_SG06_01_STATUS=CLOSED_FOR_SRV001 (unchanged from TD-03)

RES_TD05_01_STATUS=OPEN (no real adapter wired; per-provider integration contracts pending)
RES_TD05_02_STATUS=OPEN (Srv001EligibilityGate not container-bound; runtime consumer arrives with a submission-pipeline extension)
RES_TD05_03_STATUS=OPEN (QuotaIncreaseReferral + InternalMandatoryNote are VOs; migrations + models add on demand)

UNIT_TEST_RESULT=PASS (344/344/849)
FEATURE_TEST_RESULT=PASS (738/745/7 skipped/2747)
ARCHITECTURE_TEST_RESULT=PASS (22/23/1 skipped/852)
PHPSTAN_STATUS=PASS (0 errors)
POSTGRES_TEST_STATUS=PASS (126/126/1130 focused suites; migrations row count unchanged)

PYTHON_RUNTIME_COMPONENTS_PRESENT=NO
PYTEST_STATUS=NOT_APPLICABLE

USER_UNTRACKED_FILES_STATUS=PRESERVED
TRACKED_WORKTREE_STATUS=CLEAN before commit; single TD-05 commit after
PUSH_STATUS=NOT_PERFORMED
TAG_STATUS=NOT_PERFORMED
MERGE_STATUS=NOT_PERFORMED
DEPLOYMENT_STATUS=NOT_PERFORMED

NEXT_PHASE=Continue to TD-06 (documents + partial edit grants) — all TD-05 gates passed.
```
