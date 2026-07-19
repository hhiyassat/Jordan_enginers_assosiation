<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * JeaPortalTilesSeeder
 *
 * Placeholder services that mirror the six top-level categories shown on the
 * JEA e-services portal design (Certificates, Financial Services, My Projects,
 * Miscellaneous Services, Board Decisions, Engineers in Offices).
 *
 * Each is seeded as an *active* service with a minimal placeholder schema —
 * enough to satisfy the JSON `schema` column and render on /services, but
 * without a real workflow. Refine the schemas later via the AI generator
 * (POST /api/v1/admin/services/generate-schema-from-file) or the admin UI.
 *
 * Idempotent: uses firstOrCreate on (organization_id, code).
 *
 * Run: php artisan db:seed --class=JeaPortalTilesSeeder
 */
class JeaPortalTilesSeeder extends Seeder
{
    public function run(): void
    {
        // Attach to the demo org so ahmed@demo.esp sees them on /services.
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        foreach ($this->tiles() as $tile) {
            ServiceDefinition::firstOrCreate(
                ['organization_id' => $org->id, 'code' => $tile['code']],
                [
                    ...$tile,
                    'organization_id' => $org->id,
                    'currency'        => 'JOD',
                    'status'          => 'active',
                    'schema'          => $this->placeholderSchema($tile['code'], $tile['name_ar'], $tile['name_en']),
                ]
            );
        }

        $this->command->info('✓ Seeded ' . count($this->tiles()) . ' JEA portal placeholder services under org "demo".');
    }

    private function tiles(): array
    {
        return [
            [
                'code'           => 'JEA-CERT',
                'name_ar'        => 'الشهادات',
                'name_en'        => 'Certificates',
                'description_ar' => 'شهادات الخبرة والانتساب ومزاولة المهنة',
                'description_en' => 'Experience, membership, and practice-license certificates',
            ],
            [
                'code'           => 'JEA-FIN',
                'name_ar'        => 'الخدمات المالية',
                'name_en'        => 'Financial Services',
                'description_ar' => 'الرسوم والفواتير والمدفوعات الإلكترونية',
                'description_en' => 'Fees, invoices, and electronic payments',
            ],
            [
                'code'           => 'JEA-PROJ',
                'name_ar'        => 'خدمات تصديق المخططات الهندسية',
                'name_en'        => 'Engineering Drawings Certification',
                'description_ar' => 'تصديق مخططات الأبنية والمشاريع الكبرى ومشاريع الطاقة والدفاع المدني',
                'description_en' => 'Certify building drawings, large projects, energy projects, and civil defense',
            ],
            [
                'code'           => 'JEA-MISC',
                'name_ar'        => 'خدمات أخرى',
                'name_en'        => 'Other Services',
                'description_ar' => 'استعلامات عامة وتحديث البيانات والشكاوى',
                'description_en' => 'General inquiries, data updates, and complaints',
            ],
            [
                'code'           => 'JEA-DEC',
                'name_ar'        => 'قرارات هيئة المكاتب الهندسية',
                'name_en'        => 'Board Decisions',
                'description_ar' => 'الاطلاع على القرارات وتقديم الاعتراضات',
                'description_en' => 'Review board decisions and file objections',
            ],
            [
                'code'           => 'JEA-ENG',
                'name_ar'        => 'المهندسين العاملين في المكاتب الهندسية',
                'name_en'        => 'Engineers Working in Engineering Offices',
                'description_ar' => 'تسجيل وإدارة المهندسين العاملين',
                'description_en' => 'Register and manage engineers employed at offices',
            ],
        ];
    }

    private function placeholderSchema(string $code, string $nameAr, string $nameEn): array
    {
        return [
            'service_code' => $code,
            'name_ar'      => $nameAr,
            'name_en'      => $nameEn,
            'version'      => '0.1-placeholder',
            'workflow'     => [
                'stages' => [[
                    'id'        => 'placeholder_review',
                    'label_ar'  => 'مراجعة أولية',
                    'label_en'  => 'Placeholder Review',
                    'role'      => 'staff',
                    'sla_hours' => 24,
                    'actions'   => ['approve', 'reject'],
                ]],
            ],
            'fee'         => ['type' => 'fixed', 'amount' => 0, 'currency' => 'JOD'],
            'sections'    => [],
            'fields'      => [],
            'documents'   => [],
            'certificate' => [
                'validity_months' => 0,
                'title_ar'        => $nameAr,
                'title_en'        => $nameEn,
                'fields_on_cert'  => [],
            ],
        ];
    }
}
