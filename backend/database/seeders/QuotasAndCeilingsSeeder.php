<?php

namespace Database\Seeders;

use Modules\JeaProjects\Engine\Disciplines;
use Modules\JeaProjects\Models\Engineer;
use Modules\JeaProjects\Models\EngineerDisciplineQuota;
use App\Models\Organization;
use Modules\JeaProjects\Models\OfficeCeiling;
use Illuminate\Database\Seeder;

/**
 * QuotasAndCeilingsSeeder — JORD-67
 *
 * Seeds the JEA 2025 Chapter 9 quota + ceiling defaults for the demo
 * organization + its 3 seeded engineers. The values below are the
 * manual's "class B engineer office" tier (the baseline) — a real
 * deployment will override per office classification in the admin UI
 * (Phase 3 Tier B / JORD-70 handles the classification-boost math).
 *
 * Per-engineer quotas (manual p. 124-125, design column):
 *   materials/environmental    → 118,750 m² / year
 *   structural / electrical    →  56,250 m² / year
 *   architectural              →  56,250 m² / year
 *   mechanical                 →  56,250 m² / year
 *
 * Per-office ceilings (manual p. 127, class-B engineer-office):
 *   structural                 →  30,000 m² / year
 *   architectural / electrical → 30,000 m² / year each
 *   mechanical                 →  15,000 m² / year
 *   environmental              →  15,000 m² / year
 *
 * Each engineer only gets a quota for their own declared discipline,
 * plus the alias mapping (civil → structural) so the existing seeded
 * "civil" engineer lands in the structural quota bucket. The
 * organization gets a ceiling for every discipline (offices employ
 * engineers of multiple specialties).
 *
 * Idempotent — updateOrCreate on the composite unique so re-runs
 * converge on the same numbers.
 */
class QuotasAndCeilingsSeeder extends Seeder
{
    /** @var array<string, int> */
    private const ENGINEER_QUOTA = [
        Disciplines::ARCHITECTURAL =>  56250,
        Disciplines::STRUCTURAL    =>  56250,
        Disciplines::ELECTRICAL    =>  56250,
        Disciplines::MECHANICAL    =>  56250,
        Disciplines::ENVIRONMENTAL => 118750,
    ];

    /** @var array<string, int> */
    private const OFFICE_CEILING = [
        Disciplines::ARCHITECTURAL => 30000,
        Disciplines::STRUCTURAL    => 30000,
        Disciplines::ELECTRICAL    => 30000,
        Disciplines::MECHANICAL    => 15000,
        Disciplines::ENVIRONMENTAL => 15000,
    ];

    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        $year = (int) now()->year;

        // JORD-77: seed one office_ceiling row per (office_user × discipline).
        // The "office" is an applicant User; each Organization can host
        // multiple. Every applicant in the org gets the same seeded
        // defaults; admins can override per-office via the admin UI.
        $applicants = \App\Models\User::where('organization_id', $org->id)
            ->where('role', 'applicant')->get();
        $officeCount = 0;
        foreach ($applicants as $applicant) {
            foreach (self::OFFICE_CEILING as $discipline => $m2) {
                OfficeCeiling::updateOrCreate(
                    [
                        'office_user_id' => $applicant->id,
                        'discipline'     => $discipline,
                        'year'           => $year,
                    ],
                    [
                        'organization_id' => $org->id, // denorm for legacy queries
                        'm2_allowed'      => $m2,
                    ],
                );
                $officeCount++;
            }
        }

        // Engineer quotas — one row per engineer keyed on their normalized
        // discipline. Engineers with unknown / null specialization are
        // skipped (a warning surfaces so ops can backfill).
        $engineerCount = 0;
        $skipped = [];
        foreach (Engineer::where('organization_id', $org->id)->get() as $eng) {
            $raw = (string) ($eng->specialization ?? '');
            if ($raw === '') {
                $skipped[] = $eng->membership_number ?: (string) $eng->id;
                continue;
            }
            $discipline = Disciplines::normalize($raw);
            if (!array_key_exists($discipline, self::ENGINEER_QUOTA)) {
                $skipped[] = $eng->membership_number ?: (string) $eng->id;
                continue;
            }
            EngineerDisciplineQuota::updateOrCreate(
                ['engineer_id' => $eng->id, 'discipline' => $discipline, 'year' => $year],
                ['m2_allowed' => self::ENGINEER_QUOTA[$discipline]],
            );
            $engineerCount++;
        }

        $this->command->info("✓ Seeded {$officeCount} office ceilings and {$engineerCount} engineer quotas for year {$year}.");
        if ($skipped) {
            $this->command->warn('Skipped engineers with unknown discipline: ' . implode(', ', $skipped));
        }
    }
}
