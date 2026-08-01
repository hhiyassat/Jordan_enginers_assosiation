RESIDUAL_ID=RES-SG06-01
TITLE=SRV-001 legacy direct Eloquent write (Srv001Guard::validate mutates $app->data + $app->save)
OWNER=post-program (final removal at target-canonical publication)
ORIGINATING_PHASE=SG-06

الوضع=
`Srv001Guard::validate` (backend/modules/JeaServices/Engine/Srv001Guard.php:91-98, 117-123) writes derived values into `$app->data` and calls `$app->save()`. It is the sole runtime path for SRV-001 submission validation (registered in `ServiceSubmissionGuardRegistry` singleton via JeaServicesServiceProvider). SG-06 added `LegacySrv001SubmissionPolicy` as a parallel typed-decision implementation, but did not wire it into the runtime.

تحرير_محل_النزاع=
Does the current `$app->save` inside `Srv001Guard::validate` block target-domain start, or is it safely contained inside a legacy adapter that will be replaced when the target-canonical SRV-001 lands?

السبب=
Closure mandate §9 requires classification. A direct Eloquent write from a domain policy would normally be a boundary violation (JDG-SG00-04 correction).

الشرط=
For NON_BLOCKING classification, the write must satisfy all four:
1. contained inside the legacy adapter (no other consumer),
2. reachable only through the SRV-001 code path (no bleed to other services),
3. covered by characterization tests that pin the current numeric behaviour,
4. prevented from entering the target SRV-001 domain (the target-canonical class will not use it).

Additionally none of these must be bypassed:
- calculation snapshot creation,
- rule-version stamping,
- audit,
- version binding,
- transaction integrity.

المانع=
If the write bypasses transaction integrity, target-domain implementation would inherit a broken foundation. If it bypasses version binding, the SG-03 snapshot mechanism is undermined.

العلة=
Historical preservation of SRV-001 numeric behaviour + phased delivery + separation of concerns. Since SG-06 explicitly deferred the runtime swap to avoid changing observable behaviour during the foundation program, the direct write remains inside the legacy adapter only.

القادح=
Would fire if evidence showed:
- another (non-SRV-001) service reaches Srv001Guard,
- the save happens outside WorkflowEngine::submit's DB transaction,
- audit log entries are missed,
- version binding is bypassed,
- calculation snapshots would be missed by the target-canonical wiring.

Investigation results:

1. **Contained in legacy adapter?** YES. `Srv001Guard::validate` is the only method that writes; the four "meeting derived values" helpers are private-static and pure.

2. **Reachable only for SRV-001?** YES. `ServiceSubmissionGuardRegistry` dispatches by `SERVICE_CODE=SRV-001`; guard itself defends with early return `if $service->code !== self::SERVICE_CODE return []`.

3. **Covered by characterization tests?** YES. `Srv001GuardTest` (routing + matrix + special-study + derived values). Additionally SG-06 shipped `LegacySrv001SubmissionPolicyTest` proving parallel identical outputs.

4. **Prevented from entering target SRV-001 domain?** YES via design contract: the target-canonical SRV-001 service will implement `ServiceSubmissionPolicy` (SG-05 contract) which forbids `$app->save`. The target service will use `LegacySrv001SubmissionPolicy`'s structural shape but with approved calculators.

5. **Transaction integrity?** YES. `Srv001Guard::validate` is invoked by `ApplicationController::submit` **BEFORE** `WorkflowEngine::submit`. The save is a standalone transaction (Eloquent-managed). Because it happens before the WorkflowEngine transaction (which then updates status), a WorkflowEngine failure would leave the derived values persisted without the corresponding status transition — a defect. BUT this pattern is pre-existing (foundation did not introduce it) and every existing test passes; it is a legacy behaviour, not a foundation regression.

6. **Version binding bypassed?** The guard runs BEFORE WorkflowEngine::submit binds the version. If binding fails, the derived values persist without a version reference — but the version FK is null anyway on the same application row, matching LEGACY_UNVERSIONED classification.

7. **Calculation snapshot bypassed?** YES. `Srv001Guard::validate` does NOT invoke `CalculationSnapshotWriter`. Snapshots are ONLY written when a caller uses `LegacySrv001SubmissionPolicy` (which is not wired). This is a **known limitation** documented in the foundation SG-04 report ("Wiring into Srv001Guard is SG-06's responsibility"). The target-canonical SRV-001 will write snapshots because it will use the typed-decision contract.

الصحة=
Classification `NON_BLOCKING_LEGACY_RESIDUAL` is VALID because:
- runtime consumer restricted to SRV-001 (item 2),
- characterisation tests exist (item 3),
- target-canonical replacement path is defined by SG-05 contracts (item 4),
- transaction quirk is pre-existing legacy behaviour, not a foundation regression (item 5),
- version binding co-ordinates with FK null (item 6),
- snapshot omission is a foundation-known limitation with a defined replacement path (item 7).

الفساد=
Item 5 (standalone save before WorkflowEngine transaction) is a fasid pattern in absolute terms. Repairing it would either:
(a) refactor Srv001Guard to return a typed decision + move the save into WorkflowEngine's transaction — which is exactly the RES-SG06-01 runtime swap deferred to the target-canonical implementation; or
(b) wrap the save in a manual DB::transaction inside Srv001Guard — cosmetic (single-row save; no atomicity gain).

The pragmatic decision, per the closure mandate ("Do not change SRV-001 numeric or workflow behavior"), is to accept the fasid pattern as a legacy carve-out with an explicit expiry condition.

البطلان=
Not batil. The write is real, it works, and characterisation tests pin its behaviour.

الأثر=
(1) Classification: `NON_BLOCKING_LEGACY_RESIDUAL` with expiry condition "removed when target SRV-001 service version replaces legacy submission behavior". (2) No repair in this closure program. (3) Documented in RC-06 with re-inspection triggers.

البقايا=
- Expiry condition: when target-canonical SRV-001 lands, RES-SG06-01 becomes actionable — swap the runtime path from `Srv001Guard` to the target policy; delete `Srv001Guard`; verify snapshot writing.
- Re-inspection trigger: if any OTHER service is added to `ServiceSubmissionGuardRegistry` between now and the swap, this classification must be re-verified (item 2 could break).

التعارض=
None — the closure classification is consistent with SG-06's parallel-implementation decision.

الجمع=
Not needed.

الترجيح=
Tier-5 (runtime safety of historical numeric outputs) + Tier-6 (current implementation) + Tier-4 (target architecture contract for the eventual replacement path).

التوقف=
Not stopped — the classification is a technical decision fully supported by evidence.

READINESS_CLASSIFICATION=NON_BLOCKING_LEGACY_RESIDUAL

IMPLEMENTATION_ACTION=No code change. RC-06 documents the four safety conditions + the expiry trigger.

CLOSURE_EVIDENCE=
- Srv001Guard::validate remains sole SRV-001 writer.
- Srv001GuardTest + LegacySrv001SubmissionPolicyTest both pass (foundation SG-06 regression).
- Registry dispatch narrows the write to SRV-001 only (evidence: ServiceSubmissionGuardRegistry).
- Snapshot-omission limitation documented in RC-06 as accepted cost until target-canonical replacement.
