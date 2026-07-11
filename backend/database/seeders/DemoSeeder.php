<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo organization
        $org = Organization::create([
            'name_ar'   => 'منظمة تجريبية',
            'name_en'   => 'Demo Organization',
            'slug'      => 'demo',
            'is_active' => true,
        ]);

        // Create demo users — 4 roles
        $users = [
            ['name' => 'مدير النظام',    'email' => 'admin@demo.esp',   'role' => 'admin'],
            ['name' => 'موظف المراجعة',  'email' => 'staff@demo.esp',   'role' => 'staff'],
            ['name' => 'المدقق القانوني', 'email' => 'auditor@demo.esp', 'role' => 'auditor'],
            ['name' => 'أحمد المقدم',    'email' => 'ahmed@demo.esp',   'role' => 'applicant'],
        ];

        foreach ($users as $u) {
            User::create([
                ...$u,
                'organization_id'    => $org->id,
                'password'           => Hash::make('Demo1234!'),
                'password_changed_at' => now(),
                'is_active'          => true,
            ]);
        }

        // Load Business License schema — backend/ is one level below esp-v2/
        $schemaPath = base_path('../schemas/business-license.json');

        $schema = file_exists($schemaPath)
            ? json_decode(file_get_contents($schemaPath), true)
            : $this->defaultSchema();

        ServiceDefinition::create([
            'organization_id' => $org->id,
            'code'            => 'BL-001',
            'name_ar'         => 'رخصة تجارية',
            'name_en'         => 'Business License',
            'description_ar'  => 'طلب الحصول على رخصة تجارية لمزاولة النشاط التجاري',
            'description_en'  => 'Apply for a business license to operate a commercial activity',
            'currency'        => 'JOD',
            'schema'          => $schema,
            'status'          => 'active',
        ]);

        $this->command->info('✓ Demo organization created: demo');
        $this->command->info('✓ Users: admin@demo.esp / staff@demo.esp / auditor@demo.esp / ahmed@demo.esp');
        $this->command->info('✓ Password: Demo1234!');
        $this->command->info('✓ Service: BL-001 رخصة تجارية (active)');
    }

    private function defaultSchema(): array
    {
        return [
            'service_code' => 'BL-001',
            'name_ar' => 'رخصة تجارية',
            'name_en' => 'Business License',
            'workflow' => [
                'stages' => [
                    ['id' => 'initial_review', 'label_ar' => 'المراجعة الأولية', 'label_en' => 'Initial Review', 'role' => 'staff', 'sla_hours' => 24, 'actions' => ['approve', 'reject', 'request_modifications']],
                ],
            ],
            'fee' => ['type' => 'fixed', 'amount' => 100, 'currency' => 'JOD'],
            'fields' => [
                ['id' => 'business_name_ar', 'label_ar' => 'اسم المنشأة', 'label_en' => 'Business Name', 'type' => 'text', 'required' => true, 'max_length' => 255, 'section' => 'business_info'],
                ['id' => 'owner_name',        'label_ar' => 'اسم المالك', 'label_en' => 'Owner Name',    'type' => 'text', 'required' => true, 'section' => 'owner_info'],
            ],
            'sections' => [
                ['id' => 'business_info', 'label_ar' => 'معلومات المنشأة', 'label_en' => 'Business Info'],
                ['id' => 'owner_info',    'label_ar' => 'معلومات المالك',  'label_en' => 'Owner Info'],
            ],
            'documents' => [],
            'certificate' => ['validity_months' => 12, 'title_ar' => 'رخصة تجارية', 'title_en' => 'Business License', 'fields_on_cert' => ['business_name_ar', 'owner_name']],
        ];
    }
}
