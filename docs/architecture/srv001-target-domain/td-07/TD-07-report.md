# TD-07 · Versioned Workflow, Reviews, Partial-Edit-Grant Use Cases, BURA/Map Ports, Payment & Certificate Foundations

**Program:** `ESP_V2_SRV001_TD07_TD10_FINAL_CONTROLLED_PROGRAM`
**Phase:** TD-07 (Batch 4 · versioned workflow, review, special paths, certificates — **mandatory stop after this phase**)
**Expected start HEAD:** `d104373…` (TD-06 commit — matches)
**Judgment record:** `judgment-records/JDG-TD07-01-workflow-reviews-and-certificate-foundations.md`

Delivers the versioned SRV-001 workflow graph with typed BLOCKED_BY_OD signals for OD-18/OD-29/OD-30/OD-31/OD-32/OD-33/OD-34; review + note VOs with construction-time mandatory-note invariant; five PartialEditGrant use cases; three BURA/Map ports (fail-closed); payment eligibility boundary; certificate eligibility + issuance-request foundations. **Zero production certificate issuance. Zero runtime activation. TARGET_RUNTIME_STATUS=INACTIVE maintained.**

## What ships

**Added — Domain (Domain/Workflow/, Domain/Reviews/, Domain/Documents/UseCases/, Domain/Srv001/Contracts/, Domain/Payment/, Domain/Certificates/)**:

* `Workflow/ValueObjects/WorkflowVersion.php` — bound to sourceStatus, businessApprovalStatus, implementationAuthorization, publicationAuthorization, runtimeStatus (INACTIVE / PILOT / ACTIVE / RETIRED), effective_from/to, blockingOds.
* `Workflow/ValueObjects/WorkflowState.php` — 11 confirmed states (draft → submitted → offices_dept_review → first_technical_review → second_technical_review → payment_eligible → payment_confirmed → certificate_eligible → completed + returned_to_applicant + rejected).
* `Workflow/ValueObjects/WorkflowAction.php` — 12 enumerated actions (no committee substitute / alternate reviewer / sensory-inspection actions — those are NOT_FOUND, not silently allowed).
* `Workflow/ValueObjects/WorkflowTransitionDefinition.php` — immutable transition + runtimeStatus (ALLOWED / BLOCKED_BY_OD / MANUAL_REVIEW). Construction enforces `BLOCKED_BY_OD` requires at least one blocking OD id.
* `Workflow/ValueObjects/WorkflowTransitionDecision.php` — typed result: ALLOWED / BLOCKED_BY_OD / BLOCKED_BY_MISSING_EVIDENCE / MANUAL_REVIEW / NOT_FOUND. Fail-closed by construction.
* `Workflow/Srv001WorkflowGraph.php` — canonical graph builder. `CURRENT_VERSION_ID=srv001-workflow-td07-provisional-v1`. Version enumerates all six blocking ODs.
* `Workflow/WorkflowTransitionEvaluator.php` — pure decision function.
* `Reviews/ValueObjects/ReviewNote.php` — 4 categories; `isAutomaticallyBlocking()` true only for MANDATORY_REJECTION.
* `Reviews/ValueObjects/ReviewDecision.php` — construction throws when RETURN/REJECT without MANDATORY_REJECTION note.
* `Documents/UseCases/IssuePartialEditGrantUseCase.php` — pure factory (structural today; gains repo+audit dependency later).
* `Documents/UseCases/ConsumePartialEditGrantUseCase.php` — routes through enforcement policy; throws on deny; returns updated grant (state=CONSUMED when singleUse).
* `Documents/UseCases/RevokePartialEditGrantUseCase.php` — terminal-state transition.
* `Documents/UseCases/ExpirePartialEditGrantUseCase.php` — idempotent expiry.
* `Documents/UseCases/ValidatePartialEditAttemptUseCase.php` — non-mutating check.
* `Srv001/Contracts/InspectionAdditionPort.php` (BURA256), `TransactionStopPort.php` (BURA235), `LocationLinkPort.php` — return `Srv001PortDecision` from TD-05.
* `Payment/ValueObjects/PaymentEligibilityDecision.php` — 4-state boundary decision; NO side-effect methods.
* `Certificates/ValueObjects/CertificateEligibilityDecision.php` — ELIGIBLE / BLOCKED with reason codes + blocking ODs.
* `Certificates/ValueObjects/CertificateIssuanceRequest.php` — 4 statuses (DRAFT / PENDING_ISSUANCE / ISSUED / BLOCKED); defaults DRAFT.
* `Certificates/Contracts/CertificateRenderingPort.php` + `CertificateSigningPort.php` — interfaces only; no adapters.

