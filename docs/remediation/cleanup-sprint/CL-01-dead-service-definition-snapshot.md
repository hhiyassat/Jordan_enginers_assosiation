# CL-01 · Remove unused `ServiceDefinitionSnapshot` contract

## What the audit said

Audit ID **U-01** in `/tmp/esp-v2-unused-code-inventory.csv`:

* Path: `backend/app/Contracts/Services/ServiceDefinitionSnapshot.php`
* Classification: `DEAD_CONFIRMED`
* Confidence: HIGH
* Recommendation: DELETE
* Blast radius: none (no `use`, `new`, `app()`, or type-hint anywhere in production)

The DTO was originally created as the input to
`Integrations\Nashmi\Services\NashmiIntegrationService::pushService()`.
CS-10 (`aa3c019`) removed `pushService()` along with its two helper
methods and the `use App\Contracts\Services\ServiceDefinitionSnapshot`
import, leaving the DTO with zero consumers. This cleanup completes
that retirement.

## Zero-reference proof

Before deletion I re-ran the reference sweep:

```
$ grep -RIn "ServiceDefinitionSnapshot" backend frontend | grep -v "^Binary"
backend/app/Contracts/Services/ServiceDefinitionSnapshot.php:8    (the file itself)
backend/app/Contracts/Services/ServiceDefinitionSnapshot.php:20   (the file itself)
backend/tests/Architecture/BoundariesTest.php:197                 (stale doc-comment)
backend/integrations/Nashmi/Services/NashmiIntegrationService.php:18  (CS-10 comment describing the deleted pushService)
```

Field-by-field:

| Check | Result |
|---|---|
| STATIC_PRODUCTION_CALLERS | 0 |
| DYNAMIC_PRODUCTION_CALLERS | 0 |
| TEST_CALLERS | 0 |
| CONTAINER_BINDINGS | 0 |
| ROUTE_REFERENCES | 0 |
| CONFIG_REFERENCES | 0 |
| MIGRATION_DEPENDENCIES | 0 |
| PUBLIC_CONTRACT_DEPENDENCIES | 0 |

## Changes

* **Deleted:** `backend/app/Contracts/Services/ServiceDefinitionSnapshot.php`.
* **Updated:** `backend/tests/Architecture/BoundariesTest.php:190-198` — the
  neutral-contract explanation used to name both `ServiceLockLookup`
  and `ServiceDefinitionSnapshot`. The DTO reference is replaced with
  a one-line note pointing at CL-01 so a future reader who wonders "why
  is the sibling contract mentioned but missing?" has the answer.
* **Deliberately NOT touched:**
  * `backend/integrations/Nashmi/Services/NashmiIntegrationService.php:18` —
    the CS-10 doc-comment historically describes what was removed. It
    remains factually correct: "the older `pushService()` code-path —
    which pushed a `ServiceDefinitionSnapshot` as a new requirements
    project — was never wired to a route." Rewriting it would obscure
    the CS-10 audit trail.
  * `docs/remediation/closure-sprint/CS-10-remaining-medium-low-findings.md` —
    historical closure-sprint report. Immutable per repo convention.
  * `docs/handoffs/esp-v2-post-remediation-handoff.md` — user-owned
    untracked file.

## Adjacent-contract discipline

The audit explicitly forbids removing adjacent contracts unless
separately classified. `ServiceLockLookup` (audit U-12), which
lives in the same directory, is `ACTIVE_DYNAMIC` (consumed by
`AiSchemaController:829`) and remains. `ApplicationLookup` +
`ApplicationSnapshot` (U-09, U-10) similarly remain.

## Verification

```
$ php artisan test tests/Architecture
{"tool":"phpunit","result":"passed","tests":16,"passed":15,"assertions":27,"duration_ms":242,"skipped":1}

$ vendor/bin/phpstan analyse --memory-limit=1G
{"tool":"phpstan","result":"passed","errors":0}

$ php artisan test
{"tool":"phpunit","result":"passed","tests":902,"passed":898,"assertions":2985,"duration_ms":32848,"skipped":4}
```

Test count unchanged from the closure-sprint baseline. The
Architecture suite includes the boundary tests that governed the
deleted DTO; both `test_no_undocumented_cross_jea_module_imports`
and `test_platform_does_not_import_service_modules` still pass with
the reduced narrative comment.

## Report fields

```
DECISION_ID=CL-01
AUDIT_ID=U-01
CLASSIFICATION=DEAD_CONFIRMED
ACTION_TAKEN=DELETE
FILES_REMOVED=backend/app/Contracts/Services/ServiceDefinitionSnapshot.php
FILES_UPDATED=backend/tests/Architecture/BoundariesTest.php (stale doc-comment)
TESTS_ADDED=none (deletion has no new behaviour to test)
TESTS_MODIFIED=none (BoundariesTest doc-comment only)
FOCUSED_TEST_RESULT=PASS (Architecture suite 16 tests / 15 passed / 1 skipped)
CONTAINING_SUITE_RESULT=PASS (backend SQLite full suite 902/898)
STATIC_ANALYSIS_RESULT=PASS (PHPStan 0 errors)
RUNTIME_VERIFICATION=zero references remain in production code; full suite green
RESIDUAL_RISK=none
COMMIT=<recorded post-commit in ledger>
```
