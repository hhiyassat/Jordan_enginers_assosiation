<?php

namespace App\Console\Commands;

use App\Models\GsbCallLog;
use Illuminate\Console\Command;

/**
 * GsbPruneLogs — MODEE Annex 4.15 §4.9.3
 *
 * Prunes GSB call logs older than the configured retention period (default 180 days).
 * Schedule: daily via app/Console/Kernel.php or Laravel 11 routes/console.php.
 *
 * Usage:
 *   php artisan gsb:prune-logs
 *   php artisan gsb:prune-logs --dry-run
 */
class GsbPruneLogs extends Command
{
    protected $signature = 'gsb:prune-logs {--dry-run : Preview without deleting}';

    protected $description = 'Prune GSB call logs older than configured retention period (MODEE §4.9.3 — min 180 days)';

    public function handle(): int
    {
        $retentionDays = config('gsb.logging.retention_days', 180);
        $cutoff        = now()->subDays($retentionDays);
        $dryRun        = $this->option('dry-run');

        $count = GsbCallLog::expired()->count();

        if ($count === 0) {
            $this->info("No GSB logs older than {$retentionDays} days found.");
            return self::SUCCESS;
        }

        $this->info("Found {$count} GSB log entries older than {$retentionDays} days (before {$cutoff->toDateString()}).");

        if ($dryRun) {
            $this->warn('Dry run — no records deleted.');
            return self::SUCCESS;
        }

        $deleted = GsbCallLog::expired()->delete();
        $this->info("Pruned {$deleted} GSB log entries. Retention policy: {$retentionDays} days (§4.9.3).");

        return self::SUCCESS;
    }
}
