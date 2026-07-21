<?php

namespace Database\Seeders;

use App\Models\Engineer;
use App\Models\EngineerDisciplineQuota;
use App\Models\OfficeCeiling;
use App\Models\Organization;
use App\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * MaterialsTestingQuotaSeeder — JORD-74 (Rule Q-02)
 *
 * The JEA 2025 manual (p. 125) pins per-lab annual m² caps for
 * materials-testing services (SRV-008 / SRV-009):
 *
 *   "الحصص الهندسية عن الاجهزة المخبرية وآلات الحفر لاختصاص ميكانيكا
 *    التربة … مختبر استشاري 1200 م² / هندسي 900 / أ 900."
 *
 * The three tiers translate to a per-office ceiling for the special
 * 'materials_testing' discipline bucket (not one of the 5 canonical
 * Disciplines — it's a lab-service-specific quota).
 *
 * Also wires SRV-008/009 into the quota-tracked path:
 *   • Adds area_m2 field (required) — the m² of testing per project.
 *   • Adds engineer_id field (required, dynamic options) — mirrors
 *     JORD-69 pattern on DRW-P-*.
 *   • Sets schema.quota_discipline_override = 'materials_testing' so
 *     CapacityGuard + QuotaLedger route the check to the materials
 *     ceiling instead of the picked engineer's own discipline.
 *
 * Default seeded ceiling: 1200 m²/yr for the demo org (consultant
 * tier from the manual). Ops changes per-office via the admin UI /
 * a direct OfficeCeiling update as they onboard real labs.
 *
 * Idempotent — updateOrCreate on the composite unique
 * (org_id, discipline, year); wholesale-replaces schema.fields'
 * area_m2 + engineer_id entries by id.
 */
class MaterialsTestingQuotaSeeder extends Seeder
{
    public const MATERIALS_DISCIPLINE = 'materials_testing';
    public const DEFAULT_CEILING = 1200; // consultant tier per manual p.125

    private const SERVICE_CODES = ['SRV-008', 'SRV-009'];

    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        // 1. Office ceilings — one per office user (JORD-77).
        $applicants = \App\Models\User::where('organization_id', $org->id)
            ->where('role', 'applicant')->get();
        foreach ($applicants as $applicant) {
            OfficeCeiling::updateOrCreate(
                [
                    'office_user_id' => $applicant->id,
                    'discipline'     => self::MATERIALS_DISCIPLINE,
                    'year'           => (int) now()->year,
                ],
                [
                    'organization_id' => $org->id, // denorm
                    'm2_allowed'      => self::DEFAULT_CEILING,
                ],
            );
        }

        // 2. Schema wiring on SRV-008/009.
        $updated = 0;
        foreach (self::SERVICE_CODES as $code) {
            $svc = ServiceDefinition::where('organization_id', $org->id)
                ->where('code', $code)->first();
            if (!$svc) continue;

            $schema = $svc->schema ?? [];
            $schema['fields'] = $this->mergeFields($schema['fields'] ?? []);
            $schema['quota_discipline_override'] = self::MATERIALS_DISCIPLINE;
            $svc->update(['schema' => $schema]);
            $updated++;
        }

        $this->command->info('✓ Materials-testing quota (' . self::DEFAULT_CEILING
            . " m²/yr, JEA p.125) applied to {$updated} services.");
    }

    /**
     * Idempotently append area_m2 + engineer_id fields.
     *
     * @param  list<array<string,mixed>> $existing
     * @return list<array<string,mixed>>
     */
    private function mergeFields(array $existing): array
    {
        $newIds = ['area_m2', 'engineer_id'];
        $kept = array_values(array_filter(
            $existing,
            fn ($f) => !in_array($f['id'] ?? null, $newIds, true),
        ));
        return array_merge($kept, [
            [
                'id'       => 'area_m2',
                'label_ar' => 'مساحة الفحص (م²)',
                'label_en' => 'Testing Area (m²)',
                'type'     => 'number',
                'required' => true,
                'min'      => 1,
            ],
            [
                'id'       => 'engineer_id',
                'label_ar' => 'المهندس المسؤول عن الفحص',
                'label_en' => 'Responsible Testing Engineer',
                'type'     => 'select',
                'required' => true,
                'options'  => [],
                'options_endpoint' => '/engineers',
            ],
        ]);
    }
}
