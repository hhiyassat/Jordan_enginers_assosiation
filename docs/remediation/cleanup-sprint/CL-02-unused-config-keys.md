# CL-02 · Retire four dead `esp.*` configuration keys

## What the audit said

Audit ID **U-03** in `/tmp/esp-v2-unused-code-inventory.csv`:

* Location: `backend/config/esp.php:31-38`
* Dead keys: `default_sla_hours`, `max_upload_size_mb`, `rate_limit_login`, `rate_limit_api`
* Classification: `DEAD_CONFIRMED`
* Recommendation: DELETE (after deprecation window)

## What we found

The four keys **do not exist** as active `return`-array entries in
`backend/config/esp.php` — they exist only as a P2 cleanup comment on
lines 31-38 documenting a prior removal. The runtime cleanup was
already done by an earlier sprint; only institutional-memory
documentation remained.

Verified via reference sweep across production + tests + env + deployment:

```
$ grep -RIn "default_sla_hours\|max_upload_size_mb\|rate_limit_login\|rate_limit_api" \
    backend frontend deployment
backend/config/esp.php:32    (P2 cleanup comment)
backend/config/esp.php:33    (P2 cleanup comment)
backend/config/esp.php:36    (P2 cleanup comment)
```

Field-by-field:

| Check | Result |
|---|---|
| CONFIG_READERS | 0 |
| DIRECT_ENV_READERS | 0 |
| TEST_REFERENCES | 0 |
| DOCUMENTATION_REQUIREMENT | 0 |
| DEPLOYMENT_TEMPLATE_REFERENCE | 0 |

## Classification

`SUPERSEDED_WITH_EVIDENCE`. The audit's cleanup target was met before
this sprint began. There are no keys to remove.

## Changes

* **Updated:** the P2 cleanup comment block on `backend/config/esp.php:31-38`
  to explicitly mark the retirement window as closed (post-CL-02
  verified zero references) and to instruct future contributors NOT
  to re-add these keys. This preserves the institutional memory
  (why aren't these keys here?) while making it clear the
  deprecation is definitive.
* **NOT changed:** `.env.example`, deployment templates,
  documentation, tests — none reference the retired keys.

## Verification

```
$ php artisan test tests/Unit/Support/ProductionSafetyTest.php
{"tool":"phpunit","result":"passed","tests":22,"passed":22,"assertions":23,"duration_ms":183}

$ vendor/bin/phpstan analyse config/esp.php --memory-limit=1G
{"tool":"phpstan","result":"passed","errors":0}

$ php artisan test
{"tool":"phpunit","result":"passed","tests":902,"passed":898,"assertions":2985,"duration_ms":19529,"skipped":4}
```

Comment-only edit; behaviour unchanged. Test count and assertion
count identical to CL-01 baseline.

## Report fields

```
DECISION_ID=CL-02
AUDIT_ID=U-03
CLASSIFICATION=SUPERSEDED_WITH_EVIDENCE
ACTION_TAKEN=comment-tighten only (keys were already retired)
FILES_REMOVED=none (nothing to remove — keys already absent)
FILES_UPDATED=backend/config/esp.php (P2-cleanup-comment tightened; ties the retirement to CL-02)
TESTS_ADDED=none
TESTS_MODIFIED=none
FOCUSED_TEST_RESULT=PASS (ProductionSafety 22 tests / 23 assertions)
CONTAINING_SUITE_RESULT=PASS (backend full suite 902/898+4-skipped)
STATIC_ANALYSIS_RESULT=PASS (PHPStan on config/esp.php — 0 errors)
RUNTIME_VERIFICATION=comment-only change; no runtime behaviour altered
RESIDUAL_RISK=none
COMMIT=<recorded post-commit in ledger>
```
