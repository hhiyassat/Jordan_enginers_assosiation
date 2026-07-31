# CS-08 · Add `(reviewer_id, created_at)` index on `application_reviews`

## Defect at start of sprint

`application_reviews.reviewer_id` is a foreign key to `users.id`.
PostgreSQL does **not** auto-index foreign-key columns. The
original migration
(`2025_01_01_000006_create_application_reviews_table.php`) only
ships an index on `(application_id, stage_id)`. The reviewer
dashboard (`ReviewDashboardController::show`) runs three
per-reviewer queries against the table:

```
SELECT count(*) FROM application_reviews
WHERE reviewer_id = ? AND created_at >= ?             -- weekly count
WHERE reviewer_id = ? AND created_at >= ?             -- monthly count + group by decision
WHERE reviewer_id = ? ORDER BY created_at DESC LIMIT 5 -- recent
```

Without an index, each of these fell back to a full sequential scan
of `application_reviews` on Postgres. The dashboard latency grew
linearly with total review volume across all reviewers, not with
the calling reviewer's slice.

## What changed

New migration
`backend/modules/JeaServices/Database/Migrations/2026_07_31_000030_add_reviewer_id_created_at_index_to_application_reviews.php`
adds a composite btree index
`application_reviews_reviewer_id_created_at_idx` on
`(reviewer_id, created_at)`. Rationale:

* Covers all three dashboard queries via a single index range scan.
* Supports the `ORDER BY created_at DESC LIMIT 5` recent-decisions
  query via an Index Scan Backward (Postgres can traverse a
  btree in either direction).
* Does not duplicate the existing `(application_id, stage_id)`
  index — that one supports the application-side lookup
  (`WHERE application_id = ? [AND stage_id = ?]`), which this new
  index cannot serve.
* Reversible via `Schema::table(...)->dropIndex(...)`. Verified
  down + up on Postgres in this sprint.

## Verification

### Focused migration

```
$ php artisan migrate --path=modules/JeaServices/Database/Migrations/2026_07_31_000030_...
 2026_07_31_000030_add_reviewer_id_created_at_index_to_application_reviews  2.85ms DONE
$ php artisan migrate:rollback --step=1
 2026_07_31_000030_add_reviewer_id_created_at_index_to_application_reviews  4.78ms DONE
```

### PostgreSQL EXPLAIN plans

Disposable Docker container `esp-pg-cs08` on port 55434. Seeded
2 000 `application_reviews` rows across 100 reviewers, then
`ANALYZE`d the table so the planner has real cardinality stats.

**With the new index in place:**

```
EXPLAIN (ANALYZE, BUFFERS)
SELECT count(*) FROM application_reviews
WHERE reviewer_id = 5 AND created_at >= now() - INTERVAL '7 days';

 Aggregate  (cost=10.58..10.59 rows=1 width=8) (actual time=0.111..0.112 rows=1)
   ->  Bitmap Heap Scan on application_reviews
         Recheck Cond: ((reviewer_id = 5) AND (created_at >= (now() - '7 days'::interval)))
         ->  Bitmap Index Scan on application_reviews_reviewer_id_created_at_idx
               Index Cond: ((reviewer_id = 5) AND (created_at >= (now() - '7 days'::interval)))
 Execution Time: 0.190 ms
```

```
EXPLAIN (ANALYZE)
SELECT * FROM application_reviews
WHERE reviewer_id = 5 ORDER BY created_at DESC LIMIT 5;

 Limit  (cost=0.28..15.34 rows=5 width=119) (actual time=0.023..0.106 rows=5)
   ->  Index Scan Backward using application_reviews_reviewer_id_created_at_idx
         Index Cond: (reviewer_id = 5)
 Execution Time: 0.128 ms
```

**Without the new index (after `DROP INDEX`) — for comparison:**

```
 Limit  (cost=10000000048.33..10000000048.34 rows=5 width=119) (actual time=590.870..590.879)
   ->  Sort ...
         ->  Seq Scan on application_reviews
               Filter: (reviewer_id = 5)
               Rows Removed by Filter: 1980
 Execution Time: 1221.662 ms
```

Two orders of magnitude improvement on the recent-decisions query;
seq-scan pattern eliminated.

### Postgres migration reversibility

```
$ DB_CONNECTION=pgsql ... php artisan migrate:rollback --step=1
  2026_07_31_000030_add_reviewer_id_created_at_index_to_application_reviews  ... DONE
$ DB_CONNECTION=pgsql ... php artisan migrate --force
  2026_07_31_000030_add_reviewer_id_created_at_index_to_application_reviews  ... DONE
```

### Full backend suite

```
$ php artisan test
{"tool":"phpunit","result":"passed","tests":909,"passed":905,"assertions":3010,"duration_ms":31300,"skipped":4}
```

Test count unchanged (this item ships schema only, no code paths).
No regressions.

## Report fields

```
ITEM_ID=CS-08
ORIGINAL_FINDING=NEW-A17 (application_reviews.reviewer_id had no supporting composite index; Postgres does not auto-index FK columns)
START_HEAD=dff547c
END_HEAD=484b36374ae5c7deaa32883399ea8ade3c0e00e2
STATUS=FIXED
ROOT_CAUSE=Foreign-key columns are not automatically indexed by PostgreSQL. The original create table migration only added an index on (application_id, stage_id); every reviewer-dashboard query fell back to a seq scan proportional to total review volume across all reviewers.
IMPLEMENTATION_DECISION=Add a single composite btree index (reviewer_id, created_at). Supports both the equality-range queries (WHERE reviewer_id = ? AND created_at >= ?) and the ordered-limit query (WHERE reviewer_id = ? ORDER BY created_at DESC LIMIT ...) via one physical structure. Did not add per-decision or per-stage columns because query evidence does not need them yet (avoid speculative width).
FILES_CHANGED=none (new migration only)
MIGRATIONS_ADDED=backend/modules/JeaServices/Database/Migrations/2026_07_31_000030_add_reviewer_id_created_at_index_to_application_reviews.php
TESTS_ADDED=none (schema-only change; existing dashboard tests continue to pass unchanged)
TESTS_MODIFIED=none
FOCUSED_TEST_RESULT=PASS (migrate up + rollback + up on both SQLite and PostgreSQL 15)
CONTAINING_SUITE_RESULT=PASS (full backend suite — 909 tests / 905 passed / 4 skipped / 3010 assertions)
STATIC_ANALYSIS_RESULT=NOT_APPLICABLE (migration-only change; no PHP source touched)
RUNTIME_VERIFICATION=EXPLAIN (ANALYZE, BUFFERS) on PostgreSQL 15 shows both dashboard-shape queries switch from Seq Scan (~1220ms with JIT) to Bitmap Index Scan / Index Scan Backward using the new index (~0.13ms). Rollback + re-apply on Postgres clean.
RESIDUAL_RISK=Index covers reviewer-side queries only; the reviewer *queue* claim/release path filters on `assigned_reviewer_id` on the applications table — a separate concern (Applications.assigned_reviewer_id also has no explicit index but the query pattern is different and less hot).
EXTERNAL_BLOCKER=none
COMMIT=484b363
NEXT_ITEM=CS-09
```

## Acceptance criteria

```
MIGRATION_REVERSIBLE=YES              (migrate:rollback executed on SQLite + PostgreSQL 15)
INDEX_PRESENT=YES                     (`\d application_reviews` shows application_reviews_reviewer_id_created_at_idx)
POSTGRES_QUERY_PLAN_USES_INDEX=YES    (Bitmap Index Scan on count, Index Scan Backward on ordered-limit — both plans captured in this doc)
```
