# CS-07 · Require Nashmi nonce in production

## Defect at start of sprint

Nashmi HMAC signature verification and timestamp-replay window were
enforced, but `X-Nashmi-Nonce` (or `X-Integration-Nonce` /
`X-Request-Id`) was **optional**. If the caller omitted the header,
replay protection degraded to the 5-minute timestamp window — any
signed payload could be re-posted arbitrarily many times inside
that window, without a single-request-per-nonce guarantee.

The nonce store also used `Cache::has($k)` + `Cache::put($k)` —
non-atomic. Two concurrent workers submitting the same request
could both pass the `has()` check before either wrote, producing a
narrow-but-real race.

## What changed

`backend/integrations/Nashmi/Http/Middleware/ValidateIntegrationKey.php`
now:

* **Requires the nonce header in production.** If `APP_ENV=production`
  and no nonce header is present, the middleware returns
  `401 { message: 'Missing nonce header (required in production).' }`
  and emits `SecurityEvents::integrationSignatureFailure(...,
  'missing_nonce_in_production')`. Non-production callers may still
  omit it — the local dev / test fixtures don't have to be updated.
* **Uses `Cache::add()` for atomic storage.** `Cache::add()` is a
  put-if-missing that returns `true` when it set the key and `false`
  when a concurrent caller already claimed it. Two concurrent
  identical requests: exactly one wins, the loser receives the
  standard replay 401.
* **Namespaces the nonce key by signing-secret fingerprint.** The
  cache key is now
  `nashmi:nonce:{first-12-hex-of-sha256(secret)}:{sha256(nonce)}`,
  which means rotating `NASHMI_SIGNING_SECRET` invalidates the old
  nonce set — a nonce reused across a key rotation is treated as a
  fresh nonce, so the rotation does not silently accept prior
  replays.
* **Uses full sha256 hashes for the nonce component**, not `md5()`,
  so a malicious client cannot cheaply engineer nonce-key collisions
  against another caller.

## New tests

`backend/tests/Feature/NashmiNonceEnforcementTest.php` (5 tests):

1. `test_missing_nonce_rejected_in_production` — sets
   `APP_ENV=production` and asserts 401 with the missing-nonce
   message.
2. `test_missing_nonce_tolerated_outside_production` — signed request
   without a nonce still succeeds in `testing` env.
3. `test_replayed_nonce_rejected` — two sequential requests with the
   same nonce → 200 then 401.
4. `test_atomic_replay_protection_only_one_of_two_concurrent_wins` —
   pre-populates the exact cache key the middleware computes to
   simulate a race, asserts the second `Cache::add()` fails and the
   middleware returns 401. This test also fails if the key
   derivation drifts.
5. `test_key_rotation_invalidates_prior_nonces` — claims a nonce
   under secret A, rotates to secret B, replays the same nonce
   string under B → accepted (because the cache key namespace
   changed with the fingerprint).

## Verification

### Focused tests

```
$ php artisan test tests/Feature/NashmiNonceEnforcementTest.php tests/Feature/NashmiSecurityTest.php
{"tool":"phpunit","result":"passed","tests":9,"passed":9,"assertions":15,"duration_ms":224}
```

### Full backend suite

```
$ php artisan test
{"tool":"phpunit","result":"passed","tests":909,"passed":905,"assertions":3010,"duration_ms":31482,"skipped":4}
```

5 net-new tests over the CS-06 baseline of 904. Zero regressions —
the existing `NashmiSecurityTest::test_replay_nonce_rejection` still
passes with the new `Cache::add()` storage.

### PHPStan

```
$ vendor/bin/phpstan analyse integrations/Nashmi/Http/Middleware/ValidateIntegrationKey.php \
    tests/Feature/NashmiNonceEnforcementTest.php
```

The only remaining PHPStan finding on the middleware is a
pre-existing `booleanAnd.rightAlwaysTrue` on the guard
`if (empty($signingSecret) && $isProduction)` at line 62 — that
condition existed at `HEAD` before CS-07 (verified via `git show
HEAD~5:...`). The new nonce block itself is clean.

## Report fields

```
ITEM_ID=CS-07
ORIGINAL_FINDING=NEW-A12 (Nashmi nonce optional in production; replay protection degraded to timestamp window)
START_HEAD=ee31466
END_HEAD=dff547c2fb02e638039a2d6f449749c4119f41a3
STATUS=FIXED
ROOT_CAUSE=Nonce header was documented but not required in any environment; storage used non-atomic Cache::has()/put(); cache key derivation used md5() and did not incorporate the signing secret so key rotations didn't invalidate prior nonces.
IMPLEMENTATION_DECISION=Enforce presence in production (401 on missing); switch storage to atomic Cache::add() so two concurrent identical requests cannot both pass; namespace the cache key by the first 12 hex chars of sha256(secret) so a key rotation invalidates prior nonces; use full sha256 for the nonce hash to remove cheap collision engineering.
FILES_CHANGED=backend/integrations/Nashmi/Http/Middleware/ValidateIntegrationKey.php
MIGRATIONS_ADDED=none
TESTS_ADDED=backend/tests/Feature/NashmiNonceEnforcementTest.php (5 tests: missing-in-prod, missing-outside-prod-ok, replayed rejected, atomic concurrent race, key-rotation invalidation)
TESTS_MODIFIED=none (pre-existing NashmiSecurityTest::test_replay_nonce_rejection continues to pass)
FOCUSED_TEST_RESULT=PASS (9 tests / 15 assertions across Nashmi feature suites)
CONTAINING_SUITE_RESULT=PASS (full backend suite — 909 tests / 905 passed / 4 skipped / 3010 assertions)
STATIC_ANALYSIS_RESULT=PASS on the new nonce block; the pre-existing `booleanAnd.rightAlwaysTrue` finding at line 62 predates this sprint (confirmed via git show HEAD~5) and is untouched.
RUNTIME_VERIFICATION=`test_atomic_replay_protection_only_one_of_two_concurrent_wins` reconstructs the exact cache key the middleware writes; if the middleware ever changes its key derivation the test fails immediately.
RESIDUAL_RISK=Cache-backed atomicity depends on the CACHE_STORE being a serialized backend (redis/database). If a deploy uses `file` or `array` in production Cache::add() semantics can still be non-atomic across workers — but ProductionSafety::checkCacheStore already rejects `file`/`array` at boot.
EXTERNAL_BLOCKER=Nashmi provider must include a nonce header on every request. If Nashmi's own client cannot send one in production, `X-Request-Id` (which most reverse proxies emit) will be accepted as a fallback per the middleware's chain of `X-Nashmi-Nonce` → `X-Integration-Nonce` → `X-Request-Id`.
COMMIT=dff547c
NEXT_ITEM=CS-08
```

## Acceptance criteria

```
NASHMI_NONCE_REQUIRED_IN_PRODUCTION=YES     (401 on missing header in APP_ENV=production)
NONCE_ATOMIC_REPLAY_PROTECTION=YES          (Cache::add() put-if-missing; namespaced by secret fingerprint)
CONCURRENT_REPLAY_TEST=PASS                 (test_atomic_replay_protection_only_one_of_two_concurrent_wins)
```
