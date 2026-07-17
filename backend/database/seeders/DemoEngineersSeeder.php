<?php

namespace Database\Seeders;

use App\Models\Engineer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds 3 demo engineers under the demo applicant office
 * (ahmed@demo.esp) and backfills the 3 sample projects so each is
 * attributed to a specific engineer.
 *
 * Idempotent: firstOrCreate on (office_user_id, membership_number).
 */
class DemoEngineersSeeder extends Seeder
{
    public function run(): void
    {
        $office = User::where('email', 'ahmed@demo.esp')->first();
        if (!$office) {
            $this->command->error('Demo office user (ahmed@demo.esp) not found. Run DemoSeeder first.');
            return;
        }

        $engineers = [
            [
                'name_ar'           => 'م. أحمد الزعبي',
                'name_en'           => 'Eng. Ahmed Al-Zoubi',
                'membership_number' => '2870',
                'specialization'    => 'civil',
                'annual_quota_m2'   => 2500,
            ],
            [
                'name_ar'           => 'م. سارة عبد الله',
                'name_en'           => 'Eng. Sara Abdullah',
                'membership_number' => '3145',
                'specialization'    => 'architectural',
                'annual_quota_m2'   => 1500,
            ],
            [
                'name_ar'           => 'م. عمر الشريف',
                'name_en'           => 'Eng. Omar Al-Sharif',
                'membership_number' => '4028',
                'specialization'    => 'electrical',
                'annual_quota_m2'   => 1200,
            ],
        ];

        $created = [];
        foreach ($engineers as $data) {
            $created[$data['membership_number']] = Engineer::firstOrCreate(
                ['office_user_id' => $office->id, 'membership_number' => $data['membership_number']],
                [
                    ...$data,
                    'organization_id' => $office->organization_id,
                    'office_user_id'  => $office->id,
                    'is_active'       => true,
                ]
            );
        }

        // Backfill existing projects to engineers by name match.
        // إسكان حسين → Ahmed (2870), عمارة البنك الإسلامي → Sara (3145),
        // مدرسة الأمة → Omar (4028).
        $assignments = [
            'إسكان حسين'            => '2870',
            'عمارة البنك الإسلامي'   => '3145',
            'مدرسة الأمة'            => '4028',
        ];

        $backfilled = 0;
        foreach ($assignments as $nameAr => $memberNo) {
            $project = Project::where('owner_user_id', $office->id)
                ->where('name_ar', $nameAr)
                ->first();
            if ($project && !$project->engineer_id && isset($created[$memberNo])) {
                $project->engineer_id = $created[$memberNo]->id;
                $project->save();
                $backfilled++;
            }
        }

        $this->command->info('✓ Seeded ' . count($engineers) . ' engineers under office ' . $office->email);
        $this->command->info("✓ Backfilled {$backfilled} existing projects to engineers.");
    }
}
