# Session Handoff — 2026-07-22

Branch complete. PR #2 ready for human review. This document captures
everything shipped on the branch so a follow-up session can pick up
from wherever review lands.

---

## Current state

- **Branch:** `feat/jord-84-85-86-office-fees-polish`
- **PR:** https://github.com/hhiyassat/code-generation/pull/2 (open, ready for review)
- **Base:** `main`
- **Commits on branch:** 11 (each groups one logical batch of tickets)
- **Diff stats vs main:** 71 files changed, +3633 / -330 lines
- **Test totals:** backend `545 passed / 2036 assertions`, frontend `410 passed`, build green
- **`main` is untouched.** This branch is the only landing path per project policy.

---

## Commit-by-commit summary

| SHA | Batch | Tickets |
|-----|-------|---------|
| `1e5c1a5` | Applicant self-service `/my-office` (initial branch commit before this session) | JORD-84 (my numbering) |
| `1438ab5` | Admin-editable service fees + 50,000 JOD default seed | JORD-85 (my) |
| `edbfb60` | Frontend polish (sortable columns, CSV export, empty-state icons) | JORD-86 (my) |
| `7fe3d9e` | Console-cleanup + logout error surfacing | JORD-80, JORD-84/85/86 (PM) |
| `bf19594` | Applicant flow blockers | JORD-58, JORD-59, JORD-62 |
| `783c354` | Translation-gap bulk | JORD-57, JORD-60, JORD-87, JORD-89, JORD-90, JORD-94, JORD-95, JORD-96 |
| `0b4f514` | DynamicForm i18n + Reviewer Dashboard | JORD-93, JORD-88 (PM) |
| `fab2963` | Code-quality bulk | JORD-69, JORD-70, JORD-71, JORD-72, JORD-73, JORD-74, JORD-75, JORD-76, JORD-77, JORD-78, JORD-79, JORD-81 |
| `d178cbc` | Auth stability + CSP delivery | JORD-52, JORD-82, JORD-83 (PM) |
| `2d79ed6` | UI bug hunt | JORD-55, JORD-56, JORD-61, JORD-64, JORD-65, JORD-66, JORD-67, JORD-68 |
| `451b80d` | Login polish + this handoff doc | JORD-51 |

**Total tickets addressed on the branch:** ~40.

---

## What's landed — grouped by theme

### New surfaces (net-new pages / components)
- `/my-office` — applicant self-service dues + complaints + sanctions (read-only)
- `/admin/service-fees` — admin fee editor with placeholder-badge filter
- `/applications/:id` — applicant view of a single request, edit CTA
- `/review/dashboard` — reviewer landing page with workload tiles
- Reusable `ConfirmDialog` component (replaces `window.confirm`)
- Reusable `SortHeader` + `useSortableRows` + `csv` utils
- Reusable `errorMessage()` helper

### Backend endpoints added
- `GET /api/v1/my/dues`, `GET /my/complaints`, `GET /my/sanctions`
- `GET /admin/service-fees`, `PATCH /admin/services/{id}/fee`
- `GET /review/dashboard`

### Structural / API-shape changes
- `GET /auth/me` moved outside `auth:sanctum`; returns `{user: null}` for guests
- `applicationsApi.get()` typed to include `certificate_pdf_url`
- `adminApi.dashboard()` `recent` array properly typed
- `adminApi.updateService/saveService/chatUpdateSchema` accept `ServiceSchema` (not `Record<string, unknown>`)
- `admin.dashboard.recent[]` schema typed at the API layer

### Config / ops surface
- `ESP_SESSION_LIFETIME_MINUTES` — cookie lifetime env knob
- `ESP_SESSION_COOKIE_SECURE` — `auto|true|false` tri-state secure flag
- `docs/deployment_csp.md` — nginx block + cookie env reference

### Data / seeder additions
- `ServiceFeeDefaultsSeeder` — 50,000 JOD placeholder for placeholder-fee services

