<?php

namespace Database\Seeders;

use Modules\JeaProjects\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds 3 sample projects for the demo applicant so /projects has data.
 * Idempotent — firstOrCreate on (owner_user_id, name_ar).
 *
 * Run: php artisan db:seed --class=SampleProjectsSeeder
 */
class SampleProjectsSeeder extends Seeder
{
    public function run(): void
    {
        $applicant = User::where('email', 'ahmed@demo.esp')->first();
        if (!$applicant) {
            $this->command->error('Applicant ahmed@demo.esp not found. Run DemoSeeder first.');
            return;
        }

        $samples = [
            [
                'name_ar'     => 'إسكان حسين',
                'name_en'     => 'Hussein Housing',
                'type'        => 'سكني',
                'area_m2'     => 120,
                'city'        => 'اربد',
                'contract_no' => '2628700029',
                'request_no'  => '541320',
                'status'      => 'active',
            ],
            [
                'name_ar'     => 'عمارة البنك الإسلامي',
                'name_en'     => 'Islamic Bank Building',
                'type'        => 'تجاري',
                'area_m2'     => 850,
                'city'        => 'عمان',
                'contract_no' => '2628700045',
                'request_no'  => '541198',
                'status'      => 'pending',
            ],
            [
                'name_ar'     => 'مدرسة الأمة',
                'name_en'     => 'Al-Omma School',
                'type'        => 'حكومي',
                'area_m2'     => 1200,
                'city'        => 'الزرقاء',
                'contract_no' => '2628700061',
                'request_no'  => null,
                'status'      => 'pending',
            ],
        ];

        foreach ($samples as $data) {
            Project::firstOrCreate(
                ['owner_user_id' => $applicant->id, 'name_ar' => $data['name_ar']],
                [
                    ...$data,
                    'organization_id' => $applicant->organization_id,
                    'owner_user_id'   => $applicant->id,
                ]
            );
        }

        $this->command->info('✓ Seeded ' . count($samples) . ' sample projects for ahmed@demo.esp.');
    }
}
