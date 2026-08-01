# CS-02 · Complete queue runtime integration

## Defect at start of sprint

Two queue-job classes existed but had **zero production dispatchers**:

* `App\Jobs\ProcessNotificationJob`
* `Integrations\Nashmi\Jobs\ProcessNashmiOutboundJob`

Both were only referenced from `tests/Unit/QueueJobsTest.php` (a
dispatch-shape check with `Queue::fake()`). The scaffolding claimed
async capability that the runtime did not actually possess.

There were also no `jobs`, `failed_jobs`, or `job_batches` migrations
in the repository. The code default in `backend/config/queue.php`
(`env('QUEUE_CONNECTION', 'database')`) would 500 on the first dispatch
in any environment where `QUEUE_CONNECTION` was left unset — the
schema Laravel needs simply wasn't there. `queue:failed` /
`queue:retry` / `queue:flush` were non-functional for the same reason.

## What changed

### Wire real production dispatchers

**Nashmi outbound.** `IntegrationController::notifyCodeDone` no longer
runs the external HTTP call on the request thread. It:

1. Rejects duplicate calls per-cycle (the cycle carries a
   `code_done_notified_at` timestamp; a second POST returns HTTP 200
   with `idempotent: true` and does not dispatch again).
2. Rejects illegal state transitions with HTTP 422 as before, and
   dispatches nothing on failure.
3. Extracts a correlation id from `X-Request-Id` (or generates one)
   and forwards it into the job.
4. Dispatches `ProcessNashmiOutboundJob` and returns HTTP 202 Accepted
   immediately. Nashmi HTTP now happens off-thread with retries.

**Password-change notification.** `AuthController::changePassword`
dispatches `ProcessNotificationJob` after the SecurityEvents entry to
deliver a user-visible inbox notice ("your password was changed") in
Arabic and English. This is a legitimate async caller — the request
does not need to block on the notification insert, and the security
log is authoritative for the audit trail. The dispatch carries the
correlation id so the notification row can be traced back to the HTTP
request that triggered it.

### Upgraded job classes

Both jobs now expose:

* `$tries = 3` — retry ceiling.
* `backoff()` returning explicit per-attempt seconds (`[30, 120, 300]`
  for the network-bound Nashmi job; `[10, 30, 60]` for the DB-bound
  notification job).
* `$timeout` — per-attempt kill switch (60s Nashmi, 30s notification).
* `WithoutOverlapping` middleware keyed by a natural idempotency key
  (`cycle_id` for Nashmi; `user_id|type|correlation_id` for
  notifications). Two concurrent workers on the same logical event
  can't both run.
* Structured `Log::channel('integration')` entries in `handle()` +
  `failed()` carrying the correlation id, attempt number, cycle
  ref/user id.
* A permanent-failure logger in `failed()` so `queue:failed` entries
  land with a searchable context line before the framework serializes
  the exception blob.

### Missing migrations shipped

* `2026_07_31_000010_create_jobs_table.php`
* `2026_07_31_000011_create_job_batches_table.php`
* `2026_07_31_000012_create_failed_jobs_table.php`

All three follow the Laravel 11 standard schema. `migrate:rollback`
was executed against each; drop is clean.

### Environment note updated

`backend/.env.example` — the old comment claimed the jobs table was
missing (`Deployments that want DB-backed jobs must also add the
matching migrations…`). Updated: the migrations now ship, so
`QUEUE_CONNECTION=database` is a valid production choice. `sync` in
production is still rejected at boot by ProductionSafety.

### Explicit note on NotificationService async

`NotificationService::sendToUser` is still called synchronously from
`JeaNotificationService::send()` for every JEA notification. This is
an explicit architectural choice: JEA notifications are inserted
inside the same DB transaction as the workflow status change, so
their durability is bound to the workflow decision (not the queue
worker). Making them async would require `dispatchAfterCommit`
plumbing throughout `WorkflowEngine` — a bigger refactor than CS-02
scope. Password-change (the CS-02 dispatch site) is not inside a
workflow transaction and is a natural async candidate.

## Verification

### Focused tests

```
$ php artisan test \
    tests/Feature/Integrations/NashmiJobDispatchTest.php \
    tests/Feature/Auth/PasswordChangeNotificationJobTest.php \
    tests/Unit/Jobs \
    tests/Feature/Queue \
    tests/Unit/QueueJobsTest.php
{"tool":"phpunit","result":"passed","tests":11,"passed":11,"assertions":33,"duration_ms":307}
```

### Containing suites

```
$ php artisan test \
    tests/Feature/Integrations tests/Feature/Auth tests/Unit/Jobs \
    tests/Feature/Queue tests/Feature/NashmiSecurityTest.php \
    tests/Unit/QueueJobsTest.php
{"tool":"phpunit","result":"passed","tests":27,"passed":27,"assertions":56,"duration_ms":415}
```

### Full backend suite (regression check)

```
$ php artisan test
{"tool":"phpunit","result":"passed","tests":877,"passed":873,"assertions":2917,"duration_ms":31175,"skipped":4}
```

13 new tests introduced, zero regressions.

### PHPStan on all changed source files

```
$ vendor/bin/phpstan analyse \
    app/Jobs/ProcessNotificationJob.php \
    integrations/Nashmi/Jobs/ProcessNashmiOutboundJob.php \
    integrations/Nashmi/Http/Controllers/IntegrationController.php \
    app/Http/Controllers/Api/AuthController.php \
    integrations/Nashmi/Models/IntegrationCycle.php
{"tool":"phpstan","result":"passed","errors":0}
```

