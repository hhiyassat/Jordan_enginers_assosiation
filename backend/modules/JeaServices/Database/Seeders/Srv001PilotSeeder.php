<?php

declare(strict_types=1);

namespace Modules\JeaServices\Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;
use Modules\JeaServices\Models\ServiceDefinition;
use Modules\JeaServices\Support\JordanGovernorates;

/**
 * Srv001PilotSeeder — SRV-001 (تقارير استطلاع الموقع للأبنية المقترحة).
 *
 * One-service pilot. Writes schema.fields[], schema.documents[] and
 * schema.workflow.routing[] for SRV-001 only. Preserves the workflow
 * stages set by SurveyWorkflowsSeeder and the fee block set by
 * SiteSurveyFeesSeeder — this seeder MUST run after both.
 *
 * Field set is the amended, JEA-2025-manual-anchored set from directive
 * §7-§11 and matches the delta audit's manual-reference plan:
 *
 *   • Cadastral identification (JORD-89 / ص.34)
 *   • Governorate + location context (JORD-84 / ص.36 + JORD-91 for area matrix)
 *   • Party/sector (project_sector) — routing enum (خاص / حكومي)
 *   • Building geometry (floor_count, floor_area) — inputs to the
 *     exploration-requirement matrix
 *   • Exploration outputs (minimum_* derived, actual_* user-entered)
 *   • length_lm remains from SiteSurveyFeesSeeder — not touched here
 *
 * Documents:
 *   • survey_contract               (category=CONTRACT, required) — JORD-85-srv
 *   • site_investigation_report     (category=REPORT,   required, PDF-only, 25MB)
 *     — JORD-86 + JORD-87 + JORD-90
 *
 * Workflow routing:
 *   • project_sector = "حكومي"  →  route to SRV-006
 *     Enforced server-side by ApplicationController; the schema.workflow.routing
 *     block is the declarative source-of-truth the guard reads.
 *
 * Manual references are attached to individual fields / documents by
 * ManualReferenceLinksSeeder (extended for SRV-001).
 *
 * Idempotent: field/document arrays are replaced wholesale by id, so
 * re-runs converge on the canonical set. length_lm is preserved by
 * running AFTER SiteSurveyFeesSeeder and matching by id.
 */
class Srv001PilotSeeder extends Seeder
{
    private const SERVICE_CODE = 'SRV-001';

    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (! $org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        $svc = ServiceDefinition::where('organization_id', $org->id)
            ->where('code', self::SERVICE_CODE)
            ->first();

        if (! $svc) {
            $this->command->error(self::SERVICE_CODE . ' not found. Run ServicePlan2026Seeder first.');
            return;
        }

        $schema = $svc->schema ?? [];

        // Preserve length_lm (from SiteSurveyFeesSeeder) — merge our fields on top.
        $schema['fields']    = $this->mergeFields($schema['fields'] ?? []);
        $schema['documents'] = $this->documents();
        $schema['workflow']  = $this->attachRouting($schema['workflow'] ?? []);
        $schema['pilot']     = [
            'code'          => self::SERVICE_CODE,
            'manual_source' => 'كتاب التعليمات الفنية 2025',
            'notes'         => 'DLS integration deliberately excluded from this pilot.',
        ];

        $svc->update(['schema' => $schema]);
        $this->command->info('✓ SRV-001 pilot schema applied (fields + documents + routing).');
    }

    /**
     * @param  list<array<string,mixed>>  $existing
     * @return list<array<string,mixed>>
     */
    private function mergeFields(array $existing): array
    {
        $pilot   = $this->pilotFields();
        $pilotIds = array_map(static fn (array $f) => $f['id'], $pilot);

        // Keep any existing field that's not in the pilot set (length_lm
        // notably survives from SiteSurveyFeesSeeder).
        $kept = array_values(array_filter(
            $existing,
            static fn (array $f) => ! in_array($f['id'] ?? null, $pilotIds, true),
        ));

        return array_merge($kept, $pilot);
    }

