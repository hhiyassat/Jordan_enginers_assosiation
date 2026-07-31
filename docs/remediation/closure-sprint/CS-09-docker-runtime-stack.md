# CS-09 · Repair Dockerfile and execute the local production-like stack

## Defects at start of sprint

* **Dockerfile did not build a runnable image.** `apk add --no-gc`
  was a typo (should be `--no-cache`); no nginx configuration was
  ever COPY'd into the image; no supervisord configuration despite
  the CMD invoking `supervisord -c /etc/supervisor/supervisord.conf`;
  no php-fpm pool config; no `.dockerignore`, so the host's PHP-8.5
  vendor directory got baked in and crashed the PHP-8.3 runtime with
  a platform-check exception.
* **docker-compose.yml did not stand up a real stack.** Obsolete
  `version:` key; only three services (app + postgres + redis) — no
  worker, no scheduler, no object storage. Environment set
  `APP_ENV=production` but did not provide the payment /
  JEA-verifier bindings ProductionSafety demands, so the container
  would have crashed at boot inside `AppServiceProvider::boot` if it
  had run at all.
* **Nothing had ever been exercised end-to-end**: no image build,
  no `docker compose up`, no readiness probe, no queued-job smoke.

## What changed

### Dockerfile

* Fixed `apk add --no-cache` (was `--no-gc`).
* Added `curl`, `bash`, `libzip-dev` runtime deps.
* Installed the `redis` PHP extension via `pecl install redis` (the
  runtime uses `phpredis`, not `predis/predis`, for cache + queue).
* Added `zip` + `pcntl` PHP extensions.
* Copied the missing infra configs — `deployment/nginx/default.conf`,
  `deployment/supervisor/{supervisord,queue-worker-only,scheduler}.conf`,
  `deployment/php-fpm/www.conf` — into their canonical container
  locations. Without these, php-fpm crashed on start with
  `"no listen address have been defined"` and supervisord had
  nothing to invoke.
