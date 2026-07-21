<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RecurringDuesService;
use Illuminate\Console\Command;

/**
 * JORD-79: creates F-05 annual dues obligations for every active
 * office. Idempotent — the RecurringObligation composite unique on
 * (office_user_id, kind, period_year) means running twice on the
 * same year is a no-op. Scheduled Feb 1 each year in
 * routes/console.php; can also be invoked manually.
 *
 * Usage:
 *   php artisan dues:open-annual          # opens for current year
 *   php artisan dues:open-annual --year=2027
 */
class OpenAnnualDues extends Command
{
    protected $signature = 'dues:open-annual {--year= : year to open (defaults to current)}';
    protected $description = 'Open F-05 annual-dues obligations for every active office (JORD-79)';

    public function handle(RecurringDuesService $svc): int
    {
        $year = (int) ($this->option('year') ?? now()->year);
        $count = $svc->openAnnualDuesFor($year);
        $this->info("✓ Opened {$count} annual-dues obligations for {$year}.");
        return self::SUCCESS;
    }
}
