# CS-04 · Complete or remove `ApplicationLookup`

## Defect at start of sprint

`App\Contracts\Applications\ApplicationLookup` +
`App\Contracts\Applications\ApplicationSnapshot` +
`Modules\JeaServices\Services\EloquentApplicationLookup` all existed
and were wired into the container by
`JeaServicesServiceProvider::register`. But **zero production code
consumed them**. The only references were:

* the binding registration itself;
* prose "Retirement:" notes inside `SM_ALLOWED_IMPORTS` in
  `SiblingModuleBoundariesTest`;
* the impl file itself.

Meanwhile, `SM_ALLOWED_IMPORTS` had 15 entries — every sibling that
needed to read a JEA `Application` still imported the Eloquent model
directly. The contract was ready but nobody used it.

## Decision

**Adopt the contract, don't delete it.** Its shape (an
`ApplicationSnapshot` DTO carrying id, reference_number, org id,
service_definition_id, applicant_id, status, current_stage,
fee_amount, cadastral fields, data blob) fits pure-read consumers
without extension. What was missing was any first consumer to prove
the abstraction and lower the bar for the next migration.

The best-fit first consumer is `SanctionGuard::validate()` — it
touches exactly one Application field (`applicant_id`) to look up
active sanctions. Migrating it exercises the contract on a
production-critical path (application submit) and drops one entry
from `SM_ALLOWED_IMPORTS` with zero surface change to callers.

Callers that need Eloquent relations (`QuotaLedger`, `CapacityGuard`,
`LegalFineController`, `SupervisionTransferService`,
`RemindExpiries`) or FK belongsTo relations (`LegalFine`,
`SupervisionTransfer` models) need bigger contract extensions or
architectural moves. Those are explicit CS-05 backlog items and are
documented as such.

## What changed

* `SanctionGuard::validate(Application)` → `validate(ApplicationSnapshot)`.
  The `use Modules\JeaServices\Models\Application` import is gone;
  the file no longer sits in `SM_ALLOWED_IMPORTS`.
* `EloquentApplicationLookup` gains a public `snapshotOf(Application)`
  static helper so callers that already hold the Eloquent model
  (like `ApplicationController::submit`) can build a snapshot without
  a second DB round-trip via `find()`.
* `ApplicationController::submit` calls
  `EloquentApplicationLookup::snapshotOf($app)` before invoking the
  sanction guard.
* `ComplaintsAndSanctionsTest` — the two direct calls to
  `SanctionGuard::validate($app)` now pass a snapshot built via the
  same helper.
* `SiblingModuleBoundariesTest::SM_ALLOWED_IMPORTS` — SanctionGuard
  entry replaced with a retirement note pointing at CS-04 so the
  stale-entry watchdog does not flag it.
* `Modules\JeaServices\Models\Application` — one added
  `@property int|null $service_definition_id` docblock entry so the
  `snapshotOf` static-analyser view is complete (behaviour unchanged).
* `Modules\JeaDiscipline\Models\Sanction` — added `@property`
  annotations for the columns SanctionGuard reads
  (`kind`, `effective_from`, `effective_until`, etc.). Behaviour
  unchanged; addresses a small pre-existing PHPStan gap in the
  file that CS-04 modifies.

## New tests

`tests/Feature/Contracts/ApplicationLookupContractTest.php` (5 tests):

* container resolves `ApplicationLookup` to `EloquentApplicationLookup`;
* `snapshotOf` maps every DTO field including basin/parcel numbers
  hoisted from `data`;
* `find()` returns null for a missing id;
* `find()` is org-scope-agnostic by design (the payment-callback
  path is not signed in, so contract must be able to reach any
  application by id);
* `forOrganizationInStatuses()` respects the org boundary + status
  whitelist.

## Verification

### Focused tests

```
$ php artisan test tests/Feature/Contracts/ApplicationLookupContractTest.php
{"tool":"phpunit","result":"passed","tests":5,"passed":5,"assertions":16,"duration_ms":228}
```

### Containing suites

```
$ php artisan test tests/Feature/ComplaintsAndSanctionsTest.php tests/Architecture tests/Feature/Contracts
(all pass)
```

### Full backend suite

```
$ php artisan test
{"tool":"phpunit","result":"passed","tests":895,"passed":891,"assertions":2974,"duration_ms":31586,"skipped":4}
```

5 net-new tests over the CS-03 baseline of 890. Zero regressions.

### PHPStan

```
$ vendor/bin/phpstan analyse \
    modules/JeaServices/Services/EloquentApplicationLookup.php \
    tests/Feature/Contracts/ApplicationLookupContractTest.php
{"tool":"phpstan","result":"passed","errors":0}
```

