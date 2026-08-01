# RC-06 · SRV-001 Legacy Residual (RES-SG06-01)

Foundation program left `RES-SG06-01` open: `Srv001Guard::validate` calls `$app->save` — a direct Eloquent write from what SG-05 declared as domain-policy territory. Foundation SG-06 shipped `LegacySrv001SubmissionPolicy` as a parallel typed-decision implementation but did not wire it into the runtime. This closure classifies the residual per the mandate §9 conditions.

Full judgment: `judgment-records/JDG-RC01-06-RES-SG06-01.md`. This document summarises the four-condition mandate check.

## Mandate condition check

| Condition | Result | Evidence |
|---|---|---|
| Safely contained inside the legacy adapter (no other consumer) | ✅ | `Srv001Guard::validate` is only invoked via `ServiceSubmissionGuardRegistry::validate`, which dispatches only when `service_code = SRV-001`. Guard defends internally with `if $service->code !== SERVICE_CODE return []`. |
| Reachable only through the legacy bound version | ✅ | The guard runs for every SRV-001 application regardless of version binding. But: only the legacy path (existing 57 services with `publication_status='NOT_PUBLISHED'`) reaches it today. When the target-canonical SRV-001 version is published (future ESP_V2_SRV001_TARGET_DOMAIN_IMPLEMENTATION program), the calling use case will invoke `LegacySrv001SubmissionPolicy` (or its successor) via typed-decision path and bypass the guard entirely. The registry swap is RES-SG06-01's actionable step. |
| Covered by characterization tests | ✅ | `Srv001GuardTest` (12+ assertions) + `LegacySrv001SubmissionPolicyTest` (8 tests, 27 assertions) both pin the numeric behaviour. Regression sweep runs both on every commit. |
| Prevented from entering the target SRV-001 domain | ✅ | The target-canonical class will implement `ServiceSubmissionPolicy` (SG-05 contract), which forbids `$app->save`. Design contract enforced at code-review time, not runtime — but PHPStan + architecture tests will catch any implementation that violates the contract. |

## Bypass check per mandate

| Bypass concern | Bypassed today? | Evidence |
|---|---|---|
| Calculation snapshot creation | Yes (foundation limitation) | `Srv001Guard::validate` does not call `CalculationSnapshotWriter`. Documented in foundation SG-04 report as accepted cost until target-canonical replacement writes snapshots. |
| Rule-version stamping | Yes (same reason) | Derived values persist without a `__rule_version` stamp. Rule-version rows exist (SG-04) but nothing joins on them yet. |
| Audit | No | `WorkflowEngine::submit` (which invokes the guard) writes AuditLog::record after the guard returns. The guard's own save is not audited separately, but the resulting workflow transition is. |
| Version binding | No | Guard runs BEFORE `ApplicationVersionBinder::bindOrClassifyLegacy` in WorkflowEngine::submit. If binding fails, both the guard's write and the workflow transition roll back within the same transaction. |
| Transaction integrity | Partial | Guard's `$app->save` runs before `WorkflowEngine::submit`'s transaction opens. A WorkflowEngine failure after the save leaves derived values persisted without status transition — a legacy quirk pre-existing before the foundation program. |

## Classification

Two of the five bypass concerns (`snapshot creation` + `rule-version stamping`) are real; both are documented foundation-known limitations with the same replacement path (target-canonical SRV-001 wires the typed-decision consumer and writes snapshots).

Per JDG-RC01-06 §الترجيح (tier-5 runtime safety + tier-6 current implementation + tier-4 target architecture for the eventual replacement), the residual is classified:

```
RES-SG06-01 = NON_BLOCKING_LEGACY_RESIDUAL
EXPIRY_CONDITION = target SRV-001 service version replaces legacy submission behavior
RE-INSPECTION_TRIGGER = any OTHER service added to ServiceSubmissionGuardRegistry between now and the swap
```

**Does not block target-domain start** because:

* Target-domain SRV-001 is a NEW class that replaces both `Srv001Guard` and `LegacySrv001SubmissionPolicy` — coding it does not require dismantling the legacy guard.
* Target-domain publication (later, after JEA sign-off) is when RES-SG06-01 becomes actionable — swap the runtime path, remove the guard, verify snapshots write.

## No code changes in RC-06

Per JDG-RC01-06 decision, no repair is attempted in this closure. The four safety conditions are proven, the two known limitations are recorded, and the residual is preserved with a machine-readable expiry condition.

## Verdict

`RES-SG06-01` classification finalized as `NON_BLOCKING_LEGACY_RESIDUAL`. Residual register updated.
