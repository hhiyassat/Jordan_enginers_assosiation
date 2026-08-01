# CL-06 · DG-13 User-management scoped lookups

## What the audit said

Audit group **DG-13** in `/tmp/esp-v2-duplicate-code-inventory.csv`:

* Type: STRUCTURAL
* Sites: UserManagementController + EngineerController + ProjectController
* Recommendation: CONSOLIDATE — "same shared helper suggested in DG-01"

DG-13 is a **superset** of DG-01 (Application sites) and DG-09
(Engineer + Project office-owner sites). The audit intended one
helper to cover all three groups.

## What was already reconciled

* **DG-01 Application sites** → CL-04 introduced
  `BelongsToOrganization::findForOrganizationOrFail(...)` and
  converted all 7 sites. Reference: commit `bb748fb`.
* **DG-09 Engineer + Project sites** → CL-05 recorded
  `SUPERSEDED_TO_KEEP_SEPARATE_WITH_EVIDENCE` because the sites use
  `office_user_id` / `owner_user_id` (JORD-77 office-owner scope),
  NOT `organization_id`, and the two invariants must stay
  visually distinct. Reference: commit `fcdb7a0`.

## DG-13 residual — 2 User sites

Two remaining sites use `User::where('organization_id', ...)->findOrFail(...)`:

| FILE:LINE | SYMBOL | MODEL | AUTHZ | FAILURE | QUERY |
|---|---|---|---|---|---|
| UserManagementController.php:128 | `show` | User | `canManageUsers()` guard at controller entry + admin/superuser role via middleware | 404 | `User::where('organization_id', $orgId)->findOrFail($id)` |
| UserManagementController.php:194 | `destroy` | User | `canManageUsers()` + tier check + not-self guard | 404 | same |

## Why the CL-04 helper cannot cover these

The `User` model **deliberately does not** use the
`BelongsToOrganization` trait. Traits list:

```
use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
```

`User` is the auth entity. Adding the trait would:

1. Register the global `OrganizationScope` on **User** — every
   `User::find($authId)` during authentication would be filtered by
   `Auth::user()->organization_id`, but at authentication time
   there is no `Auth::user()` yet, creating a chicken-and-egg boot
   problem.
2. Break the H-01 fail-closed pattern in a surprising place: an
   authenticated user with `organization_id = null` (edge case)
   would be unable to look up any user record — including themselves
   — because the scope would evaluate `whereRaw('1 = 0')` on User
   queries too.
3. Interfere with `UserManagementController`'s legitimate cross-user
   admin surface which already resolves the org scope explicitly at
   the callsite.

Adding the trait is an intentional non-goal.

## Decision

**`SUPERSEDED_TO_KEEP_SEPARATE_WITH_EVIDENCE`** for the two
UserManagementController sites, per the sprint mandate's escape
hatch.

Alternatives considered + rejected:

* **Extend the trait to User** — see reasons above.
* **Extract a User-specific helper** (e.g.
  `App\Support\UserLookup::findForOrganizationOrFail`) — this would
  be a one-off helper for two callsites. Pure boilerplate ratio
  would increase, not decrease. The `where('organization_id', ...)`
  pattern is already 2 lines and completely explicit.
* **Move to a `App\Support\ScopedLookup::findOrFail(Builder, int|string)`
  facade** — takes any pre-scoped query. Would technically apply,
  but the audit warned:

  > "Do not replace clear code with an overly generic helper such
  > as `findModelForOrganization(string $class, mixed $id)` unless
  > strict typing and authorization ownership are preserved."

  A Builder-first facade has the same problem — the authorization
  invariant lives outside the type system.

## Changes

**None.** No code change; no test change.

## Verification

No code change → no test change. Full suite already green at
907/903 from CL-04.

## Report fields

```
DECISION_ID=CL-06
AUDIT_ID=DG-13 (residual after CL-04 + CL-05 covered the intersection)
CLASSIFICATION=STRUCTURAL (audit); SUPERSEDED_TO_KEEP_SEPARATE_WITH_EVIDENCE (post-CL-06 for the User portion)
ACTION_TAKEN=none (record decision)
FILES_UPDATED=none
TESTS_ADDED=none
FOCUSED_TEST_RESULT=NOT_APPLICABLE (no code change)
CONTAINING_SUITE_RESULT=NOT_APPLICABLE (green at CL-04 baseline)
RESIDUAL_RISK=Two 1-line inline lookups on User model. Documented above.
COMMIT=<recorded post-commit in ledger>
```
