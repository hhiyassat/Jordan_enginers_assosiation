# Session Handoff — 2026-07-21

Waiting on human-in-the-loop review of PR #2 before continuing.
This document captures exactly where we are so a follow-up session can pick up
without re-reading the whole conversation.

---

## Human-in-the-loop remarks received (2026-07-21)

The user shared a 96-ticket backlog from the Nashmi/Eqratech PM system. Some tickets
are already **Done** (JORD-25 app-file, JORD-5 translation) or **In Review** (matching
work I've done in prior sessions: JORD-30 cookies, JORD-32 lazy split, JORD-22 API
split, JORD-35 pagination, and dozens more). The rest are **To Do** and need
addressing on a follow-up branch.

**Naming collision warning:** the PM system's JORD-84/85/86 are different tickets
from my in-session JORD-84/85/86 (MyOffice, service fees, polish). PM system
JORD-84 = auth/me 401 handling, JORD-85 = favicon, JORD-86 = v7_startTransition.
When merging or citing tickets, use the PM's numbering — my in-session numbers
were placeholders.

**Critical To Do items (start here on the follow-up branch):**
- **JORD-52** — token not persisted; reload kicks user to login page
- **JORD-4** — file structure (In Progress); revisit
- **JORD-26** — `npm run build` fails with 68 TS errors (In Review; must re-verify)
- **JORD-27** — ErrorBoundary missing (In Review; verify still present)
- **JORD-28** — no `AbortController` / request timeout (In Review; verify)
- **JORD-38** — app not truly bilingual end-to-end (In Review; verify)

**High-priority To Do (visible user bugs):**
- JORD-88 — auditor dashboard missing (currently just placeholder text)
- JORD-58 — submit request with no files should be blocked
- JORD-62 — clicking an application from "My Requests" navigates home instead of details
- JORD-92 — EditService hard-coded Arabic strings, no i18n
- JORD-32/83 — CSP configured via `<meta>` (frame-ancestors ignored) + blocking Google Fonts

**Medium bulk (grouped for batching):**
- **Bilingual gaps** (JORD-54, 57, 60, 87, 89, 90, 91, 93, 94, 95, 96) — many pages
  have Arabic-only or mixed labels
- **UI bugs** (JORD-55, 56, 59, 61, 65, 66, 67, 68) — post-login card, add-project
  form issues, "طلباتي" navigation, certificate page reload
- **Code quality** (JORD-69–81) — stale closures, `window.confirm`/`alert` for UI,
  double casts, untyped errors, `eslint-disable` for missing deps, `logout` swallowing errors

**Skipped-till-later:**
- JORD-1 (split BE/FE into separate repos) — big architectural change; parking
- JORD-3 (React project scaffolding style) — cosmetic; parking

**Suggested next-session batching:**
1. Auth stability batch — JORD-52 + JORD-84 (401 handling) + JORD-42 (guest can visit /login)
2. Build/lint hygiene — JORD-26 (verify TS builds; fix if not) + JORD-91/92/93 i18n cleanup
3. UX bug batch — JORD-58 + JORD-62 + JORD-59 (routing / gating in applicant flow)
4. Auditor dashboard — JORD-88 (net-new page + tests)

---

## Current state

- **Branch:** `feat/jord-84-85-86-office-fees-polish`
- **PR:** https://github.com/hhiyassat/code-generation/pull/2 (open, awaiting review)
- **Base:** `main`
- **Commits (oldest → newest):**
  1. `1e5c1a5` — JORD-84 applicant self-service `/my-office`
  2. `1438ab5` — JORD-85 admin-editable service fees (50 000 JOD default seed + `PATCH /admin/services/{id}/fee`)
  3. `edbfb60` — JORD-86 polish (sortable columns, CSV export, empty-state icons)
- **Test totals after this branch:** backend `531 passed / 1997 assertions`, frontend `359 passed`
- **`main` is untouched** — direct push blocked by policy, this branch is the only landing path.

---

## What each commit delivers

### JORD-84 — applicant self-service (`/my-office`)
Office user's **read-only** view of their own dues, complaints filed **against** them,
and sanctions **on** them. Pay + decide stay admin-only per manual policy.

New backend:
- `GET /api/v1/my/dues` — own obligations + tier rate table
- `GET /api/v1/my/complaints` — complaints where `target_office_user_id = me`;
  **`reporter_display` stripped** per manual p.278 (reporter confidentiality until decision)
- `GET /api/v1/my/sanctions` — own sanctions, newest first

New frontend:
- `/my-office` — summary strip (outstanding / open complaints / active sanctions)
  + tier rate reference + obligations table + complaints list + sanctions list.
- No pay / decide affordance anywhere; a hint line points to the JEA counter.

