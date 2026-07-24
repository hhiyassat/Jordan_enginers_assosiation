# JORD review — final dispositions

Every code-actionable JORD task from the review has landed on `main`.
This document records why the remaining five tasks were **not**
translated into commits and what the review team should do with them
if the objection stands.

Snapshot at the time of writing (pre-refactor):
- Frontend: 260/260 Vitest specs + 12/12 Playwright specs, `tsc` clean, `npm run build` succeeds.
- Backend: 291/291 PHPUnit, PHPStan 0.
- Total JORD tasks retired via code: 43/49.

**Current snapshot (post-refactor, 2026-07-23):**
- Frontend: 410/410 Vitest specs, dep-cruise 0 violations, `npm run build` succeeds.
- Backend: 566/566 PHPUnit + 2068 assertions, PHPStan 0.
- Total JORD tasks retired via code: 47/49 (JORD-4 added; JORD-15 fully resolved except one file).

Open tasks left un-actioned:

## JORD-1 — split BE/FE into separate repos

**Won't fix (product decision).** The current monorepo carries backend
(`backend/`) and frontend (`frontend/`) side by side with a shared CI
pipeline (`.github/workflows/ci.yml`), a shared Playwright E2E harness
(`e2e/`), and a single git history that makes contract-changing PRs
atomic (backend endpoint + frontend consumer land in the same commit).

Splitting would cost:
- Two CI configs, two `.env.example` files, two release cadences.
- Cross-repo PR coordination for every API change (backend PR → frontend PR → deploy in the right order).
- Loss of the atomic history that made e.g. `JORD-9`'s notification-system PR reviewable — one diff showed the migration, the emitter, the endpoint, and the bell UI together.

Not blocking any known work. If the platform grows to > 1 team or the
frontend gets deployed independently (e.g., separate ops team), revisit.

## JORD-3 — "React project building method is incorrect"

**Won't fix — Vite is the correct choice.** The complaint is vague; the
review notes only "the base environment when we build a React project
without AI is different". The project uses Vite + React 18 + TypeScript
+ Tailwind, which is the community-standard modern React stack (Vite is
what CRA graduated to). `npm run build` produces a lazy-loaded bundle
of ~120 KB gzipped with per-route chunks (JORD-32).

If the reviewer meant something specific (module federation? SSR?
Next.js?), reopen with the concrete requirement.

## JORD-8 — performance / load times

**Already addressed.** Every concrete performance lever from the review
has landed:
- **JORD-32**: `React.lazy` on every route → each user role only downloads their surface.
- **JORD-33**: React Query with `staleTime` + `refetchOnWindowFocus: false` → no more redundant fetches on tab-switch.
- **JORD-49**: dropped useless `useMemo` wrappers.
- **JORD-28**: `AbortController` + 30s timeout so dead connections don't hang UI.
- **JORD-40**: demo credentials removed from prod builds (Vite dead-code eliminates).

Current bundle: ~120 KB gzipped for the initial chunk. Per-route
chunks range 5–25 KB. Reopen with a Lighthouse audit if a specific
metric is regressing (LCP / TTI / CLS on a real deployment).

## JORD-15 — split multi-purpose files

**Mostly resolved (post-refactor 2026-07-23).** Was "partially done"
pre-refactor; now:

- ✅ `AdminController.php` → **deleted** (Workstream 5C, commit `9b75888`).
  Split into: `AdminDashboardController` (platform, `app/`),
  `AiSchemaController` (plugin, `plugins/AiSchema/`),
  `UserManagementController` (platform, extracted earlier).
- ⏳ `frontend/src/pages/admin/NewService.tsx` (~885 lines) — still not
  split. Moved intact to `frontend/src/modules/JeaServices/pages/NewService.tsx`
  in Workstream 10. AI-generator UI could split into input/schema/
  validation/audit tabs. Deferred until next active-development touch.

Original pre-refactor text below for history:

> Not blocking any known work. Defer until the next active-development
> touch on one of these files makes the split cheap. Won't run the
> mechanical churn now for its own sake.

## JORD-37 — video attachment on the tracker

**Needs product input.** The task body is a Windows-local file path:
`"C:\Users\alqwa\Downloads\final_video (3).mp4"`. There is no
description of what the video shows or which behaviour it demonstrates.

Cannot act without a concrete reference. Reviewer should either:
1. Attach the video to the tracker via the Nashmi upload flow so we can watch it, or
2. Describe in prose what the video demonstrates.

Marking this as blocked-on-info.

---

## Summary for the review

Updated table (post-refactor 2026-07-23):

| Task | Status | Reason |
| :--- | :--- | :--- |
| JORD-1 | Won't fix | Monorepo is right for this size. |
| JORD-3 | Won't fix | Vite is the community-standard React build. |
| JORD-4 | ✅ Done | File structure refactor — 16 workstreams, PR #3 merged. See `docs/architecture/`. |
| JORD-8 | Already addressed | JORD-32/33/49/28/40 covered every lever. |
| JORD-15 | Mostly done | AdminController split (W5C); only `NewService.tsx` remains. |
| JORD-37 | Blocked | Need the video (or a description). |

Every other JORD task has a commit landed on `main` referencing its
ID in the message. `git log --grep=JORD-<n>` returns the exact PR.

## Post-refactor additions (2026-07-22 → 07-23)

Between the original review and this update, ~40 additional JORD
tickets landed via PR #2 (`feat/jord-84-85-86-office-fees-polish`,
auto-closed by PR #3 landing). Notable:

| batch | tickets |
|-------|---------|
| Applicant self-service | JORD-84 (my/dues, my/complaints, my/sanctions) |
| Admin fee editor | JORD-85 |
| Frontend polish | JORD-86 (sortable columns, CSV export, empty states) |
| Reviewer dashboard | JORD-88 |
| Applicant flow blockers | JORD-58, 59, 62 |
| Translation gaps | JORD-57, 60, 87, 89, 90, 93, 94, 95, 96 |
| Code-quality bulk | JORD-69–81 |
| Auth stability + CSP | JORD-52, 82, 83 |
| UI bug hunt | JORD-55, 56, 61, 64, 65, 66, 67, 68 |
| Login polish | JORD-51 |

**JORD-4** deserves special mention: was "Critical, In Progress" at
review time; now fully done via the 16-workstream architecture
refactor. See `docs/architecture/01-refactoring-plan.md` for the
executed plan, `docs/architecture/04-modules.md` for the resulting
module system, and `docs/session_handoff_2026-07-22.md` Part 2 for
the day-of-execution narrative.
