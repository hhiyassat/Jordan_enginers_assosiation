<?php

namespace Database\Seeders;

use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * FeeSurchargesSeeder — JORD-65 (Rule F-06)
 *
 * The JEA 2025 manual (p. 96) pins two per-transaction surcharges on
 * every fee-bearing service:
 *
 *   Manual (p. 96):
 *     "(1) رسم أتعاب من إجمالي الأتعاب الهندسية ...
 *      رسم ممارسة مهنة عن تدقيق مخططات ودراسات ... (40) فلساً لكل
 *      متر مربع ... (150) فلساً لكل متر طولي عن أعمال استطلاع الموقع
 *      وفحص التربة."
 *
 * Which translates to three surcharges on top of the base fee:
 *   • 1% syndicate fee on the base fee amount (percent_of_base).
 *   • 40 fils/m² drawing-review fee for services keyed on area_m2.
 *   • 150 fils/lm site-survey fee for services keyed on length_lm.
 *
 * This seeder handles the first two. The 150 fils/lm surcharge waits
 * for site-survey services (SRV-001..006) to gain their own length_lm
 * base fee first — it's meaningless as a surcharge on a service
 * without a length dimension.
 *
 * Applies to:
 *   • Every DRW-P-* (12 drawing services) — carries both 1% and
 *     40 fils/m² since they have area_m2 as a form field (from
 *     DrawingFeeMatrixSeeder). Solar (DRW-P-006) also gets both;
 *     its area_m2 field is still present from the matrix seeder
 *     even though its base fee reads capacity_kw.
 *   • SRV-007, SRV-012 (excavation-support) — carries only 1% since
 *     they have area_m2 too but the 40 fils/m² surcharge is per the
 *     manual specifically for drawing-review, not shoring.
 *
 * Idempotent: replaces schema.fee.surcharges wholesale so re-runs
 * converge on the canonical list. Any per-service override an admin
 * makes via the schema editor is reset by re-runs (source-of-truth
 * seeder).
 */
class FeeSurchargesSeeder extends Seeder
{
    private const DRAWING_CODES = [
        'DRW-P-001', 'DRW-P-002', 'DRW-P-003', 'DRW-P-004', 'DRW-P-005',
        'DRW-P-006', 'DRW-P-007', 'DRW-P-008', 'DRW-P-009', 'DRW-P-010',
        'DRW-P-011', 'DRW-P-012',
    ];

    private const EXCAVATION_CODES = ['SRV-007', 'SRV-012'];

    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        $drawings = 0;
        foreach (self::DRAWING_CODES as $code) {
            if ($this->attach($org->id, $code, [
                $this->syndicate1PercentSurcharge(),
                $this->drawingReviewSurcharge(),
            ])) $drawings++;
        }

        $excavation = 0;
        foreach (self::EXCAVATION_CODES as $code) {
            if ($this->attach($org->id, $code, [
                $this->syndicate1PercentSurcharge(),
            ])) $excavation++;
        }

        $this->command->info("✓ Surcharges attached: {$drawings} drawings services (1% + 40 fils/m²), "
            . "{$excavation} excavation services (1% only).");
    }

    /**
     * @param  list<array<string,mixed>> $surcharges
     */
    private function attach(int $orgId, string $code, array $surcharges): bool
    {
        $svc = ServiceDefinition::where('organization_id', $orgId)
            ->where('code', $code)->first();
        if (!$svc) return false;

        $schema = $svc->schema ?? [];
        // Guard: only attach when the service already has a fee block.
        // Attaching surcharges to a service without a base fee produces
        // a zero-base + surcharge-only total, which is nonsensical.
        if (empty($schema['fee'])) return false;

        $schema['fee']['surcharges'] = $surcharges;
        $svc->update(['schema' => $schema]);
        return true;
    }

    /** @return array<string, mixed> */
    private function syndicate1PercentSurcharge(): array
    {
        return [
            'code'     => 'syndicate_1pct',
            'kind'     => 'percent_of_base',
            'rate'     => 0.01,   // 1% of base fee
            'label_ar' => 'رسم النقابة (1%)',
            'label_en' => 'Syndicate Fee (1%)',
            'source'   => 'كتاب التعليمات الفنية 2025 ص 96',
        ];
    }

    /** @return array<string, mixed> */
    private function drawingReviewSurcharge(): array
    {
        return [
            'code'     => 'drawing_review_40fils',
            'kind'     => 'per_unit',
            'basis'    => 'area_m2',
            'rate'     => 0.04,   // 40 fils = 0.040 JOD per m²
            'label_ar' => 'رسم تدقيق المخططات (40 فلس/م²)',
            'label_en' => 'Drawing Review (40 fils/m²)',
            'source'   => 'كتاب التعليمات الفنية 2025 ص 96',
        ];
    }
}
