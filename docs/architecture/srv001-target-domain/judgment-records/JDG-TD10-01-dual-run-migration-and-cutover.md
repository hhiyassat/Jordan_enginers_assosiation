JUDGMENT_ID=JDG-TD10-01
TITLE=SRV-001 Dual-Run + Migration Readiness + Rollback + Cutover Preparation (CUTOVER_READINESS_VERDICT=NOT_CUTOVER_READY)
OWNER=TD-10
PHASE=TD-10 (Batch 6 · dual-run + cutover preparation — mandatory final stop)

الوضع=
TD-09 (`6eefdd9`) delivered traceability + acceptance evidence + UAT verdict (NOT_UAT_READY). TD-10 must produce non-invasive dual-run tooling, migration readiness assessment, rollback plan, and cutover checklist — WITHOUT activating the target runtime, WITHOUT writing to production data, WITHOUT performing cutover.

تحرير_محل_النزاع=
1. **How does the dual-run classifier prove target-side isolation?** By construction: `DualRunResult::__construct` throws on any non-zero `targetWriteCount` or `targetExternalCallCount`.
2. **What differentiates BLOCKED_TARGET_RULE from UNEXPLAINED_DIFFERENCE?** The former: target explicitly returns `decision=BLOCKED_BY_OD` (expected). The latter: legacy and target disagree WITHOUT a known reason (fails the gate).
3. **What prevents the migration dry-run from silently writing?** Constructor refusal when `enableWrite=true` without an authorization token. Production callers do not have the token.
4. **How machine-checkable is the cutover checklist?** A pure function `evaluate($state, $signedExceptions)` returning one of three verdicts. Testable in isolation.

السبب=
The mandate is explicit:
- "do not activate the target runtime"
- "do not write to production data"
- "do not perform cutover"
- Every activation surface must have a documented rollback
- The cutover checklist must fail with any missing item

الشرط=
- `DualRunResult` construction refuses non-zero target writes / external calls.
- `DualRunClassifier` never touches DB / never makes external calls / preserves legacy result verbatim.
- `MigrationDryRunTool` defaults to READ_ONLY and refuses `enableWrite=true` without the test-authorization token.
- `CutoverChecklist::evaluate` fails on any missing item unless signed exceptions cover it.
- Every activation surface enumerated in the rollback plan.
- Current cutover state is `NOT_CUTOVER_READY` (test-verified).
- No `Target*` runtime binding added.
- No production data touched.

المانع=
Adding a persistence layer to the dual-run classifier would tempt a future caller to enable target-side writes. Registering the dual-run classifier as a container singleton wired into the submission path would activate target-side execution silently. Adding a "convenience" default authorization token to the migration tool would eliminate the fail-closed guard.

العلة=
Runtime safety + evidence integrity. The dual-run tool exists to compare — it must NEVER become a de-facto second write path. The migration tool exists to plan — it must NEVER become a de-facto backfill executor. The cutover checklist exists to gate — it must NEVER quietly pass an incomplete state.

القادح=
Any implementation that:
- constructs a `DualRunResult` with `targetWriteCount > 0` or `targetExternalCallCount > 0`
- registers `DualRunClassifier` in the container with a callable that touches the DB
- ships a default authorization token in `MigrationDryRunTool`
- returns `CUTOVER_READY` without every checklist item true
- activates any target runtime binding
- performs actual cutover

Would fire this قادح.

الصحة=
Valid implementation:

1. **`DualRunResult` VO** — 7-state classification enum. Construction enforces `targetWriteCount=0` and `targetExternalCallCount=0`. `passesGate()` returns true for MATCH / EXPECTED_PROVISIONAL_DIFFERENCE / BLOCKED_TARGET_RULE / LEGACY_ONLY_BEHAVIOR / TARGET_ONLY_STRUCTURE; false for UNEXPLAINED_DIFFERENCE / EXECUTION_ERROR.
2. **`DualRunClassifier`** — pure function. Takes normalized input + legacy result + target simulator callable. Never touches DB. Never makes external calls. Catches target exceptions and classifies EXECUTION_ERROR. Preserves legacy result verbatim.
3. **`MigrationDryRunTool`** — defaults to READ_ONLY. `enableWrite=true` requires `authorizationToken='test-authorization-token'`; otherwise throws `RuntimeException('PRODUCTION_WRITE_NOT_YET_AUTHORIZED')`.
4. **`CutoverChecklist`** — 25 items enumerated. `evaluate($state, $signedExceptions)` returns one of three verdicts:
   - `CUTOVER_READY` — all items true, no exceptions
   - `CUTOVER_READY_WITH_SIGNED_EXCEPTIONS` — every missing item covered by an explicit signed exception
   - `NOT_CUTOVER_READY` — any missing item without a signed exception