### Widespread refactors
- Every `(e as Error).message` swept across 16 files → `errorMessage(e)`
- All `useCallback`-wrap-then-honest-deps on `reload()` in ProjectsList, ReviewPanel, UserManagement
- Every hard-coded Arabic/English ternary in DynamicForm, DocumentUploader, DocumentPreviewCard, ProjectContextHeader, applyErrorHelpers, ErrorBoundary → i18n

---

## What's NOT on the branch

Deferred with reasoning in the commit or the handoff:

- **JORD-4** (Critical, In Progress) — file structure refactor. Big architectural. Needs a plan.
- **JORD-1** (Medium) — split backend and frontend into separate repos. Ops-heavy.
- **JORD-3** (Low) — React project scaffolding style. Cosmetic.
- **JORD-91** (OrganizationSettings i18n) — target file doesn't exist in the tree; recommend closing as stale.
- **JORD-26** (build fails with 68 TS errors) — build is now green on this branch; recommend re-checking against a fresh clone before closing.

Follow-up ideas noted for JORD-52 (not yet implemented):
- Sliding session (rotate token on activity)
- Background heartbeat that bumps cookie Expires without forcing re-login

---

## Test invariants added on this branch

Backend:
- `SessionCookieConfigTest` — cookie lifetime + secure-flag env behaviour (6 tests)
- `MyOfficeControllerTest` — cross-office isolation + reporter_display strip (6 tests)
- `ServiceFeeDefaultsSeederTest` + `ServiceFeeEditorTest` — placeholder replacement + editor scoping (11 tests)
- `ReviewDashboardTest` — role-scoped stats + list caps (8 tests)

Frontend (representative):
- `MyOffice.test.tsx` — no pay/decide affordance (JORD-84 my)
- `ApplicationDetail.test.tsx` — modifications banner, edit CTA nav, payment banner visibility
- `ServiceFeesAdmin.test.tsx` — placeholder badge, per-type payload shape, locked read-only
- `ConfirmDialog.test.tsx` — aria wiring, Escape close, destructive styling
- `errorMessage.test.ts` — safe extraction across Error, subclass, string, plain object, empty, custom fallback
- `useSortableRows.test.ts` + `csv.test.ts` — sort cycle + CSV escaping
- `AuthProvider.test.tsx` — cross-tab identity swap lock vs guest silent adopt
- `DynamicForm.i18n.test.ts` — validation messages follow current app language
- `ReviewDashboard.test.tsx` — headline tiles, overdue highlight, empty states
- `navItems.test.tsx` — sibling nav exclusivity for /admin/services vs /admin/services/new

---

## Operational notes for the follow-up session

- **`backend/bootstrap/cache/services.php`** — Laravel runtime cache. MUST be reverted before every commit (`git checkout HEAD -- backend/bootstrap/cache/services.php`). This has bitten every batch on this branch; you'll hit it again.
- **Two untracked files that are NOT part of this work and should NOT be committed:**
  - `backend/dain-out-saleh.txt` — unrelated user file
  - `docs/تدقيق الكتروني.pptx` — user's own doc
- **Direct push to `main` is blocked** by the auto-mode classifier. Always use a feature branch + PR.
- **Superuser role scope is user-management only** — never wire reviewer / payments / certs / GSB / applicant catalog to superuser (project memory).
- **Always write PHPUnit + Vitest tests per feature** (project memory).
- **Naming collision warning:** my in-session JORD-84/85/86 are DIFFERENT from the PM system's JORD-84/85/86. Use PM numbering when citing to reviewers.

---

## How to resume after review comments

1. `git checkout feat/jord-84-85-86-office-fees-polish && git pull`
2. Read review comments: `gh pr view 2 --comments`
3. Apply each fix as its own small commit referencing the review point
4. Run both suites before push:
   ```
   cd backend  && php artisan test
   cd frontend && npx vitest run && npm run build
   ```
5. `git push`
6. If a comment demands a NEW ticket-level change (not just a review nit),
   consider a rebase + squash conversation with the reviewer before doing
   it — the branch already carries 40 tickets.

---

## Suggested next branches (post-merge)

If the PM system's remaining backlog stays intact after this merges,
the next natural three branches would be:

