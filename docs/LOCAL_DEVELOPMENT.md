# ESP-v2 · Local development guide

Zero-to-login for a fresh checkout. This guide gets you from `git clone` to a working browser login without editing tracked source files.

## Prerequisites

- Docker Desktop (Compose v2)
- Node.js 20+ and npm 10+ (for the frontend dev server)
- `curl` (used by `scripts/check-local-dev.sh`)

## Quick start (default local profile)

```bash
# 1. Copy environment examples
cp frontend/.env.example frontend/.env
cp docker-compose.override.yml.example docker-compose.override.yml

# 2. Start the backend stack (Laravel + Postgres + Redis + MinIO)
docker compose up -d

# 3. Migrate + seed (idempotent — safe to re-run)
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force

# 4. Start the frontend dev server
cd frontend
npm install
npm run dev

# 5. Verify the setup (non-destructive)
../scripts/check-local-dev.sh
```

Open <http://localhost:5173> and sign in.

## Expected URLs

| URL | Purpose |
|---|---|
| <http://localhost:5173> | frontend (Vite dev server) |
| <http://localhost:5173/api/v1/auth/me> | frontend → Vite proxy → backend |
| <http://localhost:8080> | backend (Laravel via nginx in docker) |
| <http://localhost:8080/api/v1/auth/me> | backend, direct |

Unauthenticated `/api/v1/auth/me` returns:

```json
HTTP/1.1 200 OK
{"user":null}
```

## Demo credentials

Seeded by `backend/database/seeders/DemoSeeder.php` — password `Demo1234!`:

| Email | Role |
|---|---|
| `admin@demo.esp` | admin |
| `staff@demo.esp` | staff |
| `auditor@demo.esp` | auditor |
| `ahmed@demo.esp` | applicant |

## CAPTCHA behaviour by environment

The frontend widget and the backend middleware **must always agree**. If they disagree, login returns HTTP 422 with `errors.captcha_answer` and the user sees an unexplained rejection.

| Environment | `VITE_CAPTCHA_ENABLED` (frontend) | `CAPTCHA_ENABLED` (backend) | Behaviour |
|---|---|---|---|
| **Local default** | `false` | `false` (via `docker-compose.override.yml`) | No widget, no server check. |
| Local, CAPTCHA on | `true` | `true` (remove/adjust override) | Widget renders; server verifies. |
| Staging / production | `true` | `true` | Widget renders; server verifies. |

To enable CAPTCHA end-to-end for testing:

```bash
# 1. Turn ON the backend by removing the override or setting:
#    services.app.environment.CAPTCHA_ENABLED: "true"

# 2. Turn ON the frontend widget:
sed -i.bak 's/^VITE_CAPTCHA_ENABLED=.*/VITE_CAPTCHA_ENABLED=true/' frontend/.env

# 3. Restart both:
docker compose up -d --force-recreate app
(cd frontend && kill $(pgrep -f "vite"); npm run dev)
```

## Configuration contract

| Value | Source of truth | Default |
|---|---|---|
| Frontend port | `vite.config.ts` `server.host: true` (implicit 5173) | 5173 |
| Backend host port | `docker-compose.yml` `services.app.ports` | 8080 |
| Vite `/api` proxy target | `frontend/.env` `VITE_DEV_PROXY_TARGET` | `http://localhost:8080` |
| Browser API base URL | `frontend/.env` `VITE_API_BASE_URL` | `http://localhost:8080/api/v1` |
| Backend CAPTCHA flag | `docker-compose.override.yml` → `CAPTCHA_ENABLED` env | `false` (local) |
| Frontend CAPTCHA flag | `frontend/.env` `VITE_CAPTCHA_ENABLED` | `false` (local) |
| Demo user password | `backend/database/seeders/DemoSeeder.php` | `Demo1234!` |
| Internal Laravel hostname | Docker network name `app` | container-only |

## Common problems