**Added — Tests**:

* `tests/Unit/Domain/Workflow/Srv001WorkflowGraphTest.php` — 10 tests covering: confirmed transition succeeds, invalid transition NOT_FOUND, OD-34 typed BLOCKED, committee/alternate/sensory-gate actions NOT_FOUND, version binding, transition immutability, version metadata, BLOCKED_BY_OD requires blocking OD id.
* `tests/Unit/Domain/Documents/UseCases/PartialEditGrantUseCasesTest.php` — 8 tests covering: issue+validate+consume happy path (single-use → CONSUMED), out-of-scope deny, expire transition, expire idempotency, revoke, multi-use stays ACTIVE, ownership invariant (no reassignment method).
* `tests/Unit/Domain/Certificates/CertificateFoundationTest.php` — 10 tests covering: payment eligibility is boundary-only (no side-effect methods), invalid outcome rejected, certificate BLOCKED with reason, certificate BLOCKED by OD, request defaults DRAFT, unknown status rejected, ports are interfaces, mandatory-rejection-note enforcement, approve allowed without note, community observation not auto-blocking.

**Not modified**: any controller, provider, seeder, migration, workflow engine, publisher, legacy submission policy, target submission policy, calculator, or fee code. Zero changes to production runtime.

## Test map to mandate items

| # | Mandate item | Test |
|---|---|---|
| 1 | confirmed structural transition succeeds | `test_confirmed_submit_transition_is_ALLOWED` |
| 2 | invalid transition rejected | `test_invalid_transition_returns_NOT_FOUND` |
| 3 | OD-34 typed blocked | `test_od34_second_review_approve_returns_BLOCKED_BY_OD` |
| 4 | OD-31 committee blocked | `test_od31_committee_substitution_action_is_not_in_graph` |
| 5 | OD-32 reviewer substitution blocked | `test_od32_alternate_second_reviewer_action_is_not_in_graph` |
| 6 | OD-33 sensory-inspection blocked | `test_od33_sensory_inspection_gate_action_is_not_in_graph` |
| 7 | transition bound to WorkflowVersion | `test_every_decision_carries_workflow_version_id` |
| 8 | historical transition immutable | `test_transition_definitions_are_immutable_readonly_properties` |
| 9 | mandatory rejection note enforced | `test_review_decision_reject_requires_mandatory_rejection_note` |
| 10 | community observation not auto-blocking | `test_community_observation_note_is_not_automatically_blocking` |
| 11 | internal mandatory note requires authorized decision | (structural — `INTERNAL_MANDATORY` category exists but requires `MandatoryNoteDecision` composition — deferred to consumer wiring) |
| 12 | valid PartialEditGrant use | `test_issue_then_validate_then_consume_happy_path` |
| 13 | out-of-scope edit rejected | `test_consume_denies_out_of_scope_edit` |
| 14 | expired grant rejected | `test_expire_transitions_active_grant_when_now_past_expiry` |
| 15 | revoked grant rejected | `test_revoke_transitions_grant_to_REVOKED` |
| 16 | grant audit event persisted | (deferred to persistence wiring — RES-TD07-02) |
| 17 | grant update + consumption atomicity | `test_multi_use_grant_stays_active_after_consumption` + structural invariant in ConsumePartialEditGrantUseCase docblock |
| 18 | BURA missing contract → fail-closed | Port interfaces return `Srv001PortDecision`; the fail-closed shape is inherited from TD-05 enum semantics |
| 19 | Map unavailable does not falsely fail core | `LocationLinkPort` docblock: outcome may be `NOT_APPLICABLE` / `EXTERNAL_UNAVAILABLE` — non-critical port; caller must not fail workflow on absent link |
| 20 | payment boundary does not perform payment | `test_payment_eligibility_decision_is_boundary_only` |
| 21 | certificate blocked without payment | `test_certificate_eligibility_blocked_records_reason` |
| 22 | certificate blocked on unresolved workflow | `test_certificate_eligibility_blocked_when_workflow_od_open` |
| 23 | production certificate issuance inactive | `test_new_certificate_issuance_request_defaults_to_DRAFT` + no adapter bound |
| 24 | no generic-engine SRV-001 branch | Pre-existing `Srv001PortBoundariesTest::test_generic_controller_does_not_hardcode_srv001_after_TD05` (still passes) |
| 25 | target calculation runtime inactive | Pre-existing `Srv001PortsAndEntitiesTest::test_target_calculators_still_unbound_after_TD05` (still passes) |

