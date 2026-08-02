# TD-10 · Rollback Plan

**Scope:** every SRV-001 target-domain activation surface. Rollback must preserve every historical artefact — nothing bound to a legacy service/workflow/rule version may be renamed, retagged, or re-bound.

## Per-activation-surface rollback

| Activation surface | Rollback action | Preservation requirement |
|---|---|---|
| Target policy activation (`ServiceSubmissionPolicyRegistry` swap) | Remove `SRV-001 => TargetSrv001SubmissionPolicy` container binding; revert to `LegacySrv001SubmissionPolicy`. | Historical `application_service_definition_version_id` bindings untouched. |
| Target workflow activation (`WorkflowVersion.runtimeStatus=ACTIVE`) | Set target `WorkflowVersion.runtimeStatus=RETIRED`; restore legacy workflow reference. | Existing applications remain bound to their original `workflow_version_id`; historical transition definitions immutable. |
| Target financial-rule activation (`FinancialRuleVersion.lifecycleStatus=PUBLISHED`) | Set lifecycle to `RETIRED`; publish previous approved version if any. | Historical `FeeQuote` + `TaxQuote` snapshots frozen — must reference the rule-version id in effect at issue time. |
| Eligibility adapter activation | Revert container binding to `ContractMissing*` default. | No eligibility decision persisted becomes retroactively invalid — audit envelope carries `provider_id`. |
| Oracle adapter activation | Revert to `ContractMissingOracleDecisionAdapter`. | Any Oracle-mediated decision recorded during the failed activation window is marked ROLLBACK_INVALIDATED in audit_logs but the row is not deleted. |
| DLS adapter activation | Revert binding. | Same as Oracle. |
| BURA adapter activation | Revert binding for InspectionAddition / TransactionStop / PriorTransaction ports. | Same. |
| Storage adapter activation | Revert to no-op storage; migrate any newly-written objects to a quarantine bucket. | Existing `DocumentMetadata.storage_key` values remain valid; adapter reads from either bucket transparently. |
| Malware-scanner activation | Revert to `MalwareScanResult::unknown()` default; any documents with `SCAN_CLEAN` set during activation window are re-scanned. | No document's `malware_scan_status` is downgraded silently. |
| Payment gateway activation | Revoke gateway credentials + close intents in the gateway's admin panel; halt callback processing. | All existing `PaymentInitiationRequest` records preserved with `state=ROLLED_BACK`; no payment intent silently succeeds. |
| Receipt activation | Halt receipt issuance; existing `ReceiptIssuanceRequest` records with `status=ISSUED` remain valid (they reference frozen fee/tax snapshots). | No receipt is voided by rollback — only new issuance is halted. |
| Certificate activation | Halt certificate rendering + signing; existing certificates remain valid (signed artefacts are legal documents). | No signed certificate is retroactively invalidated by rollback. |

## Preservation invariants (verified by TD-10 architecture test)

Rollback of any surface MUST NOT:
- delete or rename any row in `applications`, `application_documents`, `calculation_snapshots`, `audit_logs`, `payments`, `receipts`, `certificates`
- mutate any `service_definition_version_id`, `workflow_version_id`, or `rule_version_id` FK
- alter any `calculation_snapshots.outputs` or `.intermediate_values`
- change any historical audit event's `action`, `subject_id`, or `extra`

## Rollback rehearsal checklist

- [ ] Backup taken (production DB dump + storage manifest)
- [ ] Rollback command tested on UAT DB
- [ ] Rollback timing measured (must complete within 5-minute window)
- [ ] Rollback preserves all invariants (assert via post-rollback query pack)
- [ ] Communication plan (applicant notice + JEA staff notice) drafted
- [ ] Monitoring dashboard shows rollback-triggered banner
- [ ] Support runbook updated

**Status:** ROLLBACK_REHEARSAL_STATUS = NOT_YET_PERFORMED. Documented for the cutover checklist.