| Symptom | Likely cause | Fix |
|---|---|---|
| `[vite] http proxy error AggregateError [ECONNREFUSED]` | Wrong `VITE_DEV_PROXY_TARGET` or Docker not up | `docker compose up -d && grep VITE_DEV_PROXY_TARGET frontend/.env` |
| Login returns 422 `errors.captcha_answer` but no widget visible | Config mismatch (frontend off, backend on) | `cp docker-compose.override.yml.example docker-compose.override.yml && docker compose up -d --force-recreate app` |
| "حدث خطأ غير متوقع" full-page overlay | Genuine unhandled render error | Check browser Console tab — inline validation errors do NOT trigger this |
| Login returns 429 | Rate-limit throttle | Wait 60s; production allows N attempts per minute per IP |
| Stale environment values | Vite didn't pick up `.env` change | Restart `npm run dev` — Vite reads envs at server start |
| Backend env change not applied | Docker container not recreated | `docker compose up -d --force-recreate app worker scheduler` |
| `docker compose exec app php artisan ...` fails with `bash: composer: command not found` | Wrong container (worker/scheduler runs supervisord only) | Use the `app` service — it has PHP + composer |
| POST `/api/v1/applications` returns HTTP 500 `Server Error`, log shows `Call to undefined function bcmul()` | Docker image built before the `bcmath` extension was added | `docker compose build app && docker compose up -d --force-recreate app worker scheduler` |
| Document upload returns HTTP 500, log shows `Class "League\Flysystem\AwsS3V3\..." not found` | Missing `league/flysystem-aws-s3-v3` composer package | Rebuild after `composer.lock` update: `docker compose build app && docker compose up -d --force-recreate app worker scheduler` |
| Document upload returns HTTP 500, log shows `basename(): Argument #1 ($path) must be of type string, false given` | MinIO bucket `esp-v2` missing (fresh compose volume) | `docker compose up -d minio-init` (idempotent) |

## How to inspect problems

| Tool | Command |
|---|---|
| Vite proxy errors | The `npm run dev` terminal (Vite logs proxy 5xx/ECONNREFUSED there) |
| Laravel logs | `docker compose logs -f app` |
| Docker service status | `docker compose ps` |
| Browser network | DevTools → Network tab, filter `auth/` |
| Browser console | DevTools → Console (React error boundary logs stack) |

## Environment reference

- **Build-time Vite variables** (must restart `npm run dev` after change): every `VITE_*` in `frontend/.env`.
- **Server-time Vite variable** (`VITE_DEV_PROXY_TARGET`): consumed by the dev server only, never bundled — still requires Vite restart.
- **Docker container variables**: after editing `docker-compose.yml` or override, run `docker compose up -d --force-recreate <service>`.

## Test suites

| Suite | Command |
|---|---|
| Backend Unit | `cd backend && ./vendor/bin/phpunit --testsuite=Unit` |
| Backend Feature | `cd backend && ./vendor/bin/phpunit --testsuite=Feature` |
| Backend Architecture | `cd backend && ./vendor/bin/phpunit --testsuite=Architecture` |
| Backend PHPStan | `cd backend && ./vendor/bin/phpstan analyse --memory-limit=1G` |
| Focused auth+captcha | `cd backend && ./vendor/bin/phpunit tests/Feature/Auth/LoginCaptchaModeTest.php tests/Feature/CaptchaServiceTest.php` |
| Frontend Vitest | `cd frontend && npx vitest run` |
| Frontend focused login | `cd frontend && npx vitest run src/auth/LoginPage.test.tsx src/test/viteConfig.test.ts` |
| Frontend build | `cd frontend && npm run build` |

## Distinction: local vs staging vs production

| Aspect | Local (this doc) | CI / test | Staging | Production |
|---|---|---|---|---|
| `docker-compose.yml` `APP_ENV` | `staging` | n/a (containerless) | `staging` | `production` |
| `docker-compose.override.yml` | copied from `.example` | not used | not used | not used |
| CAPTCHA | off | off | on | on |
| Payment gateway | fake | fake | sandbox | production adapter |
| Certificate issuance | disabled | disabled | disabled | signed |
| DB | ephemeral local docker | `RefreshDatabase` | staging-scoped | production |

## Safety rails

- `scripts/check-local-dev.sh` performs read-only checks only. It never runs `docker compose down -v`, never runs `git clean`, never prints secrets.
- `docker-compose.override.yml` is `.gitignored` if you follow the copy-from-example step; the committed file is only the `.example`.
- Never commit real credentials to `.env` or `docker-compose.override.yml`.
