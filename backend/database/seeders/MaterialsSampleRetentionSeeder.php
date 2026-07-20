<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * MaterialsSampleRetentionSeeder — JORD-60
 *
 * Attaches the 10-day materials-samples retention notice from the JEA
 * 2025 technical-instructions manual (p. 36) to the two material-testing
 * services:
 *
 *   Manual (p. 36):
 *     "المحافظة على الآبار السبرية المحفورة والعينات لمدة عشرة أيام
 *      من تاريخ إحضار التقرير إلى النقابة من أجل التدقيق."
 *
 * Product decision (recorded here to save future readers a git-blame):
 * this is delivered as a `compliance_notes[]` schema entry rather than
 * a workflow stage. The 10-day hold is passive (samples stay on-site,
 * no reviewer action closes it), so adding a fake stage would clutter
 * the reviewer console. A notes entry is:
 *   • Discoverable in the schema JSON.
 *   • Available for the frontend to render as a callout later.
 *   • Cited to a page + rule id so a compliance auditor can trace it
 *     back to the source without re-scanning the PDF.
 *
 * The `compliance_notes` schema field is introduced by this seeder as
 * an opt-in on any service; nothing else consumes it yet. Future
 * seeders (fee-matrix compliance callouts, quota warnings, etc.)
 * should reuse the same shape.
 *
 * Idempotent: run() replaces the compliance_notes[] entry with rule
 * code 'S-03' rather than appending, so re-runs converge on one
 * copy of the notice per service.
 */
class MaterialsSampleRetentionSeeder extends Seeder
{
    /** Services that must carry the S-03 retention notice. */
    private const MATERIALS_TESTING_CODES = ['SRV-008', 'SRV-009'];

    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        $notice = $this->s03Notice();

        $updated = 0;
        foreach (self::MATERIALS_TESTING_CODES as $code) {
            $svc = ServiceDefinition::where('organization_id', $org->id)
                ->where('code', $code)->first();
            if (!$svc) continue;

            $schema = $svc->schema ?? [];
            $existing = $schema['compliance_notes'] ?? [];
            // Drop any pre-existing S-03 entry (idempotency) then append fresh.
            $filtered = array_values(array_filter(
                $existing,
                fn (array $n) => ($n['code'] ?? null) !== 'S-03',
            ));
            $filtered[] = $notice;
            $schema['compliance_notes'] = $filtered;
            $svc->update(['schema' => $schema]);
            $updated++;
        }

        $this->command->info("✓ S-03 materials-sample retention notice attached to {$updated} services.");
    }

    /** @return array<string, mixed> */
    private function s03Notice(): array
    {
        return [
            'code'       => 'S-03',
            'source'     => 'كتاب التعليمات الفنية 2025',
            'page'       => 36,
            'category'   => 'retention',
            'label_ar'   => 'المحافظة على العينات لمدة 10 أيام',
            'label_en'   => 'Retain samples for 10 days',
            'body_ar'    => 'المحافظة على الآبار السبرية المحفورة والعينات لمدة عشرة أيام من تاريخ إحضار التقرير إلى النقابة من أجل التدقيق. يُمنع إغلاق موقع العينات أو إتلافها قبل انقضاء المدة.',
            'body_en'    => 'Boreholes and samples must be preserved for ten (10) days from the date the report is submitted to JEA for audit. The sample site must not be closed or samples destroyed before the retention window elapses.',
            'severity'   => 'warning',
        ];
    }
}
