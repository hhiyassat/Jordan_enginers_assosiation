# Cross-Cutting Submission Pipeline

**Status:** partial (CC-001 implemented; CC-002/003/004 pending)
**Origin:** stakeholder input 2026-07-27 (Abdullah Abu Haiba) — see `docs/manual-canonicalization/2025/batch-01/11_addendum_2026-07-27_cross_cutting_validations.md`

## Problem

Every service submission must pass platform-wide validation gates before any per-service rule runs. The current architecture has `ServiceSubmissionGuardRegistry` (per-service, keyed by service code) but no counterpart for validations that apply to every service regardless of code.

## Solution

Add `CrossCuttingSubmissionPipeline` — a container-registered orchestrator of `CrossCuttingSubmissionGuard` implementations. Runs in `ApplicationController::submit` AFTER schema data+document validation, BEFORE the per-service `ServiceSubmissionGuardRegistry`.

Pipeline order:

```
1. SchemaValidator::validateData        (per-service schema shape check)
2. SchemaValidator::validateDocuments   (per-service document required check)
3. CrossCuttingSubmissionPipeline       ← NEW — platform-wide gates
     ├─ CadastralConflictGuard          (CC-001)
     ├─ OwnerMatchClearanceGuard        (CC-002 — pending)
     └─ QuotaRoutingGuard               (CC-003 — pending; may replace CapacityGuard integration)
4. ServiceSubmissionGuardRegistry       (per-service, e.g. Srv001Guard)
5. CapacityGuard                        (existing; may fold into CC-003)
6. SanctionGuard                        (existing)
7. WorkflowEngine::submit               (state transition)
```

## Interface

`CrossCuttingSubmissionGuard::validate(Application $app): array`. Same signature as `ServiceSubmissionGuard`, distinct interface for clarity. Each guard is responsible for determining whether the current application is in scope for its check (typically by inspecting `$app->serviceDefinition->schema['fields']` for the fields it cares about).

## Assumptions on open questions (from addendum §"Impact on Batch 01 recommendation")

Where addendum blocking questions have no explicit answer yet, these are the defaults implemented; each is documented in the guard's docblock so a reviewer sees the assumption at the point of enforcement.

| addendum-Q | default in this session | override how |
|---|---|---|
| UNQ-015 name matching | Arabic-normalized via `Modules\JeaServices\Support\ArabicNormalizer` (strip diacritics, unify alef ا/أ/إ/آ/ٱ, unify ya ى/ي, collapse whitespace) | Replace normalizer implementation |
| UNQ-016 Offices Department in RBAC | Not yet used in CC-001. When needed by CC-003/004: introduce workflow-stage `offices_department_review` reachable by `admin` role (no new RBAC role) | Add `offices_department` role in `App\Models\User` roles enum |
| UNQ-017 quota APIs internal/external | Not yet used. When needed by CC-003: internal Laravel routes | Point at external system in a separate provider |
| UNQ-018 Second Auditor identity | Not yet used. When needed by CC-004: `second_auditor_review` stage on services that have it; generic "next reviewer" otherwise | Explicit routing per service if needed |
| UNQ-019 clearance issuance | Not yet used. When needed by CC-002: applicant uploads PDFs (off-platform issuance) | Build on-platform issuance service later |
| UNQ-020 scope | CC-001+002 → services with cadastral fields (detected via schema.fields containing basin_number). CC-003 → services with quota inputs. CC-004 → universal | Guard's own `isInScope($app)` method controls activation per-guard |

## CC-001 CadastralConflictGuard — logic

```
inScope(app) := schema.fields contains {basin_number, parcel_number, basin_or_location_name}
                AND all three values are present in app.data

if !inScope:      return []      // pass-through
values := normalize(app.data.basin_number, app.data.parcel_number, app.data.basin_or_location_name)
conflict := exists Application where
              organization_id != app.organization_id
              AND normalized(data.basin_number) == values[0]
              AND normalized(data.parcel_number) == values[1]
              AND normalized(data.basin_or_location_name) == values[2]
              AND status IN {SUBMITTED, UNDER_REVIEW, MODIFICATIONS_REQUESTED, APPROVED, CERTIFICATE_ISSUED}
              // draft + rejected excluded — drafts are not committed; rejected doesn't own the parcel

if conflict:      return [
                    'basin_number' => 'هذه القطعة/الحوض مسجَّلة سابقاً لمكتب هندسي آخر. لا يمكن التقديم قبل تسوية الوضع.'
                  ]
else:             return []
```

## What CC-001 does NOT do (deferred to CC-002)

- Owner-name matching. When the cadastral triple matches AND `contract_owner_name` also matches, CC-002 will require clearance + discharge from the previous office. CC-001 alone rejects on the triple regardless of owner.

## Testing shape

Tests exercise both scopes and behaviors:

- In-scope service (SRV-001) + no conflict → pass (`[]`)
- In-scope service (SRV-001) + conflict from a different org's `SUBMITTED` application → error
- In-scope service (SRV-001) + conflict from a different org's `DRAFT` application → pass (drafts don't own)
- In-scope service (SRV-001) + conflict from a different org's `REJECTED` application → pass
- In-scope service (SRV-001) + same-org conflict → pass (own drafts / submissions don't self-block)
- Out-of-scope service (e.g. CERT-001 without cadastral fields) → pass regardless
- Arabic normalization: `الحوض ٧` vs `الحوض 7` should match if we normalize digits (deferred; current normalization is text-only). Whitespace: `  إربد  ` matches `إربد`. Alef variants: `أحمد` matches `احمد`.

## Follow-on batches

- CC-002 (OwnerMatchClearanceGuard) — extends CC-001; adds conditional-document requirements.
- CC-003 (QuotaRoutingGuard) — replaces `CapacityGuard` 422 with an Offices-Department routing workflow; new HTTP endpoints.
- CC-004 (typed notes) — extends `ApplicationReview` model with `severity` column + WorkflowEngine routing.
