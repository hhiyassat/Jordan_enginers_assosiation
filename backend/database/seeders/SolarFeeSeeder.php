<?php

namespace Database\Seeders;

use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * SolarFeeSeeder — JORD-64 (Rule F-02)
 *
 * The JEA 2025 manual pins solar-PV design/supervision at
 * 4 JOD/kW (p. 71-72, 92):
 *
 *   "الحد الأدنى لأتعاب مشاريع الطاقة الشمسية 4 دنانير/كيلو واط
 *    (تصميم + إشراف)"
 *
 * DRW-P-006 (مخططات مشاريع الطاقة / Energy Projects Drawings)
 * currently carries the JORD-63 governorate×building matrix, which
 * is wrong for solar — solar fees scale with generation capacity,
 * not floor area. This seeder overrides that with a `per_unit` fee
 * on the `capacity_kw` form field.
 *
 * Runs AFTER DrawingFeeMatrixSeeder so the matrix fields (governorate,
 * building_class, area_m2) stay in place (JEA still wants those on
 * the record) but the fee block is replaced. The matrix seeder is
 * idempotent and only reads the fee block by shape, so re-running
 * this seeder is safe.
 *
 * Note: the 4 JOD/kW figure combines design + supervision per the
 * manual — no bifurcation like the p.92 drawings matrix, which quotes
 * separate design + supervision columns. Solar's line just says the
 * total minimum floor is 4/kW.
 */
class SolarFeeSeeder extends Seeder
{
    private const SOLAR_SERVICE_CODE = 'DRW-P-006';

    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        $svc = ServiceDefinition::where('organization_id', $org->id)
            ->where('code', self::SOLAR_SERVICE_CODE)->first();
        if (!$svc) {
            $this->command->warn(self::SOLAR_SERVICE_CODE . ' not found — skipping solar fee seed.');
            return;
        }

        $schema = $svc->schema ?? [];
        $schema['fields'] = $this->mergeCapacityField($schema['fields'] ?? []);
        $schema['fee']    = $this->perUnitFee();
        $svc->update(['schema' => $schema]);

        $this->command->info('✓ Solar fee (4 JOD/kW, JEA p. 71-72) applied to '
            . self::SOLAR_SERVICE_CODE . '.');
    }

    /** @return array<string, mixed> */
    private function perUnitFee(): array
    {
        return [
            'type'     => 'per_unit',
            'basis'    => 'capacity_kw',
            'rate'     => 4.0,
            'currency' => 'JOD',
            // The manual doesn't pin a solar cap — leave uncapped.
            // Very large installations pay 4 × capacity linearly.
            'source'   => 'كتاب التعليمات الفنية 2025 ص 71-72',
        ];
    }

    /**
     * Add the capacity_kw field (idempotent — replaces any prior entry).
     * Kept alongside the governorate/building_class/area_m2 fields the
     * drawings matrix seeder added; JEA still wants those on the record
     * even when the fee doesn't consume them.
     *
     * @param  list<array<string,mixed>> $existing
     * @return list<array<string,mixed>>
     */
    private function mergeCapacityField(array $existing): array
    {
        $kept = array_values(array_filter(
            $existing,
            fn ($f) => ($f['id'] ?? null) !== 'capacity_kw',
        ));
        $kept[] = [
            'id'       => 'capacity_kw',
            'label_ar' => 'الاستطاعة (كيلو واط)',
            'label_en' => 'Capacity (kW)',
            'type'     => 'number',
            'required' => true,
            'min'      => 1,
        ];
        return $kept;
    }
}
