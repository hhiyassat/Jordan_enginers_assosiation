<?php

namespace Modules\JeaServices\Database\Seeders;

use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * ExcavationFeeSeeder — JORD-64 (Rule F-03)
 *
 * The JEA 2025 manual (p. 40) prices excavation-support (shoring)
 * services:
 *
 *   "اعتماد الحد الأدنى لأتعاب التصميم (3.5 دينار) للمتر المربع
 *    من أعمال التدعيم ... رسوم بدل أتعاب لجان التدقيق بقيمة
 *    (500 فلس) عن كل م² من المساحة المراد تدعيمها وبحد أقصى
 *    (5000 دينار)"
 *
 * That's three rates on the same area basis:
 *   • Design fee:       3.5 JOD/m²  (uncapped)
 *   • Supervision fee:  2.5 JOD/m²  (separate service, deferred)
 *   • Review committee: 0.5 JOD/m²  (capped at 5000 JOD)
 *
 * Two SRV services carry the excavation-support workflow:
 *   • SRV-007  تقارير تدعيم الحفريات — تصميم وإشراف
 *   • SRV-012  تقارير الحفريات — تصميم وإشراف
 *
 * This seeder sets the DESIGN fee (3.5 JOD/m²) — the primary line
 * item the applicant pays at submission. The review-committee cap
 * (0.5 JOD/m² max 5000) and the supervision half (2.5 JOD/m²) are
 * distinct billable events; both belong on downstream services /
 * add-ons and are Phase 3 material.
 *
 * Idempotent — replaces schema.fee wholesale + appends the area field
 * (replacing any prior area_m2 entry by id).
 */
class ExcavationFeeSeeder extends Seeder
{
    private const EXCAVATION_CODES = ['SRV-007', 'SRV-012'];

    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        $updated = 0;
        foreach (self::EXCAVATION_CODES as $code) {
            $svc = ServiceDefinition::where('organization_id', $org->id)
                ->where('code', $code)->first();
            if (!$svc) continue;

            $schema = $svc->schema ?? [];
            $schema['fields'] = $this->mergeAreaField($schema['fields'] ?? []);
            $schema['fee']    = $this->designFee();
            $svc->update(['schema' => $schema]);
            $updated++;
        }

        $this->command->info("✓ Excavation-support design fee (3.5 JOD/m², JEA p. 40) "
            . "applied to {$updated} services.");
    }

    /** @return array<string, mixed> */
    private function designFee(): array
    {
        return [
            'type'     => 'per_unit',
            'basis'    => 'area_m2',
            'rate'     => 3.5,
            'currency' => 'JOD',
            'source'   => 'كتاب التعليمات الفنية 2025 ص 40',
        ];
    }

    /**
     * @param  list<array<string,mixed>> $existing
     * @return list<array<string,mixed>>
     */
    private function mergeAreaField(array $existing): array
    {
        $kept = array_values(array_filter(
            $existing,
            fn ($f) => ($f['id'] ?? null) !== 'area_m2',
        ));
        $kept[] = [
            'id'       => 'area_m2',
            'label_ar' => 'مساحة التدعيم (م²)',
            'label_en' => 'Shoring Area (m²)',
            'type'     => 'number',
            'required' => true,
            'min'      => 1,
        ];
        return $kept;
    }
}