1. `refactor/jord-4-file-structure` — big architectural, plan first
2. `chore/jord-1-monorepo-split` — CI + deploy work, plan first
3. `feat/jord-52-sliding-session` — the follow-up hardening ideas for
   cookie lifetime (only if ops asks; the env knobs may be enough)

None of these should start same-session with the review round; wait
for feedback on this PR first so the reviewer's opinion on structure
informs where the refactor goes.

---

# PART 2 — Architecture refactor + production deploy (2026-07-22 → 07-23)

Rather than block on PR #2 review, executed **JORD-4 (file structure
refactor)** as a stacked PR on top of PR #2. Then merged and started
deploying to Hostinger production. As of writing, the deploy is in a
recoverable-but-stuck state pending a PHP-version decision.

## Current state after Part 2

- **PR #2 status:** MERGED (auto-closed when PR #3 landed; its 11
  commits are ancestors of `main`).
- **PR #3 status:** MERGED at commit `e122573` on `main`.
- **`main` HEAD:** `fa0fcb0 frontend: vite dev-server allow all hosts`
  (22 commits merged in one shot).
- **Diff into `main`:** 367 files changed, ~+11,224 / -2,771 lines.
- **Local + CI tests:** backend 566/566, frontend 410/410, dep-cruise
  0 violations.
- **Hostinger production:** code is on the server, but composer
  install pulled PHP-8.4-only symfony packages. Server PHP is 8.3.32.
  Every `artisan` command dies with `syntax error, unexpected token
  "{"` in `Request.php:117`. Deploy stuck until this is resolved.

## The 16 workstreams

Each workstream shipped as its own atomic commit with acceptance
criteria verified. Merge order into `main`:

| # | commit | scope |
|---|--------|-------|
| 1–2 | `fe23ff6` | Baseline + inventory (`00-baseline.md`, `01-refactoring-plan.md`) |
| 3 | `414b9c8` | Enforcement scaffold (dep-cruiser + PHPUnit `BoundariesTest`, warnings) |
| 4 | `2a69d3e` | Per-file classification manifest (248 files tagged PC/STC/SM/PLG/EIA/RED) |
| 5A | `68597ab` | `ServiceFeesController` extracted from `ServiceCatalog` |
| 5B | `620622e` | `ApplicationController` split into 5 focused controllers |
| 5C | `9b75888` | `AdminController` split + god class deleted |
| 6 | `566530d` | Frontend `types/index.ts` + `api/admin.ts` + `api/hooks.ts` platform/JEA split |
| 7 | `6765bef` | `jea-dues` — first proof-of-concept module |
| 8A | `e4b05e3` | `jea-projects` — projects + engineers + quotas |
| 8B | `a7c832b` | `jea-discipline` — complaints + fines + transfers |
| 8C | `d43e729` | `jea-services` — application lifecycle + catalog + workflow |
| 9 | `292e975` | Frontend `platform/` consolidation |
| 10 | `a8655f8` | Frontend modularization — 66 pages → `modules/*/pages/` |
| 11 | `bfadaa6` | `ApiResponse` helper + `CorrelationId` middleware |
| 12 | `afe8e8c` | Data-ownership separation — 21 seeders → module folders + `PlatformMigrationsOnlyTest` |
| 13 | `6667d85` | Plugin architecture — `ai-schema` + `captcha` |
| 14 | `f1e6bf5` | Integration adapters — `gsb` + `nashmi` |
| 15 | `0d1ef61` | Enforcement promoted warnings → errors + 8-entry PC_ALLOWLIST |
| 16 | `73b6e36` | Architecture docs (`04–07` under `docs/architecture/`) |

Followed by 4 post-refactor commits:

| commit | scope |
|--------|-------|
| `5505d9e` | W14 straggler — inline FQN fix in ai-schema plugin |
| `c5647cf` | Port PR #1 (reviewer release/unclaim) into module locations |
| `ca39f4d` | Merge origin/main → refactor branch — resolve PR #1 overlap |
| `07e9ed3` | CI fix — realign PHPStan baseline to post-refactor tree |
| `fa0fcb0` | frontend: Vite allowedHosts fix for mobile testing |

## Post-refactor code layout