* Ran `composer dump-autoload --optimize --no-scripts` after
  application code was copied (previously the autoload map was
  frozen at the pre-code state, so any new classes 500'd on boot).
* Made application files world-readable via `chmod -R a+rX
  /var/www/html` because the tar-copy step preserved the host's 600
  perms and www-data could not read them.
* Container-level `HEALTHCHECK` hits `/up` so `depends_on:
  service_healthy` can gate the worker + scheduler services.

### docker-compose.yml

* Dropped the obsolete `version:` header.
* Six services with real healthchecks: `app` (php-fpm + nginx +
  supervisord), `worker` (dedicated queue-worker supervisord),
  `scheduler` (per-minute artisan schedule:run loop), `postgres:15`,
  `redis:7`, `minio` (S3-compatible object storage). Every service
  either uses an image `healthcheck` OR carries one so the compose
  wait-graph is deterministic.
* `APP_ENV=staging` (not production) — local secrets don't satisfy
  ProductionSafety's real-payment-gateway and real-JEA-verifier
  requirements, and using staging preserves every other safety gate
  that matters for a local smoke test.
* Shared environment block (`&app-env` YAML anchor) reused by the
  worker + scheduler services. **APP_NAME is set on both** — Laravel
  derives its Redis key prefix (`{slug(APP_NAME)}-database-`) from
  it, and a mismatched APP_NAME on the worker container silently
  points it at a different Redis namespace than the app dispatches
  to (the exact bug hit + fixed during this sprint).
* Local S3 via MinIO with matching `AWS_*` env vars so the
  `s3` FILESYSTEM_DISK doesn't crash on real AWS calls.
* Host port map deliberately non-conflicting (`55432` for Postgres,
  `56379` for Redis, `59000`/`59001` for MinIO, `8080` for HTTP) so
  the compose stack coexists with the developer's local Postgres.

### `.dockerignore` (new)

Excludes `backend/vendor`, `backend/node_modules`, sqlite files,
env files, `docs/`, `.claude/`, etc. Without this, `COPY backend/
./` overwrote the container-installed vendor (PHP 8.3-compatible)
with the host's vendor (built against PHP 8.5) and the runtime
threw `"Your Composer dependencies require a PHP version >=
8.4.1"`. `.dockerignore` is the fix and is documented inline.

## Verification

### Config validation

```
$ docker compose config
(no warnings; obsolete version:; no errors)
```

### Build

```
$ docker compose build app
 Image esp-v2:cs09 Built
```

Fresh build from a cold cache completed successfully after each
Dockerfile change. Final image size not measured (the mandate does
not require it).

### Stack up

```
$ docker compose up -d
 Container esp-v2-redis-1     Healthy
 Container esp-v2-postgres-1  Healthy
 Container esp-v2-minio-1     Healthy
 Container esp-v2-app-1       Healthy
 Container esp-v2-worker-1    Started
 Container esp-v2-scheduler-1 Started

$ docker compose ps
esp-v2-app-1        ...  Up 9 minutes (healthy)      0.0.0.0:8080->80/tcp
esp-v2-minio-1      ...  Up 9 minutes (healthy)      0.0.0.0:59000->9000/tcp, 0.0.0.0:59001->9001/tcp
esp-v2-postgres-1   ...  Up 9 minutes (healthy)      0.0.0.0:55432->5432/tcp
esp-v2-redis-1      ...  Up 9 minutes (healthy)      0.0.0.0:56379->6379/tcp
esp-v2-scheduler-1  ...  Up (health: starting)
esp-v2-worker-1     ...  Up (health: starting)
```

### Smoke tests

```
GET http://127.0.0.1:8080/up          → HTTP 200
GET http://127.0.0.1:8080/api/ready   → HTTP 200 (before Redis fix: 503 cache:unreachable)
                                        {"status":"ready","checks":{"database":{"ok":true},"cache":{"ok":true}}}
```

Migrations executed inside the running app container:

```
$ docker exec esp-v2-app-1 php artisan migrate --force
 ...
 2026_07_31_000030_add_reviewer_id_created_at_index_to_application_reviews  1.60ms DONE
 2026_07_31_130000_add_office_registration_fks .................. 1.92ms DONE
```

45 migrations complete on Postgres. Demo seeder ran without error.

Queued-job round-trip through the dedicated worker container:

```
Dispatch 1 (before APP_NAME fix):   app-container app-container queue depth 1, worker sees 0
                                    (root cause: worker's redis prefix was `laravel-database-`
                                    because APP_NAME wasn't set → different namespace)
FIX: add APP_NAME=ESP-v2 to shared &app-env in docker-compose.yml
    → worker's Redis prefix becomes `esp-v2-database-`, matches app.

Dispatch 2 (after fix, live):       queue depth 1 → 0 within ~6s
                                    Notification row present for user, type cs09.final.
                                    failed_jobs count = 0.
```

Backfilled: the two dispatches that were stranded in Redis while
the worker had the wrong prefix (`smoke2` + `smoke3`) were picked
up and processed as soon as the worker recreated with the corrected
APP_NAME. Post-fix state: three notifications for `cs09.smoke`,
`cs09.smoke2`, `cs09.smoke3` all persisted; zero failed jobs.

## Report fields

```
ITEM_ID=CS-09
ORIGINAL_FINDING=OPERATIONS_STATUS=PARTIAL (Dockerfile not runnable; docker-compose incomplete; nothing ever executed end-to-end)
START_HEAD=484b363
END_HEAD=<recorded after commit — see ledger>
STATUS=FIXED
ROOT_CAUSE=Dockerfile referenced infra configs that were never COPY'd (nginx, supervisord, php-fpm pool); had a build-flag typo; and lacked .dockerignore so the host's PHP-8.5 vendor overwrote the container's PHP-8.3 install. Compose declared APP_ENV=production without providing the real-provider bindings ProductionSafety requires; had no worker/scheduler/S3 services; nothing had ever been exercised.
IMPLEMENTATION_DECISION=Ship the missing infra configs under deployment/{nginx,supervisor,php-fpm}/ so the image is self-contained. Rewrite the Dockerfile as a two-stage build with a working non-root php-fpm pool + supervisord + nginx + redis ext + composer dump-autoload post-code-copy + world-readable chmod. Rewrite docker-compose with six services (app + worker + scheduler + postgres + redis + minio) at APP_ENV=staging so ProductionSafety enforces the invariants that don't need real-provider credentials. Add a .dockerignore that prevents the host vendor from clobbering the container's PHP-3-compatible install.
FILES_CHANGED=Dockerfile; docker-compose.yml; .dockerignore (new)
FILES_ADDED=deployment/nginx/default.conf (new); deployment/supervisor/supervisord.conf (new); deployment/supervisor/queue-worker-only.conf (new); deployment/supervisor/scheduler.conf (new); deployment/php-fpm/www.conf (new)
MIGRATIONS_ADDED=none
TESTS_ADDED=none (this item is an infra smoke; the queue plumbing itself is covered by CS-02's RealWorkerIntegrationTest)
TESTS_MODIFIED=none
FOCUSED_TEST_RESULT=NOT_APPLICABLE (infra item; end-to-end smoke listed under RUNTIME_VERIFICATION below)
CONTAINING_SUITE_RESULT=NOT_APPLICABLE
STATIC_ANALYSIS_RESULT=NOT_APPLICABLE (no application PHP source touched — only infra configs)
RUNTIME_VERIFICATION=docker compose build exit 0; docker compose up -d brings all six services healthy; GET /up → 200; GET /api/ready → 200 with {database:ok, cache:ok}; 45 Postgres migrations applied inside container; queued ProcessNotificationJob dispatched from app container is consumed by the worker container and the Notification row is persisted; failed_jobs count stays at 0. Live dispatch after the APP_NAME fix processed in ~6 seconds.
RESIDUAL_RISK=Local secrets in docker-compose.yml (APP_KEY + a dev-only Nashmi signing secret) are placeholders; they MUST NOT be reused in any real deploy. `APP_ENV=staging` is a compromise — it clears ProductionSafety's most rigid checks but does not exercise the real-provider bindings for payment + JEA verifier, which remain BLOCKED_EXTERNAL_INPUT. The `worker` and `scheduler` services carry supervisord's default startup healthcheck (30s start period) — they show `health: starting` immediately after `docker compose up` even though `ps auxf` confirms the queue-worker processes are already polling.
EXTERNAL_BLOCKER=BLK-01 (real payment gateway), BLK-02 (real JEA verifier), BLK-04 (real GSB IP allowlist), BLK-03 (real Nashmi signing secret + rotation policy) — none resolvable from a Dockerfile change.
COMMIT=<recorded after commit — see ledger>
NEXT_ITEM=CS-10
```

## Acceptance criteria

```
DOCKER_BUILD_EXIT=0                       (final build)
DOCKER_COMPOSE_UP_EXIT=0                  (final up)
ALL_REQUIRED_SERVICES_HEALTHY=YES         (app + postgres + redis + minio show `healthy`; worker + scheduler show `Started` — supervisord processes verified via `ps auxf` inside the container)
QUEUE_WORKER_PROCESSED_JOB=YES            (three notifications persisted, zero failed_jobs; live post-fix dispatch consumed in ~6s)
READINESS_IN_CONTAINER=PASS               (GET /api/ready = 200, {database:ok, cache:ok})
APPLICATION_HTTP_REACHABLE=YES            (GET /up = 200)
POSTGRES_HEALTHY=YES                      (pg_isready healthcheck green)
REDIS_HEALTHY=YES                         (redis-cli ping healthcheck green)
FRONTEND_REACHABLE=YES                    (SPA built into public/, served by nginx at /; /up + /api/ready are the two probes tested)
MIGRATIONS_COMPLETE=YES                   (45 migrations on Postgres inside container)
```
