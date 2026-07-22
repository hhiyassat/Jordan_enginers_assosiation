<?php

namespace Database\Seeders;

use Modules\JeaProjects\Models\OfficeCeiling;
use App\Models\Organization;
use App\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * GovernmentSurveyQuotaSeeder — JORD-75 (Rule Q-03)
 *
 * The JEA 2025 manual (p. 125) pins per-office annual linear-meter
 * caps for government-bidder site-survey services:
 *
 *   "الحصص السنوية … مكاتب الدرجة الأولى 2500 م.ط،
 *    الثانية 2000، الثالثة 1500"
 *
 * SRV-006 (تقارير استطلاع الموقع للمشاريع الحكومية) is the only
 * service quota'd this way — the class distinction is per-office
 * classification, not per-service.
 *
 * Unlike materials-testing (JORD-74) which uses area_m2, this one
 * uses linear meters (length_lm). JORD-75 extended CapacityGuard +
 * QuotaLedger to read the basis from schema.quota_basis_field so
 * this ticket needs no engine change beyond the seeder itself.
 *
 * Default seeded ceiling: 2500 lm (class-1 office per manual).
 *
 * Idempotent — updateOrCreate on the ceiling, replace-by-id on
 * schema.fields entries.
 */
class GovernmentSurveyQuotaSeeder extends Seeder
{
    public const GOVT_SURVEY_DISCIPLINE = 'government_survey';
    public const DEFAULT_CEILING_LM = 2500; // class-1 per manual p.125

    private const SERVICE_CODE = 'SRV-006';

    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        // 1. JORD-77: office ceilings are per-office (User), not per-org.
        $applicants = \App\Models\User::where('organization_id', $org->id)
            ->where('role', 'applicant')->get();
        foreach ($applicants as $applicant) {
            OfficeCeiling::updateOrCreate(
                [
                    'office_user_id' => $applicant->id,
                    'discipline'     => self::GOVT_SURVEY_DISCIPLINE,
                    'year'           => (int) now()->year,
                ],
                [
                    'organization_id' => $org->id, // denorm
                    'm2_allowed'      => self::DEFAULT_CEILING_LM,
                ],
            );
        }

        // 2. Schema wiring on SRV-006.
        $svc = ServiceDefinition::where('organization_id', $org->id)
            ->where('code', self::SERVICE_CODE)->first();
        if (!$svc) {
            $this->command->warn(self::SERVICE_CODE . ' not found — skipping.');
            return;
        }

        $schema = $svc->schema ?? [];
        $schema['fields'] = $this->mergeFields($schema['fields'] ?? []);
        $schema['quota_discipline_override'] = self::GOVT_SURVEY_DISCIPLINE;
        $schema['quota_basis_field']         = 'length_lm';
        $svc->update(['schema' => $schema]);

        $this->command->info('✓ Government-survey quota (' . self::DEFAULT_CEILING_LM
            . ' م.ط/yr, JEA p.125) applied to ' . self::SERVICE_CODE . '.');
    }

    /**
     * @param  list<array<string,mixed>> $existing
     * @return list<array<string,mixed>>
     */
    private function mergeFields(array $existing): array
    {
        $newIds = ['length_lm', 'engineer_id'];
        $kept = array_values(array_filter(
            $existing,
            fn ($f) => !in_array($f['id'] ?? null, $newIds, true),
        ));
        return array_merge($kept, [
            [
                'id'       => 'length_lm',
                'label_ar' => 'الطول (م.ط)',
                'label_en' => 'Length (linear meters)',
                'type'     => 'number',
                'required' => true,
                'min'      => 1,
            ],
            [
                'id'       => 'engineer_id',
                'label_ar' => 'المهندس المسؤول',
                'label_en' => 'Responsible Engineer',
                'type'     => 'select',
                'required' => true,
                'options'  => [],
                'options_endpoint' => '/engineers',
            ],
        ]);
    }
}