    /**
     * The pilot field set. Ordered for form presentation:
     *   1. sector + location (routing / geography)
     *   2. cadastral identification
     *   3. party
     *   4. building geometry
     *   5. exploration derived + entered
     *
     * `manual_reference_id` slots are populated by ManualReferenceLinksSeeder.
     *
     * @return list<array<string,mixed>>
     */
    private function pilotFields(): array
    {
        return [
            // ── 1. sector routing enum (خاص / حكومي) ──────────────────
            [
                'id'          => 'project_sector',
                'label_ar'    => 'نوع الجهة المالكة',
                'label_en'    => 'Owner Sector',
                'type'        => 'select',
                'required'    => true,
                'description_ar' => 'إذا كانت الجهة المالكة حكومية يجب استخدام خدمة SRV-006.',
                'options'     => [
                    ['value' => 'خاص',   'label_ar' => 'خاص',   'label_en' => 'Private'],
                    ['value' => 'حكومي', 'label_ar' => 'حكومي', 'label_en' => 'Government (route to SRV-006)'],
                ],
            ],

            // ── 2. Location / geography ────────────────────────────────
            [
                'id'       => 'governorate',
                'label_ar' => 'المحافظة',
                'label_en' => 'Governorate',
                'type'     => 'select',
                'required' => true,
                'options'  => JordanGovernorates::options(),
            ],
            [
                'id'         => 'directorate_name',
                'label_ar'   => 'المديرية',
                'label_en'   => 'Directorate',
                'type'       => 'text',
                'required'   => true,
                'max_length' => 200,
                // Semantic status noted on the schema — meaning of "المديرية"
                // (land vs administrative) awaits JEA confirmation. Field is
                // NOT blocked; label is kept as generic "المديرية".
                'semantic_status' => 'NEEDS_JEA_CONFIRMATION',
            ],
            [
                'id'         => 'village_name',
                'label_ar'   => 'القرية',
                'label_en'   => 'Village',
                'type'       => 'text',
                'required'   => true,
                'max_length' => 200,
            ],
            [
                'id'         => 'neighborhood_name',
                'label_ar'   => 'الحي',
                'label_en'   => 'Neighborhood',
                'type'       => 'text',
                'required'   => false,
                'max_length' => 200,
            ],

            // ── 3. Cadastral identification (JORD-89 / ص.34) ──────────
            //
            // basin_number, parcel_number are STRINGS (not numbers) so
            // leading zeros are preserved. Cadastral keys in Jordan
            // routinely carry leading zeros and must be printed
            // verbatim on internal report pages.
            [
                'id'         => 'basin_number',
                'label_ar'   => 'رقم الحوض',
                'label_en'   => 'Basin Number',
                'type'       => 'text',
                'required'   => true,
                'max_length' => 20,
                'pattern'    => '^[0-9]+$',
            ],
            [
                'id'         => 'basin_or_location_name',
                'label_ar'   => 'اسم الحوض/الموقع',
                'label_en'   => 'Basin / Location Name',
                'type'       => 'text',
                'required'   => true,
                'max_length' => 200,
            ],
            [
                'id'         => 'parcel_number',
                'label_ar'   => 'رقم القطعة',
                'label_en'   => 'Parcel Number',
                'type'       => 'text',
                'required'   => true,
                'max_length' => 20,
                'pattern'    => '^[0-9]+$',
            ],

            // ── 4. Party (owner) — simple string per directive §11 ────
            [
                'id'         => 'contract_owner_name',
                'label_ar'   => 'اسم صاحب العقد',
                'label_en'   => 'Contract Owner Name',
                'type'       => 'text',
                'required'   => true,
                'max_length' => 200,
            ],

            // ── 5. Building geometry — matrix inputs (JORD-91) ────────
            //
            // floor_count + floor_area are NOT passive fields; they are
            // inputs to the exploration-requirement matrix on
            // ExplorationRequirementMatrix.
            [
                'id'       => 'floor_count',
                'label_ar' => 'عدد الطوابق',
                'label_en' => 'Floor Count',
                'type'     => 'number',
                'required' => true,
                'min'      => 1,
            ],
            [
                'id'       => 'floor_area',
                'label_ar' => 'مساحة الطابق (م²)',
                'label_en' => 'Floor Area (m²)',
                'type'     => 'number',
                'required' => true,
                'min'      => 1,
            ],
            [
                'id'       => 'land_or_contract_area',
                'label_ar' => 'مساحة الأرض / العقد (م²)',
                'label_en' => 'Land / Contract Area (m²)',
                'type'     => 'number',
                'required' => false,
                'min'      => 0,
            ],
            [
                'id'       => 'proposed_built_area',
                'label_ar' => 'المساحة الإجمالية المقترحة (م²)',
                'label_en' => 'Proposed Built Area (m²)',
                'type'     => 'number',
                'required' => false,
                'min'      => 0,
            ],

            // ── 6. Exploration — derived + entered (JORD-91) ──────────
            //
            // The derived fields are read-only on the form; server
            // computes them via ExplorationRequirementMatrix on
            // submission validation. Kept in the schema so the (?)
            // icon and the ReportsPanel can reference their manual
            // provision.
            [
                'id'          => 'minimum_exploration_point_count',
                'label_ar'    => 'الحد الأدنى لعدد نقاط الاستكشاف',
                'label_en'    => 'Minimum Exploration Point Count',
                'type'        => 'number',
                'required'    => false,
                'read_only'   => true,
                'computed_by' => 'ExplorationRequirementMatrix',
            ],
            [
                'id'          => 'minimum_total_depth_lm',
                'label_ar'    => 'الحد الأدنى لمجموع الأعماق (م.ط)',
                'label_en'    => 'Minimum Total Depth (linear meters)',
                'type'        => 'number',
                'required'    => false,
                'read_only'   => true,
                'computed_by' => 'ExplorationRequirementMatrix',
            ],
            [
                'id'       => 'actual_exploration_point_count',
                'label_ar' => 'عدد نقاط الاستكشاف المنفذة',
                'label_en' => 'Actual Exploration Point Count',
                'type'     => 'number',
                'required' => true,
                'min'      => 0,
            ],
        ];
    }

