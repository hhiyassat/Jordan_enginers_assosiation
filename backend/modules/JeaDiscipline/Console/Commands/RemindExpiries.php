<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Console\Commands;

use App\Models\Application;
use App\Services\Notifications\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * RemindExpiries — JORD-80
 *
 * Daily cron that emits reminders for two application-level expiries:
 *   • output_validity_expiry (JORD-58 — 5-year drawing approval window)
 *   • supervision_expiry     (JORD-59 — 6-month supervision contract)
 *
 * Fires at three thresholds — 30, 7, and 1 day(s) before expiry.
 * NotificationService dedupes on (app_id × kind × threshold) so
 * running the cron daily during the 30-day window doesn't spam
 * the applicant with 30 identical notifications.
 *
 * Scans only APPROVED applications — draft/submitted apps have no
 * expiry to remind about. Skips certificate_issued (terminal) too
 * because that's post-payment and the expiry is baked into the cert.
 */
class RemindExpiries extends Command
{
    protected $signature = 'retention:remind {--dry-run : preview without emitting}';
    protected $description = 'Emit 30/7/1-day expiry reminders for drawing approvals + supervision contracts (JORD-80)';

    /** Trigger thresholds in days. Order matters: closest first so
     *  the "1 day" reminder wins the tie-break if two thresholds
     *  match on the same run (edge case at exactly-1-day-remaining). */
    private const THRESHOLDS = [1, 7, 30];

    public function handle(NotificationService $notifier): int
    {
        $dryRun = $this->option('dry-run');
        $today = now();
        $emitted = 0;
        $skipped = 0;

        Application::where('status', Application::STATUS_APPROVED)
            ->with(['applicant', 'serviceDefinition', 'reviews'])
            ->chunkById(200, function ($apps) use (&$emitted, &$skipped, $notifier, $dryRun, $today) {
                foreach ($apps as $app) {
                    foreach (['output_validity', 'supervision'] as $kind) {
                        $expiryAttr = "{$kind}_expiry";
                        $expiry = $app->{$expiryAttr};
                        if (!$expiry) { $skipped++; continue; }

                        $daysRemaining = (int) floor($today->diffInDays($expiry, false));
                        // Match highest-priority threshold the current
                        // days-remaining crosses (e.g. 5 days → match 7,
                        // not 30, so the applicant sees the newer notice).
                        $matched = null;
                        foreach (self::THRESHOLDS as $t) {
                            if ($daysRemaining <= $t && $daysRemaining >= 0) {
                                $matched = $t;
                                break;
                            }
                        }
                        if ($matched === null) { $skipped++; continue; }

                        if ($dryRun) {
                            $this->line("[dry] app={$app->id} kind={$kind} threshold={$matched}d remaining={$daysRemaining}d");
                            $emitted++;
                            continue;
                        }

                        try {
                            $notifier->emitExpiryReminder($app, $kind, $matched);
                            $emitted++;
                        } catch (\Throwable $e) {
                            Log::warning('retention:remind failed to emit', [
                                'application_id' => $app->id,
                                'kind'           => $kind,
                                'error'          => $e->getMessage(),
                            ]);
                        }
                    }
                }
            });

        $prefix = $dryRun ? 'DRY RUN: ' : '';
        $this->info("{$prefix}✓ {$emitted} reminders emitted; {$skipped} apps had no upcoming expiry.");
        return self::SUCCESS;
    }
}
