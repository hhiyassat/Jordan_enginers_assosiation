<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * JeaDrawingsSeeder
 *
 * Seeds the seven "Engineering Drawings Approval" services that live under
 * the مشاريعي (JEA-PROJ) folder tile. Each is a real applicable service —
 * clicking مشاريعي on /services should open a sub-view listing these.
 *
 * Idempotent: firstOrCreate on (organization_id, code).
 * Run: php artisan db:seed --class=JeaDrawingsSeeder
 */
class JeaDrawingsSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        foreach ($this->drawings() as $svc) {
            ServiceDefinition::firstOrCreate(
                ['organization_id' => $org->id, 'code' => $svc['code']],
                [
                    ...$svc,
                    'organization_id' => $org->id,
                    'parent_code'     => 'JEA-PROJ',
                    'currency'        => 'JOD',
                    'status'          => 'active',
                    'schema'          => $this->placeholderSchema($svc),
                ]
            );
        }

        $this->command->info('✓ Seeded ' . count($this->drawings()) . ' drawing services under JEA-PROJ.');
    }

    private function drawings(): array
    {
        // sla_hours = midpoint of the day range × 24. Display strings live in
        // the frontend which will format sla_hours back into a day range.
        return [
            ['code' => 'DRW-001', 'name_ar' => 'تصديق مخططات المباني السكنية',      'name_en' => 'Residential Building Drawings',   'base_fee' => 120, 'sla_hours' => 96],   // 3–5 أيام
            ['code' => 'DRW-002', 'name_ar' => 'تصديق مخططات المباني التجارية',      'name_en' => 'Commercial Building Drawings',    'base_fee' => 200, 'sla_hours' => 144],  // 5–7 أيام
            ['code' => 'DRW-003', 'name_ar' => 'تصديق مخططات المباني الصناعية',      'name_en' => 'Industrial Building Drawings',    'base_fee' => 250, 'sla_hours' => 204],  // 7–10 أيام
            ['code' => 'DRW-004', 'name_ar' => 'تصديق مخططات التعديلات والإضافات',   'name_en' => 'Modification & Addition Drawings','base_fee' =>  80, 'sla_hours' => 60],   // 2–3 أيام
            ['code' => 'DRW-005', 'name_ar' => 'تصديق مخططات إعادة الترميم',          'name_en' => 'Renovation Drawings',             'base_fee' =>  90, 'sla_hours' => 84],   // 3–4 أيام
            ['code' => 'DRW-006', 'name_ar' => 'تصديق مخططات البنية التحتية',         'name_en' => 'Infrastructure Drawings',         'base_fee' => 300, 'sla_hours' => 288],  // 10–14 أيام
            ['code' => 'DRW-007', 'name_ar' => 'تصديق مخططات المباني الحكومية',       'name_en' => 'Government Buildings Drawings',   'base_fee' => 350, 'sla_hours' => 336],  // 14 أيام (placeholder)
        ];
    }

    private function placeholderSchema(array $svc): array
    {
        return [
            'service_code' => $svc['code'],
            'name_ar'      => $svc['name_ar'],
            'name_en'      => $svc['name_en'],
            'version'      => '0.1-placeholder',
            'workflow'     => [
                'stages' => [[
                    'id'        => 'placeholder_review',
                    'label_ar'  => 'مراجعة أولية',
                    'label_en'  => 'Placeholder Review',
                    'role'      => 'staff',
                    'sla_hours' => $svc['sla_hours'],
                    'actions'   => ['approve', 'reject'],
                ]],
            ],
            'fee'         => ['type' => 'fixed', 'amount' => $svc['base_fee'], 'currency' => 'JOD'],
            'sections'    => [],
            'fields'      => [],
            'documents'   => [],
            'certificate' => [
                'validity_months' => 12,
                'title_ar'        => $svc['name_ar'],
                'title_en'        => $svc['name_en'],
                'fields_on_cert'  => [],
            ],
        ];
    }
}