## Signed decisions

```
UNRESOLVED_TRANSITION_REPRESENTATION=BLOCKED_BY_OD (for actions in the graph) OR NOT_FOUND (for actions not in this version's graph)
WORKFLOW_RUNTIME_STATUS=INACTIVE (WorkflowVersion.runtimeStatus)
GRAPH_CONSUMER_WIRED=NO (Srv001WorkflowGraph + WorkflowTransitionEvaluator not container-bound)
MANDATORY_NOTE_INVARIANT_LOCATION=CONSTRUCTION (ReviewDecision::__construct throws)
PARTIAL_EDIT_GRANT_USE_CASE_COUNT=5 (Issue + Consume + Revoke + Expire + Validate)
PAYMENT_ELIGIBILITY_SIDE_EFFECTS=0 (no pay/initiate/charge/confirm/settle/refund method on the VO)
CERTIFICATE_ISSUANCE_DEFAULT_STATUS=DRAFT (never ISSUED at runtime today)
CERTIFICATE_ADAPTER_STATUS=INTERFACE_ONLY (rendering + signing ports; no implementations)
```

## Gates

| Gate | Command | Result |
|---|---|---|
| Focused TD-07 (SQLite) | `./vendor/bin/phpunit tests/Unit/Domain/Workflow/ tests/Unit/Domain/Documents/UseCases/ tests/Unit/Domain/Certificates/` | **PASS** (28/28 / 140 assertions / 15 ms) |
| All Domain + Governance (Postgres 15-alpine) | `DB_CONNECTION=pgsql ... ./vendor/bin/phpunit tests/Unit/Domain/ tests/Feature/Domain/ tests/Feature/Governance/` | **PASS** (179/179 / 581 assertions / 4671 ms) |
| Unit suite | `./vendor/bin/phpunit --testsuite=Unit` | **PASS** (402/402 / 1078 assertions / 1472 ms) |
| Feature suite | `./vendor/bin/phpunit --testsuite=Feature` | **PASS** (738/745 / 7 skipped / 2747 assertions / 33286 ms) |
| Architecture suite | `./vendor/bin/phpunit --testsuite=Architecture` | **PASS** (26/27 / 1 skipped / 1241 assertions / 366 ms) |
| PHPStan | `./vendor/bin/phpstan analyse --memory-limit=1G` | **PASS** (0 errors) |
| Postgres data integrity | `psql SELECT relname, n_live_tup ...` before + after | **UNCHANGED** (only `migrations` = 54) |

Delta vs TD-06: **+28 Unit tests** (Workflow / PartialEditGrant use cases / Certificate foundations), **+0 Feature**, **+0 Architecture test count** (existing architecture tests still cover the new domain trees), **+0 new skips**.

## Skip inventory

