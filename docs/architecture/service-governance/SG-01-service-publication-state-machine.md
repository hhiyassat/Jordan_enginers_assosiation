# SG-01 · Service Publication State Machine

Companion to `SG-01-service-lifecycle-constitution.md`. Documents legal transitions between the eight lifecycle states.

## Legal transitions

```
DRAFT ──(fill schema top-level keys)──> CONFIGURED
CONFIGURED ──(add stage + fee.type)──> TECHNICALLY_VALIDATED
TECHNICALLY_VALIDATED ──(submit for UAT: uat_status:=PENDING)──> AWAITING_UAT
AWAITING_UAT ──(sign uat_status:=APPROVED + uat_reference + uat_signed_at + uat_signed_by)──> UAT_APPROVED
AWAITING_UAT ──(reject uat_status:=REJECTED)──> TECHNICALLY_VALIDATED  (uat_status returns to NOT_SUBMITTED after correction)
UAT_APPROVED ──(publish via ServicePublicationPolicy PASS + publisher ≠ uat_signed_by + publication_reason set)──> PUBLISHED
PUBLISHED ──(suspend with reason + actor)──> SUSPENDED
SUSPENDED ──(unsuspend — clear suspended_* fields)──> PUBLISHED
PUBLISHED ──(retire with reason + actor)──> RETIRED
SUSPENDED ──(retire with reason + actor)──> RETIRED
RETIRED ──(terminal — no exit)
```

## Illegal transitions

The following transitions are forbidden and MUST be rejected by whichever use case attempts them:

* `PUBLISHED` → `UAT_APPROVED` — cannot un-publish through the UAT path; use `SUSPENDED` or `RETIRED`.
* `RETIRED` → any other state — retirement is terminal.
* `DRAFT` → `PUBLISHED` (skipping intermediate states) — every publication requires the full chain.
* `AWAITING_UAT` → `PUBLISHED` — must first pass through `UAT_APPROVED`.
* Any transition performed by the same actor who signed UAT — maker-checker (enforced by `ServicePublicationPolicy`).

## Attestation retention

Fields are additive — a `SUSPENDED` service retains its `uat_reference`, `uat_signed_at`, `uat_signed_by`, `published_at`, `published_by`, and `publication_reason` so the historical trail survives.

Un-suspension clears only `suspended_at`, `suspended_by`, `suspension_reason` — publication timestamps are preserved.

Retirement clears none of the historical fields — it only adds `retired_at`, `retired_by`, `retirement_reason`.

## Transaction boundary

Each transition is a single-row UPDATE plus one audit event. SG-01 does not implement the use cases that perform the transitions (SG-02 does). This document declares the boundary each such use case must obey:

* One database transaction per transition.
* Audit event written in the same transaction.
* No cross-service state changes (a service's transition never affects another service's row).

## Concurrency

Transitions are safe against concurrent requests because they target a single row on `service_definitions` and each transition changes at least one column that participates in the previous state's precondition. A stale reader that attempts to re-transition will violate the state-machine's preconditions and be rejected by `ServicePublicationPolicy`.

## Notes on the legacy `status` column

The legacy `status` (active|inactive|draft) is orthogonal to `publication_status`. Its transitions are governed by the existing `ServiceCatalogController::updateStatus`. SG-02 will decide the reconciliation rule between the two signals for the transition window.
