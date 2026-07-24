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
 * ServiceDefinition، بحيث تظهر أيقونة (?) بجانب الحقل في الواجهة.
 *
 * المصدر: بيانات ManualReferencesSeeder — نستعلم عن الـ id بناءً على
 * jord_ticket بدلاً من تخمين الرقم. هذا يجعل الـ seeder يعمل حتى لو
 * أُعيد بناء الجدول بأرقام مختلفة.
 *
 * ⚠️ يجب أن يعمل بعد جميع الـ seeders التي تعبّئ schema.fields
 * (DrawingFeeMatrixSeeder, SolarFeeSeeder, SiteSurveyFeesSeeder,
 * DrawingEngineerPickerSeeder) وبعد ManualReferencesSeeder.
 *
 * Idempotent: يستخدم أرقام الحقول بالـ id، ويعيد كتابة manual_reference_id
 * فقط إذا لم يكن مُعيَّناً بعد.
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

        // Map: JORD ticket → field IDs to attach it to, per service pattern
        // Format: 'jord' => ['service_code_pattern' => ['field_id_1', 'field_id_2', ...]]
        $links = [
            'JORD-63' => [ // مصفوفة أتعاب المخططات
                'DRW-P-%' => ['governorate', 'building_class', 'area_m2'],
            ],
            'JORD-71' => [ // 10% governorate overflow rule
                'DRW-P-%' => ['governorate'],
            ],
            'JORD-64-solar' => [ // per-kW solar fee
                'DRW-P-006' => ['capacity_kw'],
            ],
            'JORD-78' => [ // survey per-lm fee
                'SRV-006' => ['length_lm'],
            ],
            'JORD-75' => [ // government survey lm quota
                'SRV-006' => ['length_lm'],
            ],
        ];

        $updated = 0;
        foreach ($links as $jord => $servicePatterns) {
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

        $this->command->info("✓ Linked manual_reference_id on {$updated} services.");
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
}