Files:
```
backend/app/Http/Controllers/Api/MyOfficeController.php     (new)
backend/tests/Feature/MyOfficeControllerTest.php            (new, 6 tests / 17 asserts)
backend/routes/api.php                                      (+3 routes in applicant group)
frontend/src/api/myOffice.ts                                (new)
frontend/src/api/client.ts                                  (re-export)
frontend/src/pages/applicant/MyOffice.tsx                   (new)
frontend/src/pages/applicant/MyOffice.test.tsx              (new, 7 tests)
frontend/src/layout/navItems.tsx                            (+ /my-office link)
frontend/src/layout/navItems.test.tsx                       (updated: /my-office in applicant lane)
frontend/src/routes.tsx                                     (+ lazy route)
frontend/src/i18n/locales/{ar,en}.json                      (+ nav.myOffice)
```

### JORD-85 — admin-editable service fees (partial F-07)
Every placeholder-fee service (SRV-008/009, SRV-010/011, all MSC-*) was carrying
`{type: fixed, amount: 0}` from `ServicePlan2026Seeder` — a real submission produced
a zero bill. Rather than hard-code canonical F-07 numbers (source doc not available
in machine-readable form yet), this ships an admin editor with a **50 000 JOD default**
so unattended rows stand out with a "placeholder" badge in the UI.

New backend:
- `ServiceFeeDefaultsSeeder` — replaces every `fixed 0` with `{type: fixed, amount: 50000,
  currency: JOD, source: JORD-85 admin-default}`. Guarded to never clobber a real
  per_unit rate, a non-zero fixed amount, or an admin-set row. **Idempotent** — second
  run is a no-op because the default no longer matches the "placeholder" predicate.
- `GET /admin/service-fees` — compact fee-only listing (`id, code, name, is_locked, fee`)
- `PATCH /admin/services/{id}/fee` — focused fee editor. Accepts `fixed | per_unit | free`
  with the right sub-fields. **Preserves existing surcharges across edits** (JORD-65's
  1% syndicate stays attached when the base rate changes). Locked services refuse with 423.

New frontend:
- `/admin/service-fees` — one row per service, inline fee editor.
- Placeholder rows carry a soft-warning "placeholder" badge.
- Filter tabs (`all` / `placeholder` / `set`), search by code or name.
- Locked rows render read-only with a lock badge; admin unlocks from the main services page.

Files:
```
backend/database/seeders/ServiceFeeDefaultsSeeder.php               (new)
backend/database/seeders/DatabaseSeeder.php                         (registered)
backend/tests/Feature/ServiceFeeDefaultsSeederTest.php              (new, 4 tests / 11 asserts)
backend/tests/Feature/ServiceFeeEditorTest.php                      (new, 7 tests / 16 asserts)
backend/app/Http/Controllers/Api/ServiceCatalogController.php       (+ adminFeesIndex, updateFee)
backend/routes/api.php                                              (+ 2 routes)
frontend/src/api/admin.ts                                           (+ listServiceFees, updateServiceFee)
frontend/src/pages/admin/ServiceFeesAdmin.tsx                       (new)
frontend/src/pages/admin/ServiceFeesAdmin.test.tsx                  (new, 8 core tests)
frontend/src/layout/navItems.tsx                                    (+ /admin/service-fees, DollarSign)
frontend/src/routes.tsx                                             (+ lazy route)
frontend/src/i18n/locales/{ar,en}.json                              (+ nav.serviceFees)
```

### JORD-86 — polish (empty states + sortable columns + CSV export)
Applied uniformly across the five admin queues built this session
(`OfficeDues`, `ComplaintsAdmin`, `LegalFinesAdmin`, `SupervisionTransfersAdmin`,
`ServiceFeesAdmin`).

Reusable utilities in `frontend/src/utils/`:
- `csv.ts` — `toCsv()` + `downloadCsv()` (in-browser Blob download,
  UTF-8 BOM prefix so Excel renders Arabic without mojibake). Exports the
  **current filtered + sorted set** (not the server-side collection) —
  matches the "export what I'm looking at" expectation.
- `useSortableRows.ts` — sort state + memoized sorted rows. Cycles
  `asc → desc → off`. Nulls sink to the bottom on asc.
- `SortHeader.tsx` — clickable `<th>` with `aria-sort` + chevron.

Per-page changes:
- **OfficeDues** — sortable table + CSV + icon empty state.
- **LegalFinesAdmin** — sortable table + CSV.
- **ServiceFeesAdmin** — sortable table + CSV + icon empty state.
- **ComplaintsAdmin** — CSV export (card-layout, no table sort).
- **SupervisionTransfersAdmin** — CSV export (card-layout, no table sort).

