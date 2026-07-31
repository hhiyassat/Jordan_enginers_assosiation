# CS-01 · Fix NEW-A1 production boot blocker

## Summary

`App\Support\ProductionSafety::checkNashmiSigningSecret` read the config
value from the wrong key. The check called
`config('integrations.nashmi.signing_secret', '')` but the actual Nashmi
configuration lives at `backend/config/nashmi.php`, which Laravel exposes
as `config('nashmi.*')`. The file `backend/config/integrations.php` is a
registry of enabled integrations (each key is an integration id → its
provider class); it has no `signing_secret` field and never did.

Because `config('integrations.nashmi.signing_secret', '')` therefore
always resolved to the empty-string default, the check appended a
violation on every production boot regardless of whether
`NASHMI_SIGNING_SECRET` was set in the environment. Any real production
deploy would abort at boot with the message
`nashmi.signing_secret is empty; production must set NASHMI_SIGNING_SECRET`,
even with a perfectly valid secret in place.

The pre-existing test (`test_missing_nashmi_signing_secret_is_a_violation`)
appeared to prove the check worked, but was itself using the wrong key —
so it was testing nothing beyond "an unset key reads as empty."

## What changed

* `backend/app/Support/ProductionSafety.php` — one-line fix from
  `config('integrations.nashmi.signing_secret', '')` to
  `config('nashmi.signing_secret', '')`, with a comment explaining why
  the legacy key was wrong so nobody re-introduces it. The violation
  message was updated to match the correct key.
* `backend/tests/Unit/Support/ProductionSafetyTest.php` — the existing
  test was corrected to set the canonical key. Three additional tests
  were added:
  * empty-string secret rejected;
  * populated secret accepted (positive case);
  * a regression guard that sets the wrong legacy key *only* and
    verifies the validator still complains — so nobody can silently
    re-introduce a shadow config path;
  * `test_all_safe_production_settings_produce_zero_violations` — an
    end-to-end regression that binds a real payment gateway + real JEA
    verifier, sets every safe production value on config, and asserts
    that `collectViolations()` returns `[]`. This is the missing
    "valid config really boots" test called out in the CS-01 acceptance
    criteria.

## Search for other stale `integrations.nashmi.*` references

```
$ grep -RIn 'integrations\.nashmi' backend
```

After the fix, the only remaining match is inside the regression-guard
test (which intentionally sets the wrong key to prove it does not
silence the real check). Zero production-code references remain.

## Verification

Focused test: `php artisan test --filter=ProductionSafetyTest`

```
{"tool":"phpunit","result":"passed","tests":22,"passed":22,"assertions":23,"duration_ms":196}
```

Containing suite: `php artisan test tests/Unit/Support`

```
{"tool":"phpunit","result":"passed","tests":22,"passed":22,"assertions":23,"duration_ms":184}
```

PHPStan on the changed source file:
`vendor/bin/phpstan analyse app/Support/ProductionSafety.php`

```
{"tool":"phpstan","result":"passed","errors":0}
```

PHPStan on the test file surfaces three pre-existing style findings
(`assertNull()` on a void method; two `assertTrue(true)` calls inside
helper methods that intentionally count a pass on match). These already
existed at `HEAD` (`a4224fc`) and are unrelated to this fix. They are
recorded in the residual backlog rather than expanded into scope.

## Report fields

```
ITEM_ID=CS-01
ORIGINAL_FINDING=NEW-A1 (config key typo aborts every production boot)
START_HEAD=a4224fcceff08a73d7c348b4a3324417fe66a413
END_HEAD=<recorded after commit — see ledger>
STATUS=FIXED
ROOT_CAUSE=Validator read a non-existent nested config path (`integrations.nashmi.signing_secret`) instead of the actual key (`nashmi.signing_secret`). The empty-string default fell through, so the check appended a violation regardless of the real env value.
IMPLEMENTATION_DECISION=Correct the config key and its error message; update the pre-existing miswritten test; add positive + shadow-key regression tests + an end-to-end "safe production boots" test.
FILES_CHANGED=backend/app/Support/ProductionSafety.php; backend/tests/Unit/Support/ProductionSafetyTest.php
MIGRATIONS_ADDED=none
TESTS_ADDED=test_empty_string_nashmi_signing_secret_is_a_violation; test_populated_nashmi_signing_secret_is_ok; test_wrong_legacy_key_does_not_shadow_correct_one; test_all_safe_production_settings_produce_zero_violations
TESTS_MODIFIED=test_missing_nashmi_signing_secret_is_a_violation (corrected key + assertion string)
FOCUSED_TEST_RESULT=PASS (22 tests / 23 assertions)
CONTAINING_SUITE_RESULT=PASS (22 tests / 23 assertions, tests/Unit/Support)
STATIC_ANALYSIS_RESULT=PASS (PHPStan on ProductionSafety.php — 0 errors). Test-file has 3 pre-existing style findings unrelated to CS-01.
RUNTIME_VERIFICATION=The added `test_all_safe_production_settings_produce_zero_violations` exercises the full `collectViolations()` path against a production-shaped config and asserts zero violations. Without the fix this test would fail because the Nashmi check appends a violation regardless of the secret being present.
RESIDUAL_RISK=Low. The other 12 ProductionSafety checks were re-inspected during this change and each still reads its own dedicated top-level config namespace (`filesystems.default`, `queue.default`, `cache.default`, `session.*`, `app.debug`, `sanctum.expiration`, `gsb.allowed_ips`, `esp.*`). None depends on a similarly-mistaken sub-tree lookup.
EXTERNAL_BLOCKER=none
COMMIT=<recorded after commit — see ledger>
NEXT_ITEM=CS-02
```

## Acceptance criteria

```
CORRECT_NASHMI_CONFIG_KEY_USED=YES
MISSING_SECRET_REJECTED=YES
VALID_SECRET_ACCEPTED=YES
SAFE_PRODUCTION_BOOT_TEST=PASS
```
