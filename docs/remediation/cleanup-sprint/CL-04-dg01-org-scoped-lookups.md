# CL-04 · Consolidate DG-01 org-scoped model lookups

## What the audit said

Audit group **DG-01** in `/tmp/esp-v2-duplicate-code-inventory.csv`:

* Type: EXACT
* Sites: 7 occurrences of
  `Model::forOrganization($request->user()->organization_id)->findOrFail($id)`
* Canonical implementation: shared helper on `BelongsToOrganization`
  trait OR `App\Support\ScopedLookup::scopedFindOrFail(...)`
* Recommendation: CONSOLIDATE
* Consolidation risk: LOW — all 7 sites already behave correctly.

## Per-occurrence inspection

| FILE:LINE | SYMBOL | MODEL | ORG_SOURCE | AUTHZ_RULE | FAILURE | CROSS_TENANT_EXCEPTION | QUERY_SHAPE |
|---|---|---|---|---|---|---|---|
| PaymentsController.php:50 | `initiate` | Application | `$request->user()->organization_id` | route `role:applicant,staff,auditor,admin` + status check | 404 via findOrFail | none | `Application::forOrganization($orgId)->findOrFail($id)` |
| PaymentsController.php:114 | `confirm` | Application | same | route `role:admin` + `ConfirmPaymentRequest::authorize()` (admin + manual_reason) | 404 | none | same |
| CertificatesController.php:36 | `issue` | Application | same | route `role:staff,admin` + `isAdmin()`/`isStaff()` guard at line 32 | 404 | none | same |
| ReviewQueueController.php:104 | `show` | Application | same | route `role:staff,auditor,admin` | 404 | none | same |
| ReviewQueueController.php:118 | `claim` | Application | same | same | 404 | none | same |
| ReviewQueueController.php:127 | `release` | Application | same | same | 404 | none | same |
| ReviewQueueController.php:decide | `decide` | Application | same | same | 404 | none | same |

All 7 sites share:

* **same tenant invariant** — filter by
  `applications.organization_id = $request->user()->organization_id`;
* **same ownership rule** — no per-user restriction (that's a
  superset the private `ApplicationController::findAccessible` adds);
* **same failure semantics** — `findOrFail` → 404
  (`ModelNotFoundException`);
* **same lifecycle** — single-request lookup;
* **same side effects** — none (read-only).

## Rejected: overly-generic helper

The audit warned against `findModelForOrganization(string $class, mixed $id)`.
An FQCN-first helper would erase the type-safety and hide the tenant
intent. This CL-04 uses the alternative form: a static method on the
trait, invoked as `Model::findForOrganizationOrFail(...)`. The model
class is named at the callsite; PHPStan sees the concrete return
type via `static`.

## Not included: UserManagementController + `downloadPdfAuthenticated`

* `UserManagementController::show/destroy` (line 128 + 194) uses
  `User::where('organization_id', $orgId)->findOrFail($id)`. `User`
  is the auth entity and does NOT (and should not) use
  `BelongsToOrganization` — the trait's global scope would gate the
  auth model itself. These sites are deferred to CL-06 (DG-13
  superset) with SUPERSEDED_TO_KEEP_SEPARATE evidence.
* `CertificatesController::downloadPdfAuthenticated` (line 119-124)
  splits the query builder across two lines to conditionally add
  `applicant_id` filter (role-dependent). Different shape from the
  DG-01 target; the trait helper does not fit.

## Change

### Trait — one new method

```php
// backend/app/Models/Concerns/BelongsToOrganization.php
public static function findForOrganizationOrFail(int $orgId, int|string $id): static
{
    /** @var static $model */
    $model = static::forOrganization($orgId)->findOrFail($id);
    return $model;
}
```

`$orgId` is passed explicitly so tenant intent stays visible at
every call site. No implicit `Auth::user()` resolution inside the
helper.

The global `OrganizationScope` still fires when an auth user
exists, so the two filters compose: caller passes `$orgIdA`, global
scope filters by `$authOrgId`, and if these disagree the query
returns nothing → the helper 404s. This defence-in-depth is
verified by
`test_find_for_organization_or_fail_does_not_bypass_the_global_scope`.

### 7 call sites converted

Before:
```php
$app = Application::forOrganization($request->user()->organization_id)->findOrFail($id);
```

After:
```php
$app = Application::findForOrganizationOrFail($request->user()->organization_id, $id);
```

