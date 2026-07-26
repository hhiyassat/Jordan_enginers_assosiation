<?php

declare(strict_types=1);

namespace Modules\JeaServices\Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;
use Modules\JeaServices\Models\ManualReference;
use Modules\JeaServices\Models\ServiceDefinition;

/**
 * ManualReferenceLinksSeeder
 * ──────────────────────────
 * يربط `manual_reference_id` على حقول محدَّدة في schema.fields لكل
 * ServiceDefinition، ويربط `manual_reference_ids` (قائمة) على مستندات
 * schema.documents، بحيث تظهر أيقونة (?) بجانب الحقل / المستند في الواجهة.
 *
 * المصدر: بيانات ManualReferencesSeeder — نستعلم عن الـ id بناءً على
 * jord_ticket بدلاً من تخمين الرقم. هذا يجعل الـ seeder يعمل حتى لو
 * أُعيد بناء الجدول بأرقام مختلفة.
 *
 * ⚠️ يجب أن يعمل بعد جميع الـ seeders التي تعبّئ schema.fields/documents
 * (DrawingFeeMatrixSeeder, SolarFeeSeeder, SiteSurveyFeesSeeder,
 * DrawingEngineerPickerSeeder, Srv001PilotSeeder) وبعد ManualReferencesSeeder.
 *
 * Idempotent:
 *   • الحقول: تُكتب `manual_reference_id` فقط إذا لم تكن مُعيَّنة بعد.
 *   • المستندات: تُضاف الـ id إلى `manual_reference_ids` بدون تكرار.
 */
class ManualReferenceLinksSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (! $org) {
            $this->command->warn('Demo organization not found — skipping ManualReferenceLinksSeeder.');
            return;
        }

        $fieldLinks    = $this->fieldLinks();
        $documentLinks = $this->documentLinks();

        $updated = 0;
        foreach ($fieldLinks as $jord => $servicePatterns) {
            $ref = ManualReference::where('jord_ticket', $jord)->first();
            if (! $ref) {
                $this->command->warn("Reference for {$jord} not found — skip.");
                continue;
            }
            foreach ($servicePatterns as $pattern => $fieldIds) {
                $services = ServiceDefinition::where('organization_id', $org->id)
                    ->where('code', 'like', $pattern)
                    ->get();
                foreach ($services as $service) {
                    if ($this->attachRefToFields($service, $fieldIds, $ref->id)) {
                        $updated++;
                    }
                }
            }
        }

        foreach ($documentLinks as $jord => $servicePatterns) {
            $ref = ManualReference::where('jord_ticket', $jord)->first();
            if (! $ref) {
                $this->command->warn("Reference for {$jord} not found — skip document link.");
                continue;
            }
            foreach ($servicePatterns as $pattern => $documentIds) {
                $services = ServiceDefinition::where('organization_id', $org->id)
                    ->where('code', 'like', $pattern)
                    ->get();
                foreach ($services as $service) {
                    if ($this->attachRefToDocuments($service, $documentIds, $ref->id)) {
                        $updated++;
                    }
                }
            }
        }

        $this->command->info("✓ Linked manual references on {$updated} service(s).");
    }

    /**
     * Field-level links.
     *
     * Format: 'jord_ticket' => ['service_code_pattern' => ['field_id', ...]]
     *
     * @return array<string, array<string, list<string>>>
     */
    private function fieldLinks(): array
    {
        return [
            // Legacy — DRW-P-* fee matrix / overflow / per-kW / survey per-lm
            'JORD-63' => ['DRW-P-%' => ['governorate', 'building_class', 'area_m2']],
            'JORD-71' => ['DRW-P-%' => ['governorate']],
            'JORD-64-solar' => ['DRW-P-006' => ['capacity_kw']],
            'JORD-78' => ['SRV-006' => ['length_lm']],
            'JORD-75' => ['SRV-006' => ['length_lm']],

            // SRV-001 pilot — targeted per §13 of the directive.
            //
            // Field linker is single-ref-per-field (unlike documents,
            // which accumulate). floor_area is claimed by JORD-91 (the
            // matrix) because it is the more actionable technical rule;
            // JORD-84 owns the geographic eligibility surface via
            // governorate only. Both refs are still queryable from the
            // service_code column when a reviewer wants the applicability
            // rule on its own.
            'JORD-84' => ['SRV-001' => ['governorate']],
            'JORD-89' => ['SRV-001' => ['basin_number', 'parcel_number', 'basin_or_location_name', 'contract_owner_name']],
            'JORD-91' => ['SRV-001' => [
                'floor_count', 'floor_area',
                'minimum_exploration_point_count',
                'minimum_total_depth_lm',
                'actual_exploration_point_count',
            ]],
        ];
    }

    /**
     * Document-level links. A single document may accumulate multiple
     * refs (report signature + technical content + direct submission
     * all attach to site_investigation_report).
     *
     * @return array<string, array<string, list<string>>>
     */
    private function documentLinks(): array
    {
        return [
            'JORD-85-srv' => ['SRV-001' => ['survey_contract']],
            'JORD-86'     => ['SRV-001' => ['site_investigation_report']],
            'JORD-87'     => ['SRV-001' => ['site_investigation_report']],
            'JORD-90'     => ['SRV-001' => ['site_investigation_report']],
        ];
    }

    /** @param  list<string>  $fieldIds */
    private function attachRefToFields(ServiceDefinition $service, array $fieldIds, int $refId): bool
    {
        $schema = $service->schema ?? [];
        $fields = $schema['fields'] ?? [];
        if (empty($fields)) {
            return false;
        }

        $changed = false;
        foreach ($fields as $i => $field) {
            $id = $field['id'] ?? null;
            if (! $id || ! in_array($id, $fieldIds, true)) {
                continue;
            }
            // Only set — never overwrite an already-attached reference
            // (a service may want a different manual passage for the same
            // field id on its schema, and re-runs shouldn't stomp it).
            if (! isset($field['manual_reference_id'])) {
                $fields[$i]['manual_reference_id'] = $refId;
                $changed = true;
            }
        }

        if ($changed) {
            $schema['fields'] = $fields;
            $service->update(['schema' => $schema]);
        }
        return $changed;
    }

    /** @param  list<string>  $documentIds */
    private function attachRefToDocuments(ServiceDefinition $service, array $documentIds, int $refId): bool
    {
        $schema = $service->schema ?? [];
        $documents = $schema['documents'] ?? [];
        if (empty($documents)) {
            return false;
        }

        $changed = false;
        foreach ($documents as $i => $doc) {
            $id = $doc['id'] ?? null;
            if (! $id || ! in_array($id, $documentIds, true)) {
                continue;
            }
            $existing = $doc['manual_reference_ids'] ?? [];
            if (! is_array($existing)) {
                $existing = [];
            }
            if (! in_array($refId, $existing, true)) {
                $existing[] = $refId;
                $documents[$i]['manual_reference_ids'] = array_values($existing);
                $changed = true;
            }
        }

        if ($changed) {
            $schema['documents'] = $documents;
            $service->update(['schema' => $schema]);
        }
        return $changed;
    }
}
