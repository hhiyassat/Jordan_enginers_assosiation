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