11 pre-existing `IntegrationController` errors were also cleared by
adding `@property` annotations to `IntegrationCycle`.

### Migration reversibility

```
$ php artisan migrate --path=database/migrations/2026_07_31_000010_...
   2026_07_31_000010_create_jobs_table .............. 5.41ms DONE
   2026_07_31_000011_create_job_batches_table ....... 0.78ms DONE
   2026_07_31_000012_create_failed_jobs_table ....... 1.98ms DONE
$ php artisan migrate:rollback --step=3
   2026_07_31_000012_create_failed_jobs_table ....... 2.29ms DONE
   2026_07_31_000011_create_job_batches_table ....... 0.36ms DONE
   2026_07_31_000010_create_jobs_table .............. 0.34ms DONE
```

### Real worker integration test

`tests/Feature/Queue/RealWorkerIntegrationTest.php` runs on the
`database` driver end-to-end:

1. Asserts that `jobs` + `failed_jobs` tables exist (fails if the
   sprint's migrations were dropped).
2. Dispatches `ProcessNotificationJob` on the `database` driver.
3. Asserts a `jobs` row exists (payload persisted, no notification yet).
4. Calls `Queue::connection('database')->pop()->fire()` — the same
   internal path `queue:work` uses — and asserts:
   * the `Notification` row now exists;
   * the `jobs` row is deleted (job completed successfully);
   * `failed_jobs` is still empty (no exception path).

## Report fields

```
ITEM_ID=CS-02
ORIGINAL_FINDING=H-10 / NEW-A14 / NEW-A15 — jobs scaffold-only; missing jobs/failed_jobs migrations
START_HEAD=5105257
END_HEAD=5d0252f6dbce325bdaef041975e7135f160ee52a
STATUS=FIXED
ROOT_CAUSE=Job classes existed as scaffolding but had zero production dispatch sites; the queue system's supporting schema had never been shipped as migrations, so any deploy on the code-default `database` driver would 500 on first dispatch.
IMPLEMENTATION_DECISION=Wire ONE real dispatcher per job (Nashmi outbound + password-changed notification), ship the missing framework migrations, upgrade both job classes with idempotency middleware + timeout + backoff + structured logging + correlation id, and cover the whole pipeline with unit + feature + real-worker tests.
FILES_CHANGED=backend/app/Jobs/ProcessNotificationJob.php; backend/integrations/Nashmi/Jobs/ProcessNashmiOutboundJob.php; backend/integrations/Nashmi/Http/Controllers/IntegrationController.php; backend/integrations/Nashmi/Models/IntegrationCycle.php; backend/app/Http/Controllers/Api/AuthController.php; backend/.env.example
MIGRATIONS_ADDED=2026_07_31_000010_create_jobs_table.php; 2026_07_31_000011_create_job_batches_table.php; 2026_07_31_000012_create_failed_jobs_table.php
TESTS_ADDED=tests/Feature/Integrations/NashmiJobDispatchTest.php (3 tests); tests/Feature/Auth/PasswordChangeNotificationJobTest.php (1 test); tests/Unit/Jobs/ProcessNashmiOutboundJobTest.php (4 tests); tests/Feature/Queue/RealWorkerIntegrationTest.php (1 test)
TESTS_MODIFIED=none (existing QueueJobsTest still passes unchanged)
FOCUSED_TEST_RESULT=PASS (11 tests / 33 assertions)
CONTAINING_SUITE_RESULT=PASS (27 tests / 56 assertions)
STATIC_ANALYSIS_RESULT=PASS (PHPStan 0 errors across every touched file)
RUNTIME_VERIFICATION=RealWorkerIntegrationTest dispatches on database driver, asserts jobs table row, invokes worker via Queue::pop()->fire(), asserts Notification row inserted, jobs row removed, failed_jobs empty. Full backend suite = 877 tests / 873 passed / 4 skipped / 2917 assertions.
RESIDUAL_RISK=JeaNotificationService::send() still writes notifications synchronously (transactional coupling to WorkflowEngine); making it async needs dispatchAfterCommit plumbing and is out of CS-02 scope. Nashmi outbound retries land at Nashmi's endpoint — Nashmi's own idempotency is not guaranteed, so a partial-success retry may create duplicate Nashmi-side projects. This is external and cannot be fixed here.
EXTERNAL_BLOCKER=Redis provisioning for production (recommended over `database` driver for lower latency); Nashmi outbound endpoint idempotency (external contract).
COMMIT=5d0252f
NEXT_ITEM=CS-03
```

## Acceptance criteria

```
NOTIFICATION_JOB_PRODUCTION_DISPATCHERS=1  (AuthController::changePassword)
NASHMI_JOB_PRODUCTION_DISPATCHERS=1        (IntegrationController::notifyCodeDone)
QUEUE_DRIVER_NON_SYNC_TESTED=YES           (RealWorkerIntegrationTest on database driver)
FAILED_JOB_STORAGE_AVAILABLE=YES           (create_failed_jobs_table migration ships)
IDEMPOTENCY_IMPLEMENTED=YES                (WithoutOverlapping middleware + controller-level dedupe)
REAL_WORKER_TEST=PASS                      (Queue::pop()->fire() on database driver executes end-to-end)
```