Files:
```
frontend/src/utils/csv.ts                                   (new)
frontend/src/utils/csv.test.ts                              (new, 4 tests)
frontend/src/utils/useSortableRows.ts                       (new)
frontend/src/utils/useSortableRows.test.ts                  (new, 5 tests)
frontend/src/utils/SortHeader.tsx                           (new)
frontend/src/pages/admin/OfficeDues.tsx                     (sortable + CSV + empty state)
frontend/src/pages/admin/LegalFinesAdmin.tsx                (sortable + CSV)
frontend/src/pages/admin/ServiceFeesAdmin.tsx               (sortable + CSV + empty state)
frontend/src/pages/admin/ServiceFeesAdmin.test.tsx          (+2 polish tests)
frontend/src/pages/admin/ComplaintsAdmin.tsx                (CSV export in filter bar)
frontend/src/pages/admin/SupervisionTransfersAdmin.tsx      (CSV export in filter bar)
```

---

## What is missing / follow-ups

### Blocking on human review (PR #2)
Waiting for review comments. After remarks land, resume by:

1. Read the review at `gh pr view 2 --comments` (or GitHub UI).
2. Apply changes on **this same branch** (`feat/jord-84-85-86-office-fees-polish`).
3. Commit each fix separately with a message referencing the review point.
4. `git push` (no force needed unless review demands a squash).
5. Re-run `php artisan test` + `npx vitest run` to prove nothing regressed.

### Known gaps flagged in the PR body (not blockers for this PR)

**F-07 real numbers still need entering per-service.** The 50 000 JOD default is
intentionally a placeholder that raises a "placeholder" badge in the UI. Once the
source doc surfaces:
- SRV-008/009 materials-testing per-report pricing (basis: `area_m2`? per-report fixed?)
- MSC-001..MSC-014 fee schedule (some may be free-of-charge — needs the list)
- Admin uses the new `/admin/service-fees` page to set each row; no code change required
  unless a fee TYPE beyond `fixed | per_unit | free` is needed (e.g. tiered by area).

**SRV-010/011 half-fee re-approval rule NOT structurally wired.** Currently they
each carry the 50 000 JOD placeholder like everything else. To do it "right" the
schema needs a `derived_from` pointer:
```json
{
  "type": "derived",
  "derived_from": "SRV-001",
  "multiplier": 0.5
}
```
plus a `FeeCalculator` change to resolve `derived` at pay time. **Not built yet** —
would be a new ticket (JORD-87 candidate). If ops just wants "half of whatever we
happen to charge for SRV-001 today", they can plug in the fixed number via the
new editor as a stopgap.

**MSC-* free-of-charge subset.** Manual likely marks a few MSC-* as free (queries,
data-lookup pages). Once identified, admin flips those to `type: free` via the
editor — no code change.

### Not started, still on the outer backlog (from user's earlier multi-item list)
The user hasn't scheduled these but they're the natural next chunks after PR #2 merges:
- Notification surface for MyOffice events (new complaint filed against me, sanction
  effective, dues nearing due-date).
- Bulk import of MSC-* fees via a CSV upload endpoint (mirror of the CSV export).
- Historical chart of dues paid on the MyOffice summary strip.

### Operational notes for the follow-up session
- `backend/bootstrap/cache/services.php` — Laravel runtime cache; MUST be reverted
  before commits (`git checkout HEAD -- backend/bootstrap/cache/services.php`).
- Two untracked files in the working tree that are NOT part of this work and
  should NOT be committed:
  - `backend/dain-out-saleh.txt` — unrelated user file
  - `docs/تدقيق الكتروني.pptx` — user's own doc
- Direct push to `main` is blocked by the auto-mode classifier; always use a
  feature branch + PR.
- Superuser role is user-management only — **never** wire reviewer / payments /
  certs / GSB / applicant catalog to superuser (project memory constraint).
- Always write PHPUnit + Vitest tests per feature (project memory constraint).

---

## Test invariants pinned by this branch
Keep these green as you resume:

Backend:
- Applicant office querying `/my/dues` sees only their own obligations
  (`MyOfficeControllerTest::test_my_dues_does_not_leak_other_office_obligations`)
- `reporter_display` is stripped from `/my/complaints`
  (`MyOfficeControllerTest::test_my_complaints_strips_reporter_display`)
- Fee editor preserves surcharges across rate edits
  (`ServiceFeeEditorTest::test_preserves_existing_surcharges_across_edits`)
- Fee seeder never clobbers real fees
  (`ServiceFeeDefaultsSeederTest::test_does_not_clobber_a_real_per_unit_fee`, `..._fixed_fee`)

Frontend:
- MyOffice exposes no pay / decide affordance
  (`MyOffice.test.tsx:it('does NOT expose any pay / decide affordance ...')`)
- ServiceFeesAdmin locked services hide the save button
  (`ServiceFeesAdmin.test.tsx:it('shows "locked" badge and hides save button ...')`)
- CSV export button hidden when the list is empty
  (`ServiceFeesAdmin.test.tsx:it('CSV export button is present when there are rows ...')`)
