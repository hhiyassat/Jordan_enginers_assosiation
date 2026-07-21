<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * ServiceFeeDefaultsSeeder — JORD-85 (partial F-07)
 *
 * The F-07 canonical fee schedule for SRV-008/009 (materials-testing
 * per-report), SRV-010/011 (survey re-approval half-fees), and every
 * MSC-* miscellaneous service is not yet published in a machine-
 * readable source we can seed from. Until it is, this seeder lays
 * down a single admin-configurable default fee so the placeholder
 * `fixed 0` blocks stop letting a submission produce a zero bill.
 *
 * The default (50000 JOD) is intentionally high — it's a placeholder
 * that MUST be overridden per service by the admin via the new
 * `PATCH /admin/services/{id}/fee` endpoint before those services
 * go live. Ops can adjust the default here without a code review
 * loop for every subsequent fee update.
 *
 * Applies only to services whose fee block currently has
 * `type === 'fixed'` && `amount === 0` — i.e. the placeholder that
 * ServicePlan2026Seeder writes for services with no wired fee.
 * SRV-001..006 already have per_unit(length_lm) from
 * SiteSurveyFeesSeeder and are skipped.
 *
 * Idempotent — safe to re-run. Won't clobber a real fee an admin
 * has already set through the fee editor (any non-placeholder is
 * left alone).
 */
class ServiceFeeDefaultsSeeder extends Seeder
{
    public const DEFAULT_AMOUNT_JOD = 50000;
    public const DEFAULT_CURRENCY   = 'JOD';

    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command?->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        $updated = 0;
        $services = ServiceDefinition::where('organization_id', $org->id)->get();
        foreach ($services as $svc) {
            $schema = $svc->schema ?? [];
            $fee    = $schema['fee'] ?? null;

            // Only replace the placeholder — never clobber a real fee.
            $isPlaceholder = is_array($fee)
                && ($fee['type']   ?? null) === 'fixed'
                && ((float) ($fee['amount'] ?? 0)) === 0.0;

            if (!$isPlaceholder) continue;

            $schema['fee'] = [
                'type'     => 'fixed',
                'amount'   => self::DEFAULT_AMOUNT_JOD,
                'currency' => self::DEFAULT_CURRENCY,
                'source'   => 'JORD-85 admin-default — override per service via PATCH /admin/services/{id}/fee once F-07 amounts are published.',
            ];
            $svc->update(['schema' => $schema]);
            $updated++;
        }

        $this->command?->info("✓ Service fee defaults ({$updated} services set to "
            . self::DEFAULT_AMOUNT_JOD . ' ' . self::DEFAULT_CURRENCY . ' — admin-editable).');
    }
}
