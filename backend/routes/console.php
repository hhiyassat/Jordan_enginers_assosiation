<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| ESP v2 — Console / Scheduled Tasks
|--------------------------------------------------------------------------
|
| Laravel 11 schedule is defined here (no Kernel.php in L11).
| Run the scheduler: php artisan schedule:run  (via cron: * * * * *)
|
*/

// ── GSB audit log retention — MODEE Annex 4.15 §4.9.3 ──────────────
//
// Prune GSB call logs older than the configured retention period
// (minimum 180 days per policy). Runs daily at 02:00 server time.
// Use --dry-run flag manually to preview before actual deletion:
//   php artisan gsb:prune-logs --dry-run

Schedule::command('gsb:prune-logs')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::critical(
            'GSB log pruning failed — MODEE §4.9.3 retention policy at risk'
        );
    });

// ── Audit log retention — NFR-006 ────────────────────────────────────
//
// Prune audit_logs older than AUDIT_LOG_RETENTION_YEARS (default 7).
// Runs on the 1st of every month at 03:00 server time — cheap enough to run
// often, but not on the daily hot path.

Schedule::command('audit:prune')
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::critical(
            'Audit log pruning failed — NFR-006 (7-year retention) at risk'
        );
    });

// ── JORD-79: annual dues cron (JEA manual pp.96-97) ────────────────
//
// Every February 1 at 04:00 UTC create the F-05 annual-dues
// obligation for every active office. RecurringDuesService is
// idempotent via its composite unique so a re-run mid-month
// is a no-op.

Schedule::command('dues:open-annual')
    ->yearlyOn(2, 1, '04:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::critical(
            'Annual-dues opening failed — offices not billed for the year'
        );
    });
