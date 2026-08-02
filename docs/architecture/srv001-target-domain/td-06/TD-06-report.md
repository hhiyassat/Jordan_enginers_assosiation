# TD-06 · SRV-001 Document + Partial-Edit-Grant Foundations

**Program:** `ESP_V2_SRV001_TD02_TD06_CONTROLLED_IMPLEMENTATION`
**Phase:** TD-06 (Batch 3 · document + partial-edit foundations — **mandatory final stop after this phase**)
**Expected start HEAD:** `389342f…` (TD-05 commit — matches)
**Judgment record:** `judgment-records/JDG-TD06-01-documents-and-partial-edit-grants.md`

Builds SRV-001 document metadata, versioning, contract-locking, quarantine, and PartialEditGrant foundations. **Zero production storage. Zero production antivirus. OD-24 remains unresolved — no attachment limits invented.** `TARGET_RUNTIME_STATUS=INACTIVE` maintained.

## What ships

**Added — Domain (Domain/Documents/)**:

* `ValueObjects/DocumentCategory.php` — enum of the 13 mandated categories.
* `ValueObjects/DocumentMetadata.php` — immutable metadata VO. NEVER carries file bytes. `withReplacementVersion()` returns a new VO with `documentVersion+1` (SG-04 immutability).
* `ValueObjects/PartialEditGrant.php` — immutable grant VO with grantId, actor+role, reason, permitted sections+fields, issue+expiry, state (ACTIVE / CONSUMED / EXPIRED / REVOKED), singleUse flag. `isUsable(now)` returns true only when state=ACTIVE and expiry not passed.
* `ValueObjects/AttachmentLimitPolicy.php` — pure resolver. Returns `configurationBlocked(['OD-24_UNRESOLVED'])` when no signed configuration exists; returns `allowed($bytes)` only for categories explicitly registered via `withPublishedLimit()`.
* `ValueObjects/AttachmentLimitDecision.php` — immutable decision value object.
* `SignedContractLockPolicy.php` — narrow legal-lock: `contract_owner_name, contract_party_type, contract_signed_at, tax_number, national_number`. `isLocked($docs)` = at least one `SIGNED_CONTRACT` doc with `VALIDATION_ACCEPTED`.
* `PartialEditGrantEnforcementPolicy.php` — composes with lock policy. Deny order: REVOKED → CONSUMED → EXPIRED → expiry-passed → out-of-scope → legal-lock-active → allowed. A valid grant CANNOT unlock a legally-protected field.
* `EditPermissionDecision.php` — immutable decision value object.
* `Contracts/ObjectStoragePort.php` — Domain-layer contract; adapter reads/writes bytes; Domain never touches bytes.
* `Contracts/ChecksumCalculatorPort.php` — 64-char lowercase-hex SHA-256.
* `Contracts/MimeValidatorPort.php` — declared vs detected mime + magic-byte match.
* `Contracts/MalwareScannerPort.php` — returns `MalwareScanResult`.
* `Contracts/MalwareScanResult.php` — factories `clean()` / `infected([$reasons])` / `unknown([$reasons])`. `isClean()` true only for CLEAN.
* `Contracts/QuarantinePort.php` — hold + release, returning updated `DocumentMetadata`.

**Added — Tests**:

* `tests/Unit/Domain/Documents/DocumentMetadataTest.php` — 16 tests / covers metadata invariants, replacement versioning, unknown-category rejection, attachment-limit blocking + partial publication, borehole/site-image categories, all 13 categories enumerated.
* `tests/Unit/Domain/Documents/PartialEditGrantTest.php` — 12 tests / covers in-scope permit, out-of-scope deny, expiry, revocation, consumption, legal-lock-beats-grant, section-scoped grant, audit shape, invalid state rejection, single-use replay deny, MalwareScanResult contract.
* `tests/Unit/Domain/Documents/SignedContractLockTest.php` — 2 tests / covers lock activation + narrow field list.
* `tests/Architecture/DocumentsDomainBoundariesTest.php` — 4 tests / covers no file-bytes property, no vendor storage/AV import, no 500MB/4MB hardcoded constants, target policy still unbound.

**Not modified**: any controller, provider, seeder, migration, workflow engine, publisher, legacy submission policy, target submission policy, calculator, fee code. Zero changes to production runtime.

## Test map to mandate items