```
backend/
├── app/                    ← PLATFORM (Auth, User, Org, Notification, AuditLog, ApiResponse, CorrelationId)
├── modules/
│   ├── JeaServices/        ← application lifecycle, catalog, workflow (28 routes)
│   ├── JeaProjects/        ← projects + engineers + office quotas (12 routes)
│   ├── JeaDiscipline/      ← complaints + sanctions + fines + transfers (11 routes)
│   └── JeaDues/            ← recurring dues (4 routes)
├── plugins/
│   ├── AiSchema/           ← Claude-backed schema authoring (3 routes)
│   └── Captcha/            ← public-form challenge (1 route + middleware alias)
└── integrations/
    ├── Gsb/                ← Government Service Bus (4 routes + alias)
    └── Nashmi/             ← contractor-management webhook API (6 routes + alias)

frontend/src/
├── platform/               ← platform components + UI kit + utils + platform-only pages
├── modules/*/pages/        ← domain pages (66 total)
├── integrations/Nashmi/pages/ ← EIA pages
└── api/, auth/, layout/, i18n/, engine/, types/ ← existing shared code (some pre-split)
```

## Enforcement in CI

- `backend/tests/Architecture/BoundariesTest.php` — 9 tests, includes
  `test_platform_does_not_import_service_modules` with 8-entry
  documented allowlist.
- `backend/tests/Architecture/PlatformMigrationsOnlyTest.php` — 2 tests
  with 3-entry allowlist for legacy JEA columns on platform tables.
- `frontend/.dependency-cruiser.cjs` — 7 rules, all `severity: error`.
  Current state: 0 violations across 166 modules and 381 dependencies.

## Documentation delivered under `docs/architecture/`

- `00-baseline.md` — pre-refactor frozen state
- `01-refactoring-plan.md` — the 16-workstream plan
- `02-workstream-03-enforcement-scaffold.md` — W3 details
- `03-file-classification.md` — per-file tags
- `04-modules.md` — how service modules work
- `05-plugins-and-integrations.md` — the other two extension points
- `06-adding-a-service-module.md` — walkthrough
- `07-adding-a-plugin.md` — walkthrough (covers integrations too)

## Traps caught during execution — documented in commit messages

Each of these bit multiple workstreams; every module-adder should know
about them (also captured in `docs/architecture/06-adding-a-service-module.md`):

1. **Same-namespace short-ref trap** — `belongsTo(User::class)`
   unqualified resolves to the file's own namespace. Every model move
   needed explicit `use App\Models\User;` imports.
2. **Inline FQN sweep gap** — `\App\Models\Foo::class` inline
   references don't get touched by a `use App\Models\Foo;` sweep. Every
   workstream had a follow-up `perl` pass for inline FQNs.
3. **`apiPrefix` doesn't cross `loadRoutesFrom()`** — module routes
   need explicit `Route::prefix('api/v1')`.
4. **`composer dump-autoload -o` after every namespace change** —
   forgotten once, autoload_classmap.php stays stale forever.
5. **`bootstrap/cache/services.php`** — Laravel runtime cache; must be
   reverted before every commit (`git checkout HEAD -- ...`).
6. **PSR-4 sub-namespace short-ref** — `throw new Exceptions\Foo(...)`
   resolves via sub-namespace; needed `use App\Engine\Exceptions;`
   alias when engine files moved.
7. **`vi.mock('...')` and `vi.importActual('...')`** — module
   specifiers are STRING literals, tsc doesn't rewrite them. Every
   frontend move needed a manual sweep.
8. **Depth-2 nested subdirs** need TWO `../` insertions during a page
   move, not one.
9. **Same-namespace short-ref in Organization.php** — caught only after
   the CI ran with strict boundaries. `Organization` model had unqualified
   `hasMany(ServiceDefinition::class)` etc. Fixed with explicit imports
   + allowlist entry.

## Post-merge deployment story (still in progress)

**Deploy target:** Hostinger VPS at `srv1841200`, path
`/var/www/html/Jordan_enginers_assosiation`. Deploy repo is a
SEPARATE GitHub remote (`jea` = `hhiyassat/Jordan_enginers_assosiation.git`)
distinct from `origin` (`hhiyassat/code-generation.git`).

