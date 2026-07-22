<?php

namespace Database\Seeders;

use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * DrawingEngineerPickerSeeder — JORD-69
 *
 * Adds a required `engineer_id` select field to every DRW-P-* service
 * so the applicant explicitly picks which of the office's engineers
 * is responsible for this project. CapacityGuard reads
 * data.engineer_id to determine whose quota (and which discipline's
 * ceiling) gets deducted.
 *
 * The field is type=select with an empty options[] plus an
 * `options_endpoint: '/engineers'` marker — the frontend DynamicForm
 * fetches the office's engineer roster from that endpoint at render
 * time. Keeps the schema portable (no user-id hardcoded) and
 * automatically reflects new hires without a seeder re-run.
 *
 * Idempotent — replaces any existing engineer_id field.
 */
class DrawingEngineerPickerSeeder extends Seeder
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
            $schema['fields'] = $this->mergeEngineerField($schema['fields'] ?? []);
            $svc->update(['schema' => $schema]);
            $updated++;
        }

        $this->command->info("✓ Engineer picker field added to {$updated} drawing services.");
    }

    /**
     * @param  list<array<string,mixed>> $existing
     * @return list<array<string,mixed>>
     */
    private function mergeEngineerField(array $existing): array
    {
        $kept = array_values(array_filter(
            $existing,
            fn ($f) => ($f['id'] ?? null) !== 'engineer_id',
        ));
        $kept[] = [
            'id'       => 'engineer_id',
            'label_ar' => 'المهندس المسؤول',
            'label_en' => 'Responsible Engineer',
            'type'     => 'select',
            'required' => true,
            'options'  => [], // frontend populates from options_endpoint
            'options_endpoint' => '/engineers',
        ];
        return $kept;
    }
}
