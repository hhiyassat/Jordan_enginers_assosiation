# CS-06 · Add captcha to public office registration

## Defect at start of sprint

`POST /api/v1/office-registrations` (the anonymous JEA office-signup
endpoint) was rate-limited via `throttle:5,1` (5 requests/minute per
IP) but did NOT verify the existing `captcha` middleware alias. A
scripted attacker with a small IP pool could steadily grow the
pending-registrations queue without any bot deterrence.

The `Plugins\Captcha\Http\Middleware\VerifyCaptcha` middleware and
the `Plugins\Captcha\Services\CaptchaService` — one-time-use SVG
challenge with cache-backed answer expiring after
`esp.captcha_ttl_minutes` (default 5) — were already in the repo.
Nothing wired them up to the office-registration route.

## What changed

* `backend/modules/JeaServices/routes.php` — the office-registration
  submit route now runs the captcha middleware after the throttle:
  `->middleware(['throttle:5,1', 'captcha'])`. The alias is registered
  by `CaptchaServiceProvider::boot`; removing the captcha plugin
  makes the middleware a 500 rather than silently allowing spam,
  which is the correct fail-closed behaviour.
* Local dev + the rest of the test suite stay unaffected because
  `esp.captcha_enabled` defaults to `false`; the middleware
  short-circuits when the flag is off. Production keeps the
  ProductionSafety check that refuses to boot when
  `CAPTCHA_ENABLED=false`.

## New tests

`backend/tests/Feature/OfficeRegistrationCaptchaTest.php` (6 tests,
all with `config(['esp.captcha_enabled' => true])` in `setUp`):

1. `test_missing_captcha_rejected` — no `captcha_id`/`captcha_answer`
   → 422 with `captcha_failed: true`.
2. `test_invalid_captcha_answer_rejected` — valid challenge id but
   wrong answer → 422.
3. `test_expired_captcha_rejected` — cache entry never existed
   (simulating TTL expiry) → 422.
4. `test_replayed_captcha_rejected_after_first_use` — same
   challenge accepted once, rejected on replay. Proves one-time-use.
5. `test_valid_captcha_allows_submission` — well-formed captcha +
   valid payload → 201.
6. `test_production_safety_still_rejects_boot_without_captcha_enabled`
   — regression guard: even if a future contributor removes the
   captcha middleware, ProductionSafety still aborts production
   boot when `CAPTCHA_ENABLED=false`.

## Verification

### Focused tests

```
$ php artisan test tests/Feature/OfficeRegistrationCaptchaTest.php
{"tool":"phpunit","result":"passed","tests":6,"passed":6,"assertions":12,"duration_ms":300}
```

### Full backend suite

```
$ php artisan test
{"tool":"phpunit","result":"passed","tests":904,"passed":900,"assertions":3000,"duration_ms":31272,"skipped":4}
```

6 net-new tests over the CS-05 baseline of 898. Zero regressions.
The existing `tests/Feature/OfficeRegistrationSubmitTest.php` (16
tests) continues to pass because the middleware short-circuits when
`esp.captcha_enabled=false` — the default in the test env.

### PHPStan

```
$ vendor/bin/phpstan analyse tests/Feature/OfficeRegistrationCaptchaTest.php
{"tool":"phpstan","result":"passed","errors":0}
```

## Report fields

```
ITEM_ID=CS-06
ORIGINAL_FINDING=M-10 (public office-registration signup lacked captcha; only throttle:5,1)
START_HEAD=0f33728
END_HEAD=<recorded after commit — see ledger>
STATUS=FIXED
ROOT_CAUSE=The captcha plugin and middleware alias existed but the route wiring never added the middleware. A rate limit alone lets a small IP pool steadily grow the pending queue.
IMPLEMENTATION_DECISION=Add the existing `captcha` middleware alias to the office-registration submit route. Leave local + test-env behaviour unchanged via the pre-existing `esp.captcha_enabled` config flag (defaults to false; ProductionSafety refuses to boot production with it false). Six-test coverage: missing/invalid/expired/replayed/valid + a ProductionSafety regression guard.
FILES_CHANGED=backend/modules/JeaServices/routes.php
MIGRATIONS_ADDED=none
TESTS_ADDED=backend/tests/Feature/OfficeRegistrationCaptchaTest.php (6 tests: missing/invalid/expired/replayed/valid + ProductionSafety guard)
TESTS_MODIFIED=none (pre-existing OfficeRegistrationSubmitTest 16/16 still passes because captcha is disabled by default in the test env)
FOCUSED_TEST_RESULT=PASS (6 tests / 12 assertions)
CONTAINING_SUITE_RESULT=PASS (full backend suite — 904 tests / 900 passed / 4 skipped / 3000 assertions)
STATIC_ANALYSIS_RESULT=PASS (PHPStan clean on the new test after `@param array<string, mixed>` annotation)
RUNTIME_VERIFICATION=`test_replayed_captcha_rejected_after_first_use` proves the CaptchaService cache entry is dropped on any verify attempt — a captured answer cannot be reused even in the tight loop of two immediate submissions.
RESIDUAL_RISK=Captcha is single-request-per-challenge; a determined attacker can still burn compute solving unlimited fresh challenges. Rate limit + captcha in tandem raise the bar significantly but not to zero — CS-10 backlog notes that adding a proof-of-work challenge or hCaptcha/reCAPTCHA is a further deterrence tier if abuse is observed.
EXTERNAL_BLOCKER=none
COMMIT=<recorded after commit — see ledger>
NEXT_ITEM=CS-07
```

## Acceptance criteria

```
PUBLIC_OFFICE_REGISTRATION_CAPTCHA_REQUIRED=YES     (middleware wired; ProductionSafety enforces enable)
CAPTCHA_ONE_TIME_USE=YES                            (CaptchaService drops the cache entry on any verify)
CAPTCHA_REPLAY_REJECTED=YES                         (test_replayed_captcha_rejected_after_first_use)
```
