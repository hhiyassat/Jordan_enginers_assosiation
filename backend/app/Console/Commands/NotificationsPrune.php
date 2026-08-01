<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

/**
 * NotificationsPrune — M-09.
 *
 * Deletes READ notifications older than
 * `esp.notification_retention_days` (default 180) and UNREAD
 * notifications older than
 * `esp.notification_unread_retention_days` (default 365). Read
 * rows drop out of the applicant's dashboard quickly; unread rows
 * stay longer so an inactive applicant doesn't lose a
 * "certificate ready" notice.
 *
 * Never touches organizational audit trails or GSB / integration
 * logs — those have their own retention pruners
 * (audit:prune, gsb:prune-logs).
 *
 * Scheduled daily in routes/console.php.
 */
class NotificationsPrune extends Command
{
    protected $signature = 'notifications:prune {--dry-run : Preview without deleting}';

    protected $description = 'Delete read notifications older than retention window (M-09).';

    public function handle(): int
    {
        $readCutoff   = now()->subDays((int) config('esp.notification_retention_days', 180));
        $unreadCutoff = now()->subDays((int) config('esp.notification_unread_retention_days', 365));
        $dryRun       = (bool) $this->option('dry-run');

        // Use withoutOrgScope: the pruner runs as a console command
        // (Auth::check() is false, so the global scope no-ops); the
        // trait-driven scope is safe either way, but being explicit
        // documents the cross-tenant intent of retention pruning.
        $readCount = Notification::withoutOrgScope()
            ->whereNotNull('read_at')
            ->where('created_at', '<', $readCutoff)
            ->count();

        $unreadCount = Notification::withoutOrgScope()
            ->whereNull('read_at')
            ->where('created_at', '<', $unreadCutoff)
            ->count();

        $total = $readCount + $unreadCount;
        if ($total === 0) {
            $this->info('No notifications to prune.');
            return self::SUCCESS;
        }

        $this->info("Found {$readCount} read (>= " . $readCutoff->toDateString()
            . " age) and {$unreadCount} unread (>= " . $unreadCutoff->toDateString() . " age).");

        if ($dryRun) {
            $this->warn('Dry run — no records deleted.');
            return self::SUCCESS;
        }

        $deletedRead = Notification::withoutOrgScope()
            ->whereNotNull('read_at')
            ->where('created_at', '<', $readCutoff)
            ->delete();

        $deletedUnread = Notification::withoutOrgScope()
            ->whereNull('read_at')
            ->where('created_at', '<', $unreadCutoff)
            ->delete();

        $this->info("Pruned {$deletedRead} read + {$deletedUnread} unread notifications.");
        return self::SUCCESS;
    }
}