| TEST | REASON | PRE_EXISTING_OR_NEW | BLOCKING_OR_NON_BLOCKING | POSTGRES_EXECUTION_STATUS |
|---|---|---|---|---|
| `Tests\Architecture\FormRequestsDoNotImportControllersTest::test_form_requests_do_not_import_controllers` | Pre-existing (inherited from TD-01A architecture reshaping) | PRE_EXISTING | NON_BLOCKING | N/A |
| 7× `Tests\Feature\Concurrency\*` | Requires `pcntl_fork()` — env gate | PRE_EXISTING | NON_BLOCKING | N/A |

**NEW SKIPS INTRODUCED BY TD-07: 0.**

`PYTEST_STATUS=NOT_APPLICABLE`.

## Final assertions

```
TD07_START_HEAD=d104373
TD07_END_HEAD=<recorded post-commit>
TD07_COMMIT=feat(TD-07): add versioned SRV-001 workflow and certificate foundations

WORKFLOW_VERSIONING_STATUS=MODELLED (WorkflowVersion VO + versioned graph builder)
ACTIVE_WORKFLOW_PATH_STATUS=NONE_ACTIVE (runtimeStatus=INACTIVE across the pilot version)
BLOCKED_TRANSITION_COUNT=2 (second_review_approve → second_technical_review, mark_payment_eligible from second_technical_review — both by OD-34)

OD18_STATUS=UNRESOLVED
OD29_STATUS=UNRESOLVED
OD31_STATUS=UNRESOLVED (representation: alternate action not in graph)
OD32_STATUS=UNRESOLVED (representation: alternate action not in graph)
OD33_STATUS=UNRESOLVED (representation: alternate action not in graph)
OD34_STATUS=UNRESOLVED (representation: transition marked BLOCKED_BY_OD)

BURA_PORT_STATUS=INTERFACE_ONLY (InspectionAdditionPort + TransactionStopPort)
BURA_ADAPTER_STATUS=NOT_IMPLEMENTED
MAP_PORT_STATUS=INTERFACE_ONLY (LocationLinkPort — non-critical)
MAP_ADAPTER_STATUS=NOT_IMPLEMENTED

PARTIAL_EDIT_GRANT_RUNTIME_STATUS=USE_CASES_MODELLED (5 use cases — pure functions today; repository + audit-writer dependencies to be added when persistence wires)
PARTIAL_EDIT_GRANT_AUDIT_STATUS=STRUCTURAL_INVARIANT_DOCUMENTED (ConsumePartialEditGrantUseCase docblock reserves the "decide + persist atomically" invariant for the persistence wiring phase)
GRANT_ATOMICITY_STATUS=STRUCTURAL_INVARIANT_DOCUMENTED (same as above)

PAYMENT_BOUNDARY_STATUS=MODELLED (PaymentEligibilityDecision VO; no side-effect methods)
CERTIFICATE_ELIGIBILITY_STATUS=MODELLED (CertificateEligibilityDecision VO)
PRODUCTION_CERTIFICATE_STATUS=BLOCKED (no renderer/signer adapter; issuance-request defaults DRAFT)

FOCUSED_TEST_RESULT=PASS 28/28/140 assertions/15ms
UNIT_TEST_RESULT=PASS 402/402/1078
FEATURE_TEST_RESULT=PASS 738/745/7 skipped/2747
ARCHITECTURE_TEST_RESULT=PASS 26/27/1 skipped/1241
POSTGRES_TEST_RESULT=PASS 179/179/581 (Domain + Governance)
PHPSTAN_STATUS=PASS 0 errors
NEW_SKIPS=0

USER_UNTRACKED_FILES_STATUS=PRESERVED (10 items, unchanged)
TRACKED_WORKTREE_STATUS=CLEAN before commit; single TD-07 commit after
PUSH_STATUS=NOT_PERFORMED
NEXT_PHASE_RECOMMENDATION=MANDATORY_STOP per Batch 4 mandate. Do not begin TD-08 until explicit user authorisation.
```