`SanctionGuard.php` cleared with the Sanction `@property` annotations
added by CS-04. `ApplicationController.php` retains 7 pre-existing
`property.notFound` errors on `Project::$contract_no` and
`ApplicationDocument::$disk/$path/…` — those lines are outside the
CS-04 diff (line 189 + 412-421 vs my edit at 313-329) and belong to
the ApplicationDocument residual backlog.

### Boundary count

Before CS-04: 15 entries in `SM_ALLOWED_IMPORTS`.
After CS-04:  14 entries (SanctionGuard removed).

The other 14 items are the CS-05 sibling-coupling backlog.

## Report fields

```
ITEM_ID=CS-04
ORIGINAL_FINDING=NEW-A3 (ApplicationLookup contract had zero production consumers; 15 sibling files still imported Modules\JeaServices\Models\Application directly)
START_HEAD=89bfc40
END_HEAD=3f7883a00f75c284b3be7f5ed98e42643be9780c
STATUS=FIXED (adopted — contract now has a production consumer)
ROOT_CAUSE=The contract was added as scaffolding for a future migration but nothing consumed it. The retirement notes in SM_ALLOWED_IMPORTS were prose without a first mover.
IMPLEMENTATION_DECISION=Adopt the contract via SanctionGuard — the sibling consumer whose Application-field footprint (applicant_id only) fits ApplicationSnapshot exactly and whose call site is production-critical (application submit path). Add a public static snapshotOf() helper so callers already holding the Eloquent model don't need a second DB round-trip. Explicitly leave the 6 backlog items (QuotaLedger, CapacityGuard, LegalFineController, LegalFine.php, SupervisionTransfer.php, SupervisionTransferService, RemindExpiries) as CS-05 sibling-coupling work because each needs richer contract extensions or an architectural move (FK relations, whereHas, etc.) that would balloon CS-04 scope.
FILES_CHANGED=backend/modules/JeaDiscipline/Engine/SanctionGuard.php; backend/modules/JeaServices/Services/EloquentApplicationLookup.php; backend/modules/JeaServices/Http/Controllers/ApplicationController.php; backend/modules/JeaServices/Models/Application.php; backend/modules/JeaDiscipline/Models/Sanction.php; backend/tests/Architecture/SiblingModuleBoundariesTest.php; backend/tests/Feature/ComplaintsAndSanctionsTest.php
MIGRATIONS_ADDED=none
TESTS_ADDED=tests/Feature/Contracts/ApplicationLookupContractTest.php (5 tests: container binding, snapshotOf field mapping, find() nullability, find() cross-org policy, forOrganizationInStatuses scope + status filter)
TESTS_MODIFIED=tests/Feature/ComplaintsAndSanctionsTest.php (2 direct SanctionGuard::validate call sites now build a snapshot via EloquentApplicationLookup::snapshotOf); tests/Architecture/SiblingModuleBoundariesTest.php (SanctionGuard entry retired from SM_ALLOWED_IMPORTS)
FOCUSED_TEST_RESULT=PASS (5 tests / 16 assertions)
CONTAINING_SUITE_RESULT=PASS (Architecture + Feature/Contracts + Feature/ComplaintsAndSanctionsTest all green)
STATIC_ANALYSIS_RESULT=PASS on CS-04-touched code (SanctionGuard + EloquentApplicationLookup + contract tests). ApplicationController retains 7 pre-existing property.notFound errors outside the CS-04 diff — logged in residual backlog.
RUNTIME_VERIFICATION=ApplicationController::submit calls the contract via a static-helper snapshot; the sanction gate rejects a submitting user under a blocking sanction identically to the pre-CS-04 flow. Full suite 895 tests / 891 passed / 4 skipped confirms no behavioural regression.
RESIDUAL_RISK=Only 1 of 15 SM_ALLOWED_IMPORTS entries retired. The remaining 14 are CS-05 backlog. Contract still lacks a `findByPaymentReference(string): ?ApplicationSnapshot` method that a future Platform-side PaymentCallbackController could use; CS-03 kept its callback in JEA precisely to avoid extending the contract mid-sprint. That extension is in the CS-05 shortlist.
EXTERNAL_BLOCKER=none
COMMIT=3f7883a
NEXT_ITEM=CS-05
```

## Acceptance criteria

```
APPLICATION_LOOKUP_PRODUCTION_CONSUMERS=1 (>0)   SanctionGuard adoption
UNUSED_CONTRACT_FILES=0                          Contract + Snapshot + Impl all now consumed
APPLICATION_LOOKUP_REMOVED=NO                    Adopted, not removed — per user's rule.
REPLACEMENT_CONTRACT_DEFINED=N/A                 Contract kept.
```
