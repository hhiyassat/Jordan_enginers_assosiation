# Origin Baseline — esp-core-origin-v0.1

**Purpose:** pin the reference state on `esp-v2` from which `esp-core` was forked. Every future divergence between the two repositories is traceable back to this baseline.

Reference: `docs/handoffs/2026-07-25_pluggable-platform-fork-approach.md` §4.

---

## Origin metadata

| Field | Value |
|-------|-------|
| `ORIGIN_REPOSITORY` | `hhiyassat/code-generation` |
| `ORIGIN_COMMIT` | `c6c2ff4` |
| `ORIGIN_TAG` | `esp-core-origin-v0.1` |
| `FORK_DATE` | 2026-07-25 |
| `BRANCH` | `main` |
| `DEPLOY_REMOTE_AT_FORK` | `hhiyassat/Jordan_enginers_assosiation` (unchanged) |

## Test baseline

| Suite | Result |
|-------|--------|
| PHPUnit (backend) | **574 passed / 575 total** (1 skipped), 2097 assertions |
| Vitest (frontend) | **416 passed / 416 total**, 64 test files |
| Architecture (BoundariesTest) | passing |
| dep-cruiser | 0 violations |

## API baseline

`api-baseline.json` — full `php artisan route:list --json` output at origin commit.

- **92 routes** registered across:
  - `app/` (platform routes)
  - `modules/JeaServices|JeaProjects|JeaDiscipline|JeaDues`
  - `plugins/AiSchema|Captcha`
  - `integrations/Gsb|Nashmi`

## Schema baseline

`schema-baseline.sql` — full `.schema` dump from SQLite dev database at origin commit.

Includes:
- Platform tables (`users`, `organizations`, `notifications`, `audit_logs`, `personal_access_tokens`)
- Module-owned tables (per package: applications, service_definitions, certificates, complaints, sanctions, legal_fines, office_ceilings, engineer_discipline_quotas, recurring_obligations, manual_references, ...)
- Integration-owned tables (`gsb_call_logs`, `integration_cycles`)

## Dependency lock digests

Verify integrity of dependency locks at fork time:

| File | SHA-256 |
|------|---------|
| `backend/composer.lock` | `688342ef5912a51eb0489553c0fee540f5d787a391a520b04a512b5e7233e3c8` |
| `frontend/package-lock.json` | `cec6bb8d532ec2660d033fe51cf4879cb033b3e4bb0a43846b716379049ed21e` |

## PHP + Node version constraints

| Environment | Version |
|-------------|---------|
| Local dev PHP | 8.5.x (via Homebrew) |
| Production PHP (Hostinger) | **8.3.32** — known constraint: `symfony/*` 8.1 requires PHP 8.4+; `--ignore-platform-req=php` used on server |
| Node | 22.22.0 |
| Composer | 2.x |

## How to verify the baseline on esp-core

After fork execution, the esp-core repo should be at the same commit + same tag:

```bash
cd /Users/husseinhiyassat/Pluggable_Institutional_Service_Platform/esp-core
git tag -l esp-core-origin-v0.1  # should exist
git log esp-core-origin-v0.1 --oneline -1  # should show c6c2ff4
shasum -a 256 backend/composer.lock  # should match above
shasum -a 256 frontend/package-lock.json  # should match above
```

If any of these differ, the fork was not clean and must be redone.

## What is NOT captured here

- Environment secrets (`.env` files) — those are per-deployment, not baseline
- Runtime state (Laravel `bootstrap/cache/*`) — Laravel regenerates
- User data in production database — not applicable to origin (dev DB only)
- Node modules / vendor directories — regenerated from lock digests

## Retention policy

This baseline directory MUST NOT be deleted or modified. It is the authoritative reference for the fork event and is preserved for the lifetime of both repositories.
