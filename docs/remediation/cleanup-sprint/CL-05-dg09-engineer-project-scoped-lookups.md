# CL-05 · DG-09 Engineer / Project scoped lookups

## What the audit said

Audit group **DG-09** in `/tmp/esp-v2-duplicate-code-inventory.csv`:

* Type: STRUCTURAL
* Sites: `EngineerController::show/quota` + `ProjectController::show` +
  `OfficeSettingsController::updateEngineer/show`
* Similarity: 0.90
* Recommendation: CONSOLIDATE (extend the DG-01 helper with a scope-column name)

## Close-inspection finding

The DG-09 sites use `office_user_id` (Engineer) or `owner_user_id`
(Project) — **JORD-77 office-owner scoping**, not tenant scoping.

| FILE:LINE | MODEL | SCOPE COLUMN | SCOPE VALUE | AUTHORIZATION INVARIANT |
|---|---|---|---|---|
| EngineerController::show:67-68 | Engineer | `office_user_id` | `$request->user()->id` | office-owner (an office may only read its own engineers) |
| EngineerController::quota:82-83 | Engineer | `office_user_id` | `$request->user()->id` | same |
| ProjectController::show:108-110 | Project | `owner_user_id` | `$request->user()->id` | project-owner |
| OfficeSettingsController::updateEngineer:103-104 | Engineer | `office_user_id` | `$office->id` (admin acting on the office) | admin cross-office (different actor identity) |
| OfficeSettingsController::show:117-119 | User | `organization_id` | `$request->user()->organization_id` | tenant + admin — CL-06 (User doesn't use trait) |

The tenant-scope CL-04 helper handles ONE column: `organization_id`.
DG-09 sites use TWO different scope columns AND two different scope
values (self vs admin-on-behalf-of-office). Consolidating them under
one column-parameterised helper would:

1. Erase the tenant vs owner distinction — a future audit could not
   tell whether a site's scope is a **tenancy** guarantee (H-01) or
   an **ownership** guarantee (JORD-77).
2. Push toward the "overly generic helper" pattern the audit explicitly
   warned against — a `findByColumnOrFail(string $col, mixed $val, mixed $id)`
   loses static analyser guarantees about *which* column enforces
   authorization.
3. Break the defence-in-depth story: the CL-04 helper composes with
   the global `OrganizationScope`, doubling up the tenant filter.
   An office-owner helper has no comparable global scope to compose
   with — the `where('office_user_id', $x)` is the ONLY filter, so
   the caller needs to see it inline.

## Decision

**`SUPERSEDED_TO_KEEP_SEPARATE_WITH_EVIDENCE`** — per the sprint
mandate's own escape hatch: *"If closer inspection shows a group
should remain separate, do not force consolidation."*

The Engineer / Project ownership scoping stays inline. It reads
correctly at every site because the scope column and scope value
are literally visible on the query builder line.

## Changes

**None.** No code change; no test change.

## What is protected

* Ownership authorization stays visible at every site (grep for
  `office_user_id` still surfaces exactly the places where
  office-owner scoping applies).
* The distinction between H-01 (tenant fail-closed) and JORD-77
  (office-owner scoping) is preserved.
* PHPStan continues to see the exact `where(col, val)` filter for
  each callsite.

## What is NOT protected

The five DG-09 sites still duplicate the two-line pattern:

```php
$engineer = Engineer::where('office_user_id', $request->user()->id)->findOrFail($id);
```

This is documented tolerated duplication. If a **sixth** site appears
or the office-owner scoping ever grows a second invariant that would
be forgotten inline, revisit with a domain-typed helper such as:

```php
Engineer::findForOfficeOwnerOrFail(int $officeUserId, int|string $id): self
```

(named, not column-parameterised — keep the invariant in the name).

## Verification

No code change → no test change. Sanity: full suite already passes
at 907/903 from CL-04.

## Report fields

```
DECISION_ID=CL-05
AUDIT_ID=DG-09
CLASSIFICATION=STRUCTURAL (audit); SUPERSEDED_TO_KEEP_SEPARATE_WITH_EVIDENCE (post-CL-05)
ACTION_TAKEN=none (record decision)
FILES_UPDATED=none
TESTS_ADDED=none
FOCUSED_TEST_RESULT=NOT_APPLICABLE (no code change)
CONTAINING_SUITE_RESULT=NOT_APPLICABLE (already green at CL-04 baseline)
STATIC_ANALYSIS_RESULT=NOT_APPLICABLE
RESIDUAL_RISK=Tolerated inline duplication (5 sites). Documented above.
COMMIT=<recorded post-commit in ledger>
```
