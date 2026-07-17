<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * AuditLogPrune — NFR-006
 *
 * Enforces the 7-year audit log retention policy. Deletes rows older than
 * AUDIT_LOG_RETENTION_YEARS (default 7). Scheduled monthly in routes/console.php.
 *
 * DATA-003 note: audit_logs is append-only for the retention window. Pruning
 * beyond that window is an intentional, policy-driven deletion (retention
 * enforcement), not a mutation of in-window records.
 *
 * Usage:
 *   php artisan audit:prune
 *   php artisan audit:prune --dry-run
 */
class AuditLogPrune extends Command
{
    protected $signature = 'audit:prune {--dry-run : Preview without deleting}';

    protected $description = 'Prune audit_logs older than AUDIT_LOG_RETENTION_YEARS (NFR-006, default 7 years)';

    public function handle(): int
    {
        // config('esp.audit_retention_years') resolves the env var inside config/esp.php.
        // Calling env() here directly is disallowed (PHPStan larastan.noEnvCallsOutsideOfConfig).
        $years  = (int) config('esp.audit_retention_years', 7);
        $cutoff = now()->subYears($years);
        $dryRun = $this->option('dry-run');

        $count = AuditLog::where('created_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info("No audit logs older than {$years} years (before {$cutoff->toDateString()}).");
            return self::SUCCESS;
        }

        $this->info("Found {$count} audit log entries older than {$years} years (before {$cutoff->toDateString()}).");

        if ($dryRun) {
            $this->warn('Dry run — no records deleted.');
            return self::SUCCESS;
        }

        $deleted = AuditLog::where('created_at', '<', $cutoff)->delete();
        $this->info("Pruned {$deleted} audit log entries. Retention: {$years} years (NFR-006).");

        return self::SUCCESS;
    }
}