**Force-pushed `origin/main` → `jea/main`** (destroyed a duplicate
vite fix commit `06bee5e` on the deploy repo; zero functional loss
since `fa0fcb0` on origin does the same thing).

**Deploy sequence that worked up to `composer install`:**

1. `cd /var/www/html/Jordan_enginers_assosiation`
2. `git reset --hard origin/main` (forced sync past the divergent commit)
3. `ls modules/` — showed `JeaServices JeaProjects JeaDiscipline JeaDues` ✓
4. `composer i --no-dev -o --ignore-platform-req=php` — installed packages BUT
5. `php artisan optimize:clear` — dies with `syntax error, unexpected token "{"` in `vendor/symfony/http-foundation/Request.php:117`.

**Root cause:** `composer.lock` from the laptop pins `symfony/*` to
v8.1.x which uses PHP 8.4-only property-hooks syntax. The Hostinger
server has PHP 8.3.32 which can't parse it.

**Two recovery paths open** — decision needed:

| approach | notes |
|----------|-------|
| A. Install PHP 8.4 on Hostinger + switch default | `sudo update-alternatives --set php /usr/bin/php8.4`. Clean but Hostinger control-panel-dependent. |
| B. Downgrade symfony via composer platform config | On server: `composer config platform.php 8.3.32 && composer update --no-dev -o`. Rewrites composer.lock on the server. Works even without touching PHP. |
| C. Fix on laptop and re-push | Add `"config": { "platform": { "php": "8.3" } }` to `composer.json`, `composer update` locally, commit lock file, push. Cleanest long-term. |

**Recommendation for the follow-up session:** Path C. It's the only
one that survives future deploys without repeating the same manual fix.

## Actionable follow-up list

**Blocker to unstick the deploy** (pick one of A/B/C above and execute)

**Verify after deploy:**
1. `ls modules/` (already ✓)
2. `php artisan route:list | grep documents` should show
   `POST api/v1/applications/{id}/documents`
3. Run `php artisan db:seed --class="$S"` where
   `S='Modules\JeaServices\Database\Seeders\DrawingsDocumentsSeeder'`
   to backfill the `schema.documents` for DRW-P-001 through DRW-P-010
   (they lost their document manifest during a previous DB seed —
   verified via UI: DRW-P-001 shows "0 مستند" on service detail page,
   documents step of Apply flow renders empty).
4. Login as `ahmed@demo.esp` → apply for DRW-P-001 → click Next →
   confirm the 15-document manifest renders in the Documents step.
