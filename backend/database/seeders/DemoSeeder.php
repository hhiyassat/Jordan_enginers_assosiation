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

        // Create demo users — 4 role-scoped demos + 1 superuser
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

        // Superuser — created with a shared bootstrap password AND the
        // must_change_password flag flipped. On first login the change-password
        // endpoint lets a superuser change BOTH email and password (see
        // AuthController::changePassword), and after the flag flips off every
        // subsequent API rotation is refused: only `php artisan user:credentials`
        // can rotate the superuser's credentials from then on.
        User::create([
            'organization_id'      => $org->id,
            'name'                 => 'المستخدم الأعلى',
            'email'                => 'hhiyassat@eqratech.com',
            'role'                 => 'superuser',
            'password'             => Hash::make('796080604Hh%%'),
            'must_change_password' => true,
            'is_active'            => true,
        ]);

        // Business License (BL-001) was retired — it fell outside the
        // seven JEA portal tiles as the catalog matured. The defaultSchema()
        // helper below is kept for reference in case a similar standalone
        // service needs a starting point.

        $this->command->info('✓ Demo organization created: demo');
        $this->command->info('✓ Users: admin@demo.esp / staff@demo.esp / auditor@demo.esp / ahmed@demo.esp');
        $this->command->info('✓ Password: Demo1234!');
        $this->command->info('✓ Superuser: hhiyassat@eqratech.com (bootstrap: 796080604Hh%%, must change on first login)');
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
