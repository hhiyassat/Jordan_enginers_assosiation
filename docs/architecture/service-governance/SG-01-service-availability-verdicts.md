# SG-01 · Service Availability Verdicts (scaffolding)

Defines the verdict vocabulary that SG-02 will emit through `ServiceAvailabilityPolicy`. SG-01 only ships `PublicationDecision` (used inside admin publication attempts). This document reserves the verdict codes for SG-02's runtime gate.

## Verdict shape (to be implemented in SG-02)

```
ServiceAvailabilityVerdict {
    service_code: string
    service_version: string | null           // populated once SG-03 versions exist
    catalog_visible: bool
    application_creation_allowed: bool
    submission_allowed: bool
    payment_allowed: bool
    certificate_allowed: bool
    reason_codes: list<string>
    evaluated_at: timestamp
}
```

## Reason-code vocabulary reserved for SG-02

* `AVAIL_OK`
* `AVAIL_HIDDEN_NOT_PUBLISHED`
* `AVAIL_HIDDEN_RETIRED`
* `AVAIL_HIDDEN_SUSPENDED_FOR_APPLICANT`
* `AVAIL_VISIBLE_ADMIN_ONLY`
* `AVAIL_BLOCKED_PLACEHOLDER_FEE`
* `AVAIL_BLOCKED_PLACEHOLDER_WORKFLOW`
* `AVAIL_BLOCKED_MISSING_UAT`
* `AVAIL_BLOCKED_EFFECTIVE_FROM_FUTURE`
* `AVAIL_BLOCKED_LEGACY_STATUS_INACTIVE`
* `AVAIL_BLOCKED_LEGACY_STATUS_DRAFT`
* `AVAIL_ALLOWED_HISTORICAL_ONLY` (for retired-but-existing applications)
* `AVAIL_ALLOWED_ADMIN_INSPECTION`

## Preference-order stub (finalized in SG-02, RES-SG01-02)

For the transition window while legacy `status='active'` and new `publication_status='NOT_PUBLISHED'` coexist:

1. If `publication_status = RETIRED` → hidden except for historical view.
2. If `publication_status = SUSPENDED` → hidden for applicants; visible for admin.
3. If `publication_status = PUBLISHED` → visible.
4. If `publication_status = NOT_PUBLISHED` and legacy `status = 'active'` → visible **with governance warning** (transition-window fallback).
5. If `publication_status = NOT_PUBLISHED` and legacy `status != 'active'` → hidden.

The transition-window fallback (rule 4) closes when SG-03 versioning is in place and legacy rows have been migrated with a documented policy.

## Not implemented in SG-01

This document is scaffolding. The `ServiceAvailabilityPolicy` class, the verdict object, and the controller integrations all live in SG-02.