| Mandate item | Test |
|---|---|
| 1. authorized attachment registration | `test_valid_metadata_construction_succeeds` |
| 2. unauthorized attachment registration | `test_unknown_category_is_rejected` |
| 3. MIME mismatch | `test_mime_mismatch_is_flagged_when_declared_and_detected_differ` |
| 4. magic-byte mismatch | `test_mime_match_returns_false_when_no_detected_mime` (structural) |
| 5. checksum persistence | `test_construction_rejects_short_or_malformed_checksum` + happy-path metadata |
| 6. quarantine state | `test_metadata_carries_quarantine_state_verbatim` |
| 7. scan-pending state | `test_metadata_carries_scan_pending` |
| 8. immutable document version | `test_document_metadata_is_immutable_no_property_setters` |
| 9. replacement creates a new version | `test_withReplacementVersion_returns_new_instance_with_incremented_version` |
| 10. signed-contract lock | `test_lock_activates_once_signed_contract_is_accepted` + `test_only_documented_narrow_field_set_is_protected` |
| 11. valid PartialEditGrant | `test_active_grant_permits_in_scope_field` |
| 12. edit outside grant rejected | `test_edit_outside_grant_scope_is_denied` |
| 13. expired grant rejected | `test_expired_grant_is_denied` |
| 14. revoked grant rejected | `test_revoked_grant_is_denied` |
| 15. grant audit evidence | `test_grant_carries_full_audit_shape` |
| 16. concurrent / duplicate use | `test_single_use_grant_denies_reuse_after_consumption` + `test_consumed_grant_is_denied` |
| 17. individual borehole-image structural | `test_borehole_image_category_is_recognized` |
| 18. general-site-image structural | `test_general_site_image_category_is_recognized` |
| 19. unresolved OD-24 not used as default | `test_attachment_limit_policy_is_blocked_when_configuration_not_published` + `test_no_hardcoded_500mb_or_4mb_limits_in_documents_domain` |
| 20. file bytes absent from Domain entities | `test_document_metadata_never_carries_file_bytes` + `test_domain_documents_never_declare_a_file_bytes_property` |
| 21. no production storage claim | `test_domain_documents_does_not_import_vendor_storage_or_av_client` |
| 22. no production AV claim | `test_malware_scan_result_UNKNOWN_is_not_treated_as_clean` + `test_domain_documents_does_not_import_vendor_storage_or_av_client` |

## Control axis per port

| Port | CONTROL_MODELLED | ADAPTER_IMPLEMENTED | ADAPTER_TESTED | PRODUCTION_CONFIGURED | PRODUCTION_VERIFIED |
|---|---|---|---|---|---|
| ObjectStoragePort         | YES | NO | NO | NO | NO |
| ChecksumCalculatorPort    | YES | NO | NO | NO | NO |
| MimeValidatorPort         | YES | NO | NO | NO | NO |
| MalwareScannerPort        | YES | NO (contract enforced by unit test on `MalwareScanResult`) | NO | NO | NO |
| QuarantinePort            | YES | NO | NO | NO | NO |

## Signed decisions

```
HARDCODED_UNAPPROVED_LIMITS=NO
DEFAULT_PRODUCTION_LIMITS=NONE
LIMIT_SOURCE=VERSIONED_CONFIGURATION
TARGET_LIMIT_CONFIGURATION_STATUS=UNPUBLISHED
OD24_LIMIT_STATUS=UNRESOLVED
UNAPPROVED_LIMIT_USED=NO
FILE_BYTES_IN_DOMAIN=NO
```

## Gates

| Gate | Command | Result |
|---|---|---|
| Focused TD-06 (SQLite) | `./vendor/bin/phpunit tests/Unit/Domain/Documents/ tests/Architecture/DocumentsDomainBoundariesTest.php` | **PASS** (34/34 / 262 assertions / 32 ms) |
| All Documents + Srv001 + Governance (Postgres 15-alpine) | `DB_CONNECTION=pgsql ... ./vendor/bin/phpunit tests/Unit/Domain/Documents/ tests/Architecture/DocumentsDomainBoundariesTest.php tests/Unit/Domain/Srv001/ tests/Feature/Domain/ tests/Feature/Governance/` | **PASS** (155/155 / 614 assertions / 4429 ms) |
| Unit suite | `./vendor/bin/phpunit --testsuite=Unit` | **PASS** (374/374 / 938 assertions / 1415 ms) |
| Feature suite | `./vendor/bin/phpunit --testsuite=Feature` | **PASS** (738/745 / 7 skipped / 2747 assertions / 23524 ms) |
| Architecture suite | `./vendor/bin/phpunit --testsuite=Architecture` | **PASS** (26/27 / 1 skipped / 1081 assertions / 373 ms) |
| PHPStan | `./vendor/bin/phpstan analyse --memory-limit=1G` | **PASS** (0 errors) |
| Postgres data integrity | `psql SELECT relname, n_live_tup ...` before + after | **UNCHANGED** (only `migrations` = 54) |