    /**
     * Two required documents. `category` distinguishes them for the
     * ReportsPanel filter (category=REPORT) vs the ordinary attachments
     * section (category=CONTRACT).
     *
     * File-type policy follows the repository's canonical shape used by
     * DrawingsDocumentsSeeder and every other document seeder:
     *   • `accept: ['pdf']` — extension whitelist read by DocumentUploader
     *     for the client-side gate and by App\Rules\PdfOrDwgFile at
     *     upload time (magic-byte verified server-side).
     *   • `max_size_mb`     — size gate consumed by both DocumentUploader
     *     and ApplicationController::uploadDocument.
     *
     * No parallel MIME key is added — the existing PdfOrDwgFile rule
     * already enforces application/pdf via header bytes and the schema
     * has no other consumer for a `accepted_mime_types` field.
     *
     * @return list<array<string,mixed>>
     */
    private function documents(): array
    {
        return [
            [
                'id'          => 'survey_contract',
                'label_ar'    => 'عقد استطلاع الموقع',
                'label_en'    => 'Site-Investigation Contract',
                'category'    => 'CONTRACT',
                'required'    => true,
                'accept'      => ['pdf'],
                'max_size_mb' => 10,
            ],
            [
                'id'          => 'site_investigation_report',
                'label_ar'    => 'تقرير استطلاع الموقع',
                'label_en'    => 'Site-Investigation Report',
                'category'    => 'REPORT',
                'report_type' => 'SITE_INVESTIGATION_REPORT',
                'required'    => true,
                'accept'      => ['pdf'],
                'max_size_mb' => 25,
                'signature_requirements' => [
                    'signing_role'                           => 'HEAD_OF_SPECIALIZATION',
                    'signing_engineer_name_required'         => true,
                    'signing_engineer_registration_required' => true,
                    'source'                                 => 'كتاب التعليمات الفنية 2025 ص.37',
                ],
            ],
        ];
    }

    /**
     * Preserve the workflow stages already installed by
     * SurveyWorkflowsSeeder; attach a routing block that the
     * ApplicationController's SRV-001 guard reads before accepting a
     * submission.
     *
     * @param  array<string, mixed>  $workflow
     * @return array<string, mixed>
     */
    private function attachRouting(array $workflow): array
    {
        $workflow['routing'] = [
            [
                'when'    => ['project_sector' => 'حكومي'],
                'action'  => 'ROUTE_TO_SERVICE',
                'target'  => 'SRV-006',
                'message_ar' => 'المشاريع الحكومية تُقدَّم عبر خدمة SRV-006 — تقارير استطلاع الموقع للمشاريع الحكومية.',
                'message_en' => 'Government projects must be submitted via SRV-006 — Site-Survey Reports for Government Projects.',
                'source'  => 'directive §3',
            ],
        ];
        return $workflow;
    }
}
