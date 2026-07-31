# CS-05 · Remove hidden + direct cross-module coupling

## Defects at start of sprint

The independent post-remediation audit surfaced three coupling problems:

1. **Visible cross-JEA-module `use` imports** — 14 documented entries
   in `SM_ALLOWED_IMPORTS` (down from 15 after CS-04 retired
   `SanctionGuard`). Each is a JEA-sibling reaching into another
   JEA-sibling's Eloquent model / engine / service.

2. **Hidden container resolves** — the boundary detector only matched
   `^use Modules\...` lines, so four cross-module resolves that used
   `app(\Modules\<Other>\...)` slipped past it entirely:
   * `JeaServices\Models\Application::booted::deleted →
     app(\Modules\JeaProjects\Engine\QuotaLedger)`
   * `JeaServices\Http\Controllers\ApplicationController` — three
     resolves in `show()` + `submit()` (`QuotaLedger` overflow,
     `CapacityGuard`, `SanctionGuard`).

3. **One frontend cross-module import** —
   `frontend/src/modules/JeaServices/pages/Apply.tsx` imported
   `ProjectContextHeader` from `frontend/src/modules/JeaProjects/pages/`.

## Approach

Full retirement of every entry in `SM_ALLOWED_IMPORTS` is a
multi-week refactor: FK relations (`LegalFine` /
`SupervisionTransfer` `belongsTo(Application)`), seed data,
whereHas queries, and cross-cutting guards would each need bespoke
contracts or Platform-owned registries. That is out of scope for a
single closure sprint item.

What CS-05 does now is close the *invisibility* gap: every hidden
resolve becomes a first-class allowlist entry that the strengthened
detector policies. Combined with CS-04's real adoption of
`ApplicationLookup`, this converts CS-05 from "silent debt" to
"itemised debt with retirement paths and a hard ceiling."

## What changed

### Detector strengthened

`tests/Architecture/SiblingModuleBoundariesTest` — the private
`crossModuleReferencesIn()` helper now catches:

* `^use Modules\<Other>\...` (as before);
* `app(\Modules\<Other>\...::class)`,
  `resolve(\Modules\<Other>\...::class)`,
  `make(\Modules\<Other>\...::class)`;
* `new \Modules\<Other>\...(...)`.

Both the "no undocumented coupling" test AND the "no stale
allowlist entries" test share this helper. From now on, converting
a `use` into a hidden `app()` call does not remove the entry from
the allowlist — the file still counts as coupled.

### Hidden resolves documented

`SM_ALLOWED_IMPORTS` gains one net-new entry:

* `JeaServices/Http/Controllers/ApplicationController.php` — three
  hidden resolves in `submit()`/`show()` (`QuotaLedger` overflow,
  `QuotaLedger` capacity via `CapacityGuard`, `SanctionGuard`).

`JeaServices/Models/Application.php` was already listed for the FK
relation to `JeaProjects\Models\Project`; the strengthened detector
now also matches its `app(\Modules\JeaProjects\Engine\QuotaLedger)`
call in `booted::deleted`. Both live under one file-key entry, which
is correct — the allowlist is per-file.

Result: `HIDDEN_APP_FQCN_RESOLUTIONS_UNDOCUMENTED = 0`.

### Frontend

`frontend/src/modules/JeaProjects/pages/ProjectContextHeader.tsx` +
its `.test.tsx` moved to
`frontend/src/modules/JeaServices/pages/`. This is the correct
home: `Apply.tsx` is the only production consumer, and no
JeaProjects page uses the component. The `Apply.tsx` import path
becomes a local `./ProjectContextHeader`. All 438 frontend tests
pass and the Vite production build succeeds.

Result: `FRONTEND_SIBLING_MODULE_IMPORTS = 0`.

### Optional-module boot test

`tests/Architecture/OptionalModuleBootTest.php` (new):

* Verifies `jea-dues` can be removed from `config('modules.enabled')`
  and the ProductionSafety validator still passes — nothing in the
  invariants depends on `jea-dues`.
* Verifies the enabled-modules map lists exactly the four documented
  modules (fails the moment an undocumented module ID is added).
* Publishes the honest module-independence matrix:
  `jea-dues` is the only independently-removable module today;
  `jea-services`/`jea-projects`/`jea-discipline` form a coupled
  cluster that the CS-05 backlog will retire piecewise.

## Result vs the CS-05 acceptance list

| Metric                                        | Start | End (this sprint item) |
|-----------------------------------------------|-------|------------------------|
| `PLATFORM_TO_JEA_DIRECT_IMPORTS`              | 0     | 0                      |
| `HIDDEN_APP_FQCN_RESOLUTIONS_UNDOCUMENTED`    | 4     | 0 (all allowlisted; detector now catches them) |
| `FRONTEND_SIBLING_MODULE_IMPORTS`             | 1     | 0                      |
| `SIBLING_MODULE_DIRECT_IMPORTS` (documented)  | 14    | 15 (one net-new for the hidden bundle — visible is better than invisible) |
| `BOUNDARY_ALLOWLIST_COUNT`                    | 14    | 15                     |
| `OPTIONAL_MODULE_BOOT_TESTS`                  | none  | 3 tests / all green    |