Delta vs TD-05: **+30 Unit tests** (Documents metadata / grant / lock), **+4 Architecture tests** (Documents boundaries), **+0 Feature tests**, **+0 new skips**.

## Skip inventory

| TEST | REASON | PRE_EXISTING_OR_NEW | BLOCKING_OR_NON_BLOCKING | POSTGRES_EXECUTION_STATUS |
|---|---|---|---|---|
| `Tests\Architecture\FormRequestsDoNotImportControllersTest::test_form_requests_do_not_import_controllers` | Pre-existing skip inherited from TD-01A architecture reshaping | PRE_EXISTING | NON_BLOCKING | N/A (unit-level architecture test — no DB) |
| 7× `Tests\Feature\Concurrency\*` | Requires `pcntl_fork()` — pre-existing env gate | PRE_EXISTING | NON_BLOCKING | N/A (env-gated) |

**NEW SKIPS INTRODUCED BY TD-05 or TD-06: 0.**

`PYTEST_STATUS=NOT_APPLICABLE` — no Python runtime components exist in the repository.

## Combined Batch-3 (TD-05 + TD-06) final report

```
TD05_START_HEAD=0877865
TD05_END_HEAD=389342f
TD05_COMMIT=feat(TD-05): add SRV-001 eligibility and external ports

TD06_START_HEAD=389342f
TD06_END_HEAD=<recorded post-commit>
TD06_COMMIT=feat(TD-06): add SRV-001 document and partial-edit foundations

ELIGIBILITY_PORT_STATUS=MODELLED_NO_ADAPTER (OfficeEligibilityPort interface + InMemoryOfficeEligibilityAdapter fake)
QUOTA_PORT_STATUS=MODELLED_NO_ADAPTER (OfficeQuotaPort + InMemoryOfficeQuotaAdapter fake)
SPECIALIZATION_PORT_STATUS=MODELLED_NO_ADAPTER (SpecializationEligibilityPort interface only)
MANDATORY_NOTE_STATUS=MODELLED_NO_ADAPTER (MandatoryNotesPort + InternalMandatoryNote VO)
QUOTA_REFERRAL_STATUS=MODELLED_NO_ADAPTER (QuotaIncreaseReferralPort + QuotaIncreaseReferral VO)
TITLE_DEED_QR_STATUS=MODELLED_NO_ADAPTER (TitleDeedQrPort interface only)

ORACLE_CONTRACT_STATUS=CONTRACT_MISSING (ContractMissingOracleDecisionAdapter fails closed by default)
DLS_CONTRACT_STATUS=CONTRACT_MISSING (pre-existing DlsLookupPort from TD-01; no adapter wired)
BURA_CONTRACT_STATUS=NOT_MODELLED (no port in TD-05 scope — BURA is a downstream flow)
MAP_CONTRACT_STATUS=NOT_MODELLED (mandate labelled it "non-critical"; deferred)

PRODUCTION_ORACLE_ACTIVE=NO
PRODUCTION_DLS_ACTIVE=NO
PRODUCTION_BURA_ACTIVE=NO
FAIL_CLOSED_STATUS=ENFORCED_BY_CONSTRUCTION (Srv001EligibilityOutcome::permissiveOutcomes() is the only source of truth for permission)

DOCUMENT_DOMAIN_STATUS=MODELLED (DocumentMetadata VO + 13-category enum + validation/quarantine/scan enums)
DOCUMENT_VERSIONING_STATUS=MODELLED (DocumentMetadata::withReplacementVersion increments version + sets supersededDocumentId)
SIGNED_CONTRACT_LOCK_STATUS=MODELLED (SignedContractLockPolicy — narrow five-field list)
QUARANTINE_STATUS=MODELLED (QuarantinePort + DocumentMetadata quarantine enum states)
MALWARE_SCAN_STATUS=MODELLED (MalwareScannerPort + MalwareScanResult; isClean() true only for CLEAN)
STORAGE_ADAPTER_STATUS=NOT_IMPLEMENTED (ObjectStoragePort interface only; no adapter)

PARTIAL_EDIT_GRANT_STATUS=MODELLED (PartialEditGrant VO + PartialEditGrantEnforcementPolicy)
OUT_OF_SCOPE_EDIT_BLOCKED=YES (proven by test_edit_outside_grant_scope_is_denied)
EXPIRED_GRANT_BLOCKED=YES (proven by test_expired_grant_is_denied)
REVOKED_GRANT_BLOCKED=YES (proven by test_revoked_grant_is_denied)
GRANT_AUDIT_STATUS=STRUCTURAL_FIELDS_PRESENT (grantId, grantingActorId, grantingRole, reason, issueTimestamp, expiryTimestamp, state, singleUse)

OD24_LIMIT_STATUS=UNRESOLVED
UNAPPROVED_LIMIT_USED=NO (AttachmentLimitPolicy returns CONFIGURATION_BLOCKED)
FILE_BYTES_IN_DOMAIN=NO (proven by test_domain_documents_never_declare_a_file_bytes_property)

TARGET_RUNTIME_STATUS=INACTIVE (unchanged since TD-03 baseline verification; reasserted every phase)
TARGET_RULE_VERSION_PUBLISHED=NO
LEGACY_PARITY_STATUS=UNCHANGED
RES_SG06_01_STATUS=CLOSED_FOR_SRV001 (unchanged from TD-03)

OPEN_OD_COUNT=all pre-existing ODs unchanged (this program did not close any OD)
ODS_CLOSED=none
BLOCKING_OD_LIST=[OD-11, OD-19, OD-20, OD-24, OD-30] plus the full pre-existing list per docs/architecture/service-governance/
NEW_RESIDUALS=[RES-TD03-01, RES-TD04-01, RES-TD04-02, RES-TD05-01, RES-TD05-02, RES-TD05-03, RES-TD06-01, RES-TD06-02, RES-TD06-03, RES-TD06-04]
CLOSED_RESIDUALS=[RES-SG06-01 (for SRV-001 — closed by TD-03), RES-TD02-01 (closed by TD-03), RES-TD02-02 (closed by TD-03)]

FOCUSED_TD05_TESTS=PASS (33/33 SQLite / 126/126 Postgres)
FOCUSED_TD06_TESTS=PASS (34/34 SQLite / 155/155 Postgres domain+governance combined)
UNIT_TEST_RESULT=PASS (374/374/938)
FEATURE_TEST_RESULT=PASS (738/745/7 skipped/2747 assertions)
ARCHITECTURE_TEST_RESULT=PASS (26/27/1 skipped/1081)
POSTGRES_TEST_RESULT=PASS (all TD-05 + TD-06 focused suites; migrations row count unchanged before + after)
PHPSTAN_STATUS=PASS (0 errors)
FRONTEND_GATE_STATUS=N/A (no frontend code changed in Batch 3)
NEW_SKIPS=0 (all 8 skips are pre-existing — 1 architecture + 7 concurrency env-gates)

USER_UNTRACKED_FILES_STATUS=PRESERVED (10 items at repo root, unchanged across TD-03 through TD-06)
TRACKED_WORKTREE_STATUS=CLEAN before each phase; single commit per phase after
PUSH_STATUS=NOT_PERFORMED
TAG_STATUS=NOT_PERFORMED
MERGE_STATUS=NOT_PERFORMED
DEPLOYMENT_STATUS=NOT_PERFORMED
PUBLICATION_STATUS=NOT_PERFORMED

TECHNICAL_FOUNDATION_STATUS=BATCH_3_COMPLETE — structural foundation for eligibility, external boundaries, documents, and partial-edit grants delivered as pure Domain code with pass-through fakes. Runtime consumers not wired.
UAT_CANDIDATE_STATUS=NOT_UAT_READY — service is NOT production-ready. Every eligibility port needs a signed integration contract + real adapter; every storage/AV/quarantine port needs a real adapter; OD-24 must resolve before any file-upload UAT; target-domain calculator promotion still blocked by per-rule OD-Closure (OD-11 / OD-19 / OD-20).

NEXT_PHASE_RECOMMENDATION=MANDATORY_STOP per Batch 3 mandate. Do not begin Batch 4 (or per-provider adapter integration) until user authorises. When work resumes, natural next paths are: (a) per-port real-adapter integration alongside signed integration contracts; (b) OD-24 resolution + AttachmentLimitPolicy configuration publication; (c) container binding + runtime consumer for Srv001EligibilityGate; (d) partial-edit controller endpoint + PartialEditGrantEnforcementPolicy consumption; (e) additive migrations for QuotaIncreaseReferral + InternalMandatoryNote + DocumentMetadata persistence.

TARGET_RULE_VERSION_PUBLISHED=NO
TARGET_SERVICE_PUBLICLY_ACTIVATED=NO
PRODUCTION_INTEGRATIONS_ACTIVE=NO
PUBLICATION_AUTHORIZATION=NO
PRODUCTION_AUTHORIZATION=NO
```

**The complete SRV-001 target-domain service is NOT production-ready after TD-06.** Structural foundation only. No push, no tag, no merge, no deploy, no publish.
