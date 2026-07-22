<?php

namespace Database\Seeders;

use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * SiteSurveyFeesSeeder — JORD-78 (partial F-06)
 *
 * The JEA 2025 manual (p. 96) pins two things for site-survey work:
 *
 *   Manual (p. 96):
 *     "(150) فلساً لكل متر طولي عن أعمال استطلاع الموقع وفحص التربة"
 *
 * That's both the base practice fee AND a syndicate surcharge — the
 * manual doesn't cleanly separate them, but the platform models it
 * as one per-lm base fee + JORD-65's 1% syndicate surcharge on top.
 *
 * Before this seeder, SRV-001..006 (site-survey report services) all
 * carried the placeholder `fee: {type: fixed, amount: 0}` — every
 * survey submission produced a zero bill. This seeder:
 *   • Adds a required `length_lm` form field (linear meters of the
 *     survey path).
 *   • Sets fee to per_unit(basis=length_lm, rate=0.15).
 *   • Attaches the 1% syndicate surcharge (JORD-65's kind).
 *
 * Applies to SRV-001..006 only:
 *   • SRV-006 is government-bidder surveys — already has length_lm
 *     from JORD-75. This seeder overwrites the fee block (was fixed 0)
 *     without touching the field list; JORD-75's quota_basis_field
 *     and override survive.
 *   • SRV-007/012 (excavation) already have per_unit on area_m2
 *     from JORD-64 — not touched.
 *   • SRV-008/009 (materials testing) use area_m2 not length_lm —
 *     they stay on their own JORD-74 basis.
 *   • SRV-010/011 (re-approval at half fees) and SRV-013/014
 *     (ancillary) are out of scope; small ancillary services keep
 *     their placeholder until real fee data is available.
 *
 * Idempotent — replaces fee wholesale, appends length_lm by id.
 */
class SiteSurveyFeesSeeder extends Seeder
{
    private const SURVEY_CODES = ['SRV-001', 'SRV-002', 'SRV-003', 'SRV-004', 'SRV-005', 'SRV-006'];

    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        $updated = 0;
        foreach (self::SURVEY_CODES as $code) {
            $svc = ServiceDefinition::where('organization_id', $org->id)
                ->where('code', $code)->first();
            if (!$svc) continue;

            $schema = $svc->schema ?? [];
            $schema['fields'] = $this->ensureLengthField($schema['fields'] ?? []);
            $schema['fee']    = $this->feeBlock();
            $svc->update(['schema' => $schema]);
            $updated++;
        }

        $this->command->info("✓ Site-survey fees (150 fils/lm + 1% syndicate) applied to {$updated} services.");
    }

    /**
     * Append length_lm as a required number field. If a length_lm
     * already exists (SRV-006 from JORD-75) we replace it by id so
     * label + required stay in sync with this seeder's version.
     *
     * @param  list<array<string,mixed>> $existing
     * @return list<array<string,mixed>>
     */
    private function ensureLengthField(array $existing): array
    {
        $kept = array_values(array_filter(
            $existing,
            fn ($f) => ($f['id'] ?? null) !== 'length_lm',
        ));
        $kept[] = [
            'id'       => 'length_lm',
            'label_ar' => 'الطول (م.ط)',
            'label_en' => 'Length (linear meters)',
            'type'     => 'number',
            'required' => true,
            'min'      => 1,
        ];
        return $kept;
    }

    /**
     * per_unit(length_lm, 0.15) + 1% syndicate surcharge.
     * @return array<string, mixed>
     */
    private function feeBlock(): array
    {
        return [
            'type'     => 'per_unit',
            'basis'    => 'length_lm',
            'rate'     => 0.15,   // 150 fils = 0.15 JOD per linear meter
            'currency' => 'JOD',
            'source'   => 'كتاب التعليمات الفنية 2025 ص 96',
            'surcharges' => [[
                'code'     => 'syndicate_1pct',
                'kind'     => 'percent_of_base',
                'rate'     => 0.01,
                'label_ar' => 'رسم النقابة (1%)',
                'label_en' => 'Syndicate Fee (1%)',
                'source'   => 'كتاب التعليمات الفنية 2025 ص 96',
            ]],
        ];
    }
}