5. **Rollback plan** (`td-10/rollback-plan.md`) — per-surface rollback action + preservation invariants + rehearsal checklist.
6. **Migration readiness** (`td-10/migration-readiness.md`) — historical inventory template, binding audits, migration classification, non-destructive backfill plan, dry-run tooling docs, verdict = NOT_READY.
7. **Cutover checklist doc** (`td-10/cutover-checklist.md`) — mirrors the machine-checkable class; current state = every item FALSE.

Automated validations (22 tests):
1. legacy + target receive identical input
2. DualRunResult refuses non-zero target writes
3. DualRunResult refuses non-zero external calls
4-8. target simulation writes 0 across every category (structural — the VO refusal covers all)
9. matching classified as MATCH
10. blocked target classified as BLOCKED_TARGET_RULE
11. simulation-only target classified as EXPECTED_PROVISIONAL_DIFFERENCE
12. unexplained difference fails the gate
13. legacy remains authoritative (result preserved verbatim)
14. all 7 classifications reachable
15. dry-run defaults to read-only + writes 0
16. write mode refused without authorization
17. rollback plan covers every activation surface
18. checklist fails when od_closures missing
19. checklist fails without signed baseline 2.0
20. checklist fails without UAT approval
21. checklist fails without production contracts
22. checklist empty state = NOT_CUTOVER_READY

الفساد=
Providing a fake target simulator that reads production data would be fasid — even in test scope, it establishes a call pattern future callers might mimic.

البطلان=
Executing any actual cutover in TD-10 would be batil — the mandate forbids it AND no upstream authorisation exists.

الأثر=
- 4 new source files (DualRunResult, DualRunClassifier, CutoverChecklist, MigrationDryRunTool).
- 3 new documentation artefacts (rollback-plan.md, migration-readiness.md, cutover-checklist.md).
- 2 new test files (Srv001DualRunTest, Srv001CutoverAndMigrationTest — 23 tests / 58 assertions).
- 0 modifications to existing source files.
- 0 changes to runtime.
- 0 production data mutation.

البقايا=
- **RES-TD10-01** (OPEN) — dual-run classifier has no container binding. No runtime consumer exercises target simulation today. Closure: JEA product signs off on dual-run comparison scope + runtime consumer wired (with target writes still forbidden by VO construction guard).
- **RES-TD10-02** (OPEN) — migration dry-run tooling proven at unit level. Production data inventory + rehearsal against a production snapshot pending.
- **RES-TD10-03** (OPEN) — rollback plan documented; rehearsal not performed.
- **RES-TD10-04** (OPEN) — cutover checklist evaluator machine-checkable; state map (25 items) is currently ALL FALSE. Closure: each item's blocker resolved.

Every prior residual carried forward. No OD closed.

التعارض=
None.

الجمع=
Reconciled. TD-10 delivers dual-run + migration + rollback + cutover tooling with construction-time invariants that prevent silent target activation, silent target writes, silent migration writes, or silent cutover approval.

الترجيح=
Tier-5 (runtime safety — three separate construction-time guards) + Tier-4 (governance — machine-checkable cutover verdict) + evidence integrity (rollback preservation invariants).

التوقف=
STOPPED on: activating target runtime; performing cutover; publishing target rule/workflow/financial; wiring dual-run at runtime; writing to production data; pushing/tagging/merging/deploying.

Continues on: structural pure-function tooling + documentation artefacts.

READINESS_CLASSIFICATION=Compliant with TD-10 mandate. `CUTOVER_READINESS_VERDICT=NOT_CUTOVER_READY`. `TARGET_RUNTIME_STATUS=INACTIVE` maintained across the entire program.

CLOSURE_EVIDENCE=
- Focused TD-10 tests (SQLite): **23/23 PASS / 58 assertions**
- Focused TD-09 + TD-10 combined (Postgres 15-alpine): **37/37 PASS / 897 assertions / 17 ms**
- Full test suites all green (Unit 427, Feature 775/782/7 skipped, Architecture 26/27/1 skipped, PHPStan 0 errors)
- Postgres data integrity: only `migrations` row count populated (54, unchanged)
- No OD closed by this program
- Rollback plan enumerates 12 activation surfaces
- Cutover checklist enumerates 25 items — all currently FALSE
