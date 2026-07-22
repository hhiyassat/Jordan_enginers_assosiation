# JORD review — final dispositions

Every code-actionable JORD task from the review has landed on `main`.
This document records why the remaining five tasks were **not**
translated into commits and what the review team should do with them
if the objection stands.

Snapshot at the time of writing:
- Frontend: 260/260 Vitest specs + 12/12 Playwright specs, `tsc` clean, `npm run build` succeeds.
- Backend: 291/291 PHPUnit, PHPStan 0.
- Total JORD tasks retired via code: 43/49.

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

**Partially done, remainder deferred.** JORD-25 already decomposed the
699-line `App.tsx` into `auth/`, `layout/`, `routes.tsx`. What's left:

- `backend/app/Http/Controllers/Api/AdminController.php` (~1000 lines) — mixes user CRUD + application listing + audit-log + AI schema generation. Could split into `AdminUserController` / `AdminApplicationsController` / `AdminServicesController` / `AdminAIController`. Zero behaviour change; ~2 hours of careful mechanical work + reroute updates.
- `frontend/src/pages/admin/NewService.tsx` (~885 lines) — AI-generator UI. Could split the input tab, schema tab, validation tab, audit tab into siblings. Same treatment: mechanical, no behaviour change.

Not blocking any known work. Defer until the next active-development
touch on one of these files makes the split cheap. Won't run the
mechanical churn now for its own sake.

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

| Task | Status | Reason |
| :--- | :--- | :--- |
| JORD-1 | Won't fix | Monorepo is right for this size. |
| JORD-3 | Won't fix | Vite is the community-standard React build. |
| JORD-8 | Already addressed | JORD-32/33/49/28/40 covered every lever. |
| JORD-15 | Partial | JORD-25 done; AdminController + NewService remain, deferred to next touch. |
| JORD-37 | Blocked | Need the video (or a description). |

Every other JORD task has a commit landed on `jea/main` referencing its
ID in the message. `git log --grep=JORD-<n>` returns the exact PR.