The mandate's aspirational targets (`SIBLING_MODULE_DIRECT_IMPORTS=0`,
`BOUNDARY_ALLOWLIST_COUNT=0`) are not achievable in a focused
closure sprint item — that is real week-scale work. What CS-05 does
achieve is enforceable visibility: any NEW cross-module coupling
(including runtime resolves and `new` calls) fails the architecture
test unless the offender is added to the allowlist with a retirement
path. The remaining coupling is now recorded in the residual backlog
as CS-05-BL-1 through CS-05-BL-6.

## Verification

### Focused tests

```
$ php artisan test tests/Architecture
{"tool":"phpunit","result":"passed","tests":16,"passed":15,"assertions":27,"duration_ms":225,"skipped":1}
```

### Full backend suite

```
$ php artisan test
{"tool":"phpunit","result":"passed","tests":898,"passed":894,"assertions":2988,"duration_ms":32162,"skipped":4}
```

### Frontend

```
$ npm test -- --run
Test Files  67 passed (67)
     Tests  438 passed (438)

$ npm run typecheck    (tsc --noEmit)   → exit 0
$ npm run build       (vite build)      → exit 0; dist populated
```

### Detector self-test

Deliberately introducing a `use \Modules\JeaProjects\Models\Project`
in `JeaDues/Models/RecurringObligation.php` — which is not in
`SM_ALLOWED_IMPORTS` — makes `test_no_undocumented_cross_jea_module_imports`
fail with a clear message. Reverted the introduction; test is green
in the committed state.

## Report fields

```
ITEM_ID=CS-05
ORIGINAL_FINDING=NEW-A6 / NEW-A7 (hidden app(FQCN) resolves invisible to boundary tests) + NEW-A8 (frontend Apply.tsx importing JeaProjects/pages)
START_HEAD=3f7883a
END_HEAD=0f3372861d4373f667c9f9a4f35224a8c75376c3
STATUS=PARTIALLY_FIXED (hidden-resolve invisibility fully closed; frontend cross-module import removed; optional-module boot test added; full retirement of the 14 documented backend allowlist entries is CS-05 backlog)
ROOT_CAUSE=Two distinct gaps: (1) the sibling boundary detector only matched `^use ...` lines, so runtime container resolutions and `new \Modules\...` instantiations slipped past it; (2) full removal of every sibling coupling requires FK-relation refactors + cross-cutting guard registry + seed migrations that are out of scope for a single closure sprint item.
IMPLEMENTATION_DECISION=Close the invisibility gap: strengthen the detector to catch app/resolve/make/new patterns and register the four hidden resolves in SM_ALLOWED_IMPORTS with retirement notes. Move the one frontend cross-module import to its true home (ProjectContextHeader → JeaServices/pages). Add an optional-module boot test that publishes the honest independence matrix. Explicitly leave the 14 documented backend couplings for the CS-05 residual backlog.
FILES_CHANGED=backend/tests/Architecture/SiblingModuleBoundariesTest.php; frontend/src/modules/JeaServices/pages/Apply.tsx
MIGRATIONS_ADDED=none
TESTS_ADDED=backend/tests/Architecture/OptionalModuleBootTest.php (3 tests: jea-dues removal doesn't break invariants; only documented modules registered; module-independence matrix published)
TESTS_MODIFIED=backend/tests/Architecture/SiblingModuleBoundariesTest.php — crossModuleReferencesIn() helper unifies visible + hidden detection; both no-new-coupling and no-stale-allowlist tests share the helper
FILES_MOVED=frontend/src/modules/JeaProjects/pages/ProjectContextHeader.tsx → frontend/src/modules/JeaServices/pages/ProjectContextHeader.tsx; ...ProjectContextHeader.test.tsx → same
FOCUSED_TEST_RESULT=PASS (16 architecture tests / 15 passed / 1 skipped / 27 assertions)
CONTAINING_SUITE_RESULT=PASS (backend 898 tests / 894 passed / 4 skipped; frontend 67 files / 438 tests / all green)
STATIC_ANALYSIS_RESULT=PASS (tsc --noEmit exit 0; vite build exit 0; PHPStan on unchanged files — no new violations introduced)
RUNTIME_VERIFICATION=Full backend + frontend + build all green post-changes. Detector self-test: deliberately introducing a new cross-module `use` in JeaDues fails the boundary test with a clear message; reverted for the commit.
RESIDUAL_RISK=15 SM_ALLOWED_IMPORTS entries remain (14 old + 1 net-new for the hidden-resolves bundle). The coupled cluster jea-services / jea-projects / jea-discipline can still not be disabled independently. Retirement of each entry is CS-05-BL-1..CS-05-BL-6 in residual-backlog.md.
EXTERNAL_BLOCKER=none
COMMIT=0f33728
NEXT_ITEM=CS-06
```

## Acceptance criteria (honest reporting)

```
PLATFORM_TO_JEA_DIRECT_IMPORTS=0                  PASS  (unchanged from CS-04 baseline)
SIBLING_MODULE_DIRECT_IMPORTS=15                  NOT_ZERO (up from 14, hidden-resolve bundle made visible)
HIDDEN_APP_FQCN_RESOLUTIONS_UNDOCUMENTED=0        PASS  (detector strengthened; all 4 now allowlisted)
FRONTEND_SIBLING_MODULE_IMPORTS=0                 PASS
BOUNDARY_ALLOWLIST_COUNT=15                       NOT_ZERO (target of 0 remains CS-05 backlog)
OPTIONAL_MODULE_BOOT_TESTS=PASS                   PASS  (3 tests / all green)
```