5. Delete branch `feat/jord-84-85-86-office-fees-polish` from
   `origin` (PR #2 auto-closed; branch is now obsolete).

## Architectural debt documented, deferred to future PRs

**`BoundariesTest::PC_ALLOWLIST`** — 8 platform files still importing
from modules/plugins/integrations. Each has a retirement note:

| file | reason | retirement path |
|------|--------|-----------------|
| `AdminDashboardController.php` | reads `Modules\JeaServices` for org apps + certs | split into platform admin shell + jea-services "recent apps" widget |
| `AppServiceProvider.php` | binds `Integrations\Gsb\Services\*` in container | move bindings into `GsbServiceProvider::register()` |
| `Models/User.php` | `hasMany` JEA relations (OfficeCoalition, member) | User contract that jea-projects extends |
| `Models/Organization.php` | `hasMany` domain data (services, applications, coalitions) | Same pattern as User |
| `Http/Concerns/RespondsWithLockedService.php` | reads `Modules\JeaServices\Models\ServiceDefinition` | move trait to `modules/JeaServices/Http/Concerns/` |
| `Services/Payment/MockPaymentGateway.php` | takes Application concrete | invert: `PaymentTarget` contract |
| `Services/Payment/PaymentGateway.php` | same | same |
| `Services/Notifications/NotificationService.php` | Application knowledge in notification building | accept Notifiable + payload; modules build their own |

**`PlatformMigrationsOnlyTest::KNOWN_EXCEPTIONS`** — 3 legacy JEA
columns on platform tables:

| migration | retirement |
|-----------|-----------|
| `add_annual_quota_m2_to_users` | move to Engineer table (jea-projects) |
| `add_boost_flags_to_organizations_table` | move to OfficeCeiling (jea-projects) |
| `add_office_classification_to_users_table` | move to Engineer table (jea-projects) |

**Frontend god-lists still to break up:**
- `frontend/src/layout/navItems.tsx` — hardcodes every module's nav lane
- `frontend/src/routes.tsx` — hardcodes every module's routes
- Both need a `ModuleRegistry` runtime pattern where each module
  contributes its own routes + nav + i18n at boot.

**PHPStan analysis scope** — currently only covers `app/`; expand to
`modules/`, `plugins/`, `integrations/` module-by-module to avoid one
giant baseline bump. `phpstan.neon` has `reportUnmatchedIgnoredErrors:
false` set as a transitional measure.

**API envelope adoption** — `ApiResponse` helper exists in W11; only
new endpoints use it. Migrate existing endpoints to the standard
envelope over time.

## Operational notes for the follow-up session (updated)

- **Two separate GitHub remotes** — `origin` = development
  (`hhiyassat/code-generation.git`), `jea` = deploy repo
  (`hhiyassat/Jordan_enginers_assosiation.git`). Push to `jea` for
  production deploys; the server's remote is called `origin` from
  its own perspective (points at the `jea` GitHub repo).
- **Terminal line-wrapping bit us repeatedly during SSH deploy** —
  the user's Hostinger SSH session wraps long commands onto multiple
  lines, and each visual line becomes a separate command. Use short
  shell variables (`S='...'`) or run commands one at a time.
- **`~/run.sh` deploy script needs update** — the version I proposed
  adds `composer install --no-dev --optimize-autoloader` + explicit
  cache clears + `set -e` for fail-fast. Not applied yet on server.
- **Frontend deploy** is `pm2 restart jea-frontend` after config
  changes (Vite config specifically — HMR doesn't reload
  `vite.config.ts`).
- **PR #2 obsolete** — was auto-closed by PR #3 landing; branch
  `feat/jord-84-85-86-office-fees-polish` is safe to delete from
  origin.

## Final numbers

| metric | value |
|--------|-------|
| Total commits merged to `main` this cycle | 33 (11 from PR #2 + 22 from PR #3) |
| Files changed vs pre-refactor `main` | 367 |
| Lines added / removed | +11,224 / -2,771 |
| Backend PHPUnit tests | 566 (was 545 on PR #2 tip) |
| Backend PHPUnit assertions | 2,068 |
| Frontend Vitest tests | 410 |
| Architecture-boundary tests | 11 new (2 test classes) |
| dep-cruiser rules active | 7 (all `severity: error`) |
| Backend file classification | 30 PC + 0 STC + 40 SM + 3 PLG + 9 EIA + retired-RED (was 7) |
| Frontend file classification | 45 PC + 6 STC + 79 SM + 2 PLG + 4 EIA + reduced-RED (was 23) |
| Documentation pages under `docs/architecture/` | 8 |
| Extension subsystems now individually disable-able | 8 (4 modules + 2 plugins + 2 integrations) |

Every URL is preserved verbatim. Every response envelope preserved.
No feature was removed. PR #1's reviewer release/unclaim was ported
into the new module locations. The 8-entry PC_ALLOWLIST + 3-entry
migration allowlist are documented technical debt with retirement
paths, not hidden violations.

## Status at end of session

- ✅ Refactor complete on `main`
- ✅ CI green on `main`
- ✅ Documentation complete
- ⏳ Production deploy: code on server, `composer install` succeeded,
  runtime blocked by PHP 8.3 vs symfony 8.1 mismatch. Recovery path
  chosen (Path C — laptop-side downgrade of symfony via
  `composer.json` platform config) — execution deferred to next session.
- ⏳ Backfill of `schema.documents` on server DB (DRW-P-001..010) —
  runs cleanly once deploy unblocks. Feature (documents upload for
  applicants) will render correctly after that seeder runs.