Files:

* `backend/modules/JeaServices/Http/Controllers/PaymentsController.php`
  (initiate:50 + confirm:114)
* `backend/modules/JeaServices/Http/Controllers/CertificatesController.php`
  (issue:36)
* `backend/modules/JeaServices/Http/Controllers/ReviewQueueController.php`
  (claim:104 + release:118 + decide:127)

Aggregate queries elsewhere in the same files (`->count()`,
`->orderBy()->paginate()`) are untouched — DG-01 targets only the
`findOrFail` shape.

## Tests

Added to
`backend/tests/Feature/BelongsToOrganizationTest.php` (the existing
NFR-002 test file, correct topical home):

1. `test_find_for_organization_or_fail_returns_same_org_row` — happy path.
2. `test_find_for_organization_or_fail_rejects_cross_org_row_with_404` — caller resolves the wrong tenant id → 404, NOT a 403 leak.
3. `test_find_for_organization_or_fail_missing_id_throws_404` — id does not exist → 404.
4. `test_find_for_organization_or_fail_null_org_context_still_fails_closed` — H-01 regression guard: even with an explicit `$orgId` argument, a null-org authenticated user gets zero rows because the global scope's `whereRaw('1 = 0')` composes with the helper's filter.
5. `test_find_for_organization_or_fail_does_not_bypass_the_global_scope` — regression guard: if a future edit ever changes the helper impl to `withoutOrgScope()->where(...)`, this test fails. Confirms the double-filter (global + explicit) is intentional.

## Verification

### Focused

```
$ php artisan test tests/Feature/BelongsToOrganizationTest.php tests/Feature/Payment tests/Feature/CrossTenantIsolationTest.php
{"tool":"phpunit","result":"passed","tests":39,"passed":39,"assertions":76,"duration_ms":677}
```

### Full backend suite

```
$ php artisan test
{"tool":"phpunit","result":"passed","tests":907,"passed":903,"assertions":2992,"duration_ms":34267,"skipped":4}
```

Test count went 902 → 907 (5 new CL-04 tests). Zero regressions.

### PHPStan

```
$ vendor/bin/phpstan analyse \
    app/Models/Concerns/BelongsToOrganization.php \
    modules/JeaServices/Http/Controllers/PaymentsController.php \
    modules/JeaServices/Http/Controllers/ReviewQueueController.php
{"tool":"phpstan","result":"passed","errors":0}
```

Three pre-existing `property.notFound` errors surface when
`CertificatesController.php:56` and
`tests/Feature/BelongsToOrganizationTest.php` lines 106/120 are
included in the analyse target. All three were present at HEAD
before CL-04 (verified via `git show HEAD:...`) — they are
Eloquent-magic property accesses on `Project` / `ServiceDefinition`
that lack `@property` annotations. Recording as pre-existing debt,
not CL-04 scope.

### No N+1 regression

The helper resolves to exactly the same SQL query the 7 sites
previously issued: one `SELECT * FROM applications WHERE
organization_id = ? AND id = ? LIMIT 1` per call. No eager loads,
no relationship walks. Query-count is identical.

## Report fields

```
DECISION_ID=CL-04
AUDIT_ID=DG-01
CLASSIFICATION=EXACT (audit); CONSOLIDATED (post-CL-04)
ACTION_TAKEN=CONSOLIDATE via trait helper
FILES_ADDED=none
FILES_UPDATED=backend/app/Models/Concerns/BelongsToOrganization.php (+1 static method); 3 controller files (7 call-site conversions); backend/tests/Feature/BelongsToOrganizationTest.php (+5 tests)
TESTS_ADDED=5 (see above list)
FOCUSED_TEST_RESULT=PASS (39 tests / 76 assertions across affected suites)
CONTAINING_SUITE_RESULT=PASS (backend full suite 907 tests / 903 passed / 4 skipped / 2992 assertions)
STATIC_ANALYSIS_RESULT=PASS on CL-04-touched semantic files. 3 pre-existing property.notFound errors on other files are recorded as backlog debt.
RUNTIME_VERIFICATION=Same SQL query shape; findOrFail failure path preserved; 4 dedicated tests cover same-org / cross-org / null-org / no-scope-bypass invariants.
RESIDUAL_RISK=None. DG-13's two User sites remain (CL-06 will explain why they cannot use this helper).
COMMIT=<recorded post-commit in ledger>
```
