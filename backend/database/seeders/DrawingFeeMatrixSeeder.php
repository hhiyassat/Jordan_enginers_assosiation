<?php

namespace Database\Seeders;

use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * DrawingFeeMatrixSeeder — JORD-63
 *
 * Applies the JEA 2025 manual (p. 92) minimum-fee matrix to every
 * DRW-P-* service (12 drawing services). Each service gets:
 *   • Three new required form fields:
 *       - governorate     (select, 12 options)
 *       - building_class  (select, 4 options — matches the manual's
 *                          4 rate rows)
 *       - area_m2         (number, > 0)
 *   • A `matrix` fee block that reduces the 12-value governorate to
 *     the two rate zones the manual actually prices (Amman greater
 *     municipality vs rest of country) and looks up JOD/m² × area.
 *
 * The manual rate quote (p. 92, design column):
 *   Amman: green/commercial 3.5 · private 5.0 · residential (c+d) 2.5 · rural/shaabi 2.0
 *   Rest:  green/commercial 2.5 · private 4.0 · residential (c+d) 2.0 · rural/shaabi 1.5
 *
 * The supervision-column rates are the same values (design + supervision
 * both quoted at 3.5 in Amman green/commercial, etc.). Applying the
 * supervision half is a separate concern — belongs on the supervision-
 * contract renewal service (Phase 3+), not on the drawings-approval
 * fee block.
 *
 * Runs after DrawingValiditySeeder (JORD-58). Writes only:
 *   • schema.fields[]  — appends the 3 form fields (idempotent)
 *   • schema.fee       — replaces wholesale with the matrix block
 * Everything else (workflow, documents, certificate) survives.
 */
class DrawingFeeMatrixSeeder extends Seeder
{
    private const DRAWING_CODES = [
        'DRW-P-001', 'DRW-P-002', 'DRW-P-003', 'DRW-P-004', 'DRW-P-005',
        'DRW-P-006', 'DRW-P-007', 'DRW-P-008', 'DRW-P-009', 'DRW-P-010',
        'DRW-P-011', 'DRW-P-012',
    ];

    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        $updated = 0;
        foreach (self::DRAWING_CODES as $code) {
            $svc = ServiceDefinition::where('organization_id', $org->id)
                ->where('code', $code)->first();
            if (!$svc) continue;

            $schema = $svc->schema ?? [];
            $schema['fields'] = $this->mergeFields($schema['fields'] ?? []);
            $schema['fee']    = $this->matrixFee();
            $svc->update(['schema' => $schema]);
            $updated++;
        }

        $this->command->info("✓ Fee matrix (JEA p. 92) applied to {$updated} drawing services.");
    }

    /**
     * Append the 3 fields required by the matrix, replacing any prior
     * entries with the same id (idempotency). Existing user-authored
     * fields stay in place so a bespoke DRW-P-* schema doesn't lose
     * its custom fields on re-run.
     *
     * @param  list<array<string,mixed>> $existing
     * @return list<array<string,mixed>>
     */
    private function mergeFields(array $existing): array
    {
        $newIds = ['governorate', 'building_class', 'area_m2'];
        $kept = array_values(array_filter(
            $existing,
            fn ($f) => !in_array($f['id'] ?? null, $newIds, true),
        ));
        return array_merge($kept, [
            $this->governorateField(),
            $this->buildingClassField(),
            $this->areaField(),
        ]);
    }

    /** @return array<string, mixed> */
    private function matrixFee(): array
    {
        return [
            'type' => 'matrix',
            'keys' => ['governorate', 'building_class'],
            // Collapse the 12 governorates to the manual's 2 rate zones.
            // Amman = Amman greater municipality; everything else = rest
            // of country. If JEA later prices more granularly per
            // governorate, we just extend the map + rates block.
            'buckets' => [
                'governorate' => [
                    'amman'   => 'amman',
                    'irbid'   => 'other', 'zarqa'   => 'other', 'mafraq'  => 'other',
                    'balqa'   => 'other', 'karak'   => 'other', 'maan'    => 'other',
                    'tafilah' => 'other', 'aqaba'   => 'other', 'madaba'  => 'other',
                    'jerash'  => 'other', 'ajloun'  => 'other',
                ],
            ],
            'rates' => [
                'amman|green_commercial' => 3.5,
                'amman|private'          => 5.0,
                'amman|residential_cd'   => 2.5,
                'amman|rural_shaabi'     => 2.0,
                'other|green_commercial' => 2.5,
                'other|private'          => 4.0,
                'other|residential_cd'   => 2.0,
                'other|rural_shaabi'     => 1.5,
            ],
            'basis'    => 'area_m2',
            'default'  => 0,
            'currency' => 'JOD',
            'source'   => 'كتاب التعليمات الفنية 2025 ص 92',
        ];
    }

    /** @return array<string, mixed> */
    private function governorateField(): array
    {
        return [
            'id'       => 'governorate',
            'label_ar' => 'المحافظة',
            'label_en' => 'Governorate',
            'type'     => 'select',
            'required' => true,
            'options'  => [
                ['value' => 'amman',   'label_ar' => 'عمان',    'label_en' => 'Amman'],
                ['value' => 'irbid',   'label_ar' => 'إربد',    'label_en' => 'Irbid'],
                ['value' => 'zarqa',   'label_ar' => 'الزرقاء', 'label_en' => 'Zarqa'],
                ['value' => 'mafraq',  'label_ar' => 'المفرق',  'label_en' => 'Mafraq'],
                ['value' => 'balqa',   'label_ar' => 'البلقاء', 'label_en' => 'Balqa'],
                ['value' => 'karak',   'label_ar' => 'الكرك',   'label_en' => 'Karak'],
                ['value' => 'maan',    'label_ar' => 'معان',    'label_en' => "Ma'an"],
                ['value' => 'tafilah', 'label_ar' => 'الطفيلة', 'label_en' => 'Tafilah'],
                ['value' => 'aqaba',   'label_ar' => 'العقبة',  'label_en' => 'Aqaba'],
                ['value' => 'madaba',  'label_ar' => 'مأدبا',   'label_en' => 'Madaba'],
                ['value' => 'jerash',  'label_ar' => 'جرش',     'label_en' => 'Jerash'],
                ['value' => 'ajloun',  'label_ar' => 'عجلون',   'label_en' => 'Ajloun'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function buildingClassField(): array
    {
        return [
            'id'       => 'building_class',
            'label_ar' => 'صنف المبنى',
            'label_en' => 'Building Class',
            'type'     => 'select',
            'required' => true,
            'options'  => [
                [
                    'value'    => 'green_commercial',
                    'label_ar' => 'سكن أخضر أو تجاري (أ+ب)',
                    'label_en' => 'Green residential or commercial (A/B)',
                ],
                [
                    'value'    => 'private',
                    'label_ar' => 'مباني خاصة',
                    'label_en' => 'Private buildings',
                ],
                [
                    'value'    => 'residential_cd',
                    'label_ar' => 'سكن (ج+د)',
                    'label_en' => 'Residential (C/D)',
                ],
                [
                    'value'    => 'rural_shaabi',
                    'label_ar' => 'شعبي · ريفي · زراعي',
                    'label_en' => 'Rural / shaabi / agricultural',
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function areaField(): array
    {
        return [
            'id'       => 'area_m2',
            'label_ar' => 'المساحة (م²)',
            'label_en' => 'Area (m²)',
            'type'     => 'number',
            'required' => true,
            'min'      => 1,
        ];
    }
}
