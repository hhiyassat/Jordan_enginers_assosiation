<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * DrawingsDocumentsSeeder — JORD-54
 *
 * The JEA "Engineering Drawings Electronic Audit Procedures" doc set
 * (15 documents covering the four discipline drawings, the three
 * contract templates, the ownership deeds + site plans, calculation
 * notes, site-survey and existing-building safety study) applies to
 * every DRW-P-* service under JEA-PROJ, not just DRW-P-001. The
 * originally-approved plan was: one office prepares the drawings and
 * paperwork once → the same required-document manifest gates every
 * drawing service the office might file.
 *
 * The user manually added the set to DRW-P-001 via the admin
 * assistant, then asked for it to be applied to DRW-P-002..010. This
 * seeder is that mass-apply, expressed as source-of-truth code so:
 *   • The set survives a `db:seed --class=DatabaseSeeder` refresh
 *     (otherwise the manual DB-only edit would vanish on the next
 *     seed and this work would evaporate).
 *   • It runs idempotently — re-executing the seeder replaces the
 *     documents array wholesale so the canonical list is always the
 *     one in this file, no drift.
 *   • Any per-service required/optional override an admin makes
 *     later via the schema editor is intentionally reset by re-runs
 *     (this is a seeder, not a merge). If an org needs their own
 *     variant, they should copy the service off the plan template
 *     and edit that copy.
 *
 * Runs after CatalogWorkflowsSeeder — that seeder only writes
 * schema['workflow'], so we can safely replace schema['documents']
 * on top of it.
 */
class DrawingsDocumentsSeeder extends Seeder
{
    /** The 10 drawing services that carry this shared document manifest. */
    private const DRAWING_CODES = [
        'DRW-P-001', // مخططات الأبنية المقترحة
        'DRW-P-002', // مخططات الأبنية القائمة
        'DRW-P-003', // مخططات الأبنية المقترحة فوق الأبنية القائمة
        'DRW-P-004', // مخططات الهدم
        'DRW-P-005', // مخططات المشاريع الكبرى
        'DRW-P-006', // مخططات مشاريع الطاقة
        'DRW-P-007', // مخططات رخص المهن
        'DRW-P-008', // المخططات التعديلية
        'DRW-P-009', // مخططات إعادة التأهيل / الصيانة
        'DRW-P-010', // مخططات تعديلية / مجلس البناء الوطني
    ];

    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        $documents = $this->documents();

        $updated = 0;
        $missing = [];
        foreach (self::DRAWING_CODES as $code) {
            $service = ServiceDefinition::where('organization_id', $org->id)
                ->where('code', $code)
                ->first();
            if (!$service) {
                $missing[] = $code;
                continue;
            }
            $schema = $service->schema ?? [];
            $schema['documents'] = $documents;
            $service->update(['schema' => $schema]);
            $updated++;
        }

        $this->command->info("✓ Drawings documents applied to {$updated} services.");
        if ($missing) {
            $this->command->warn('Missing services (skipped): ' . implode(', ', $missing));
        }
    }

    /**
     * The canonical 15-document manifest. required=true means the
     * SchemaValidator blocks stage progression until the applicant
     * uploads it (WF-006). Three are optional-by-default because
     * their applicability is data-conditional (owner=company,
     * area>250m², existing-building), and the admin is the one who
     * flips them per project via the schema editor.
     *
     * @return list<array<string, mixed>>
     */
    private function documents(): array
    {
        return [
            // ── 1. Discipline drawings (approved by the four discipline chiefs) ──
            [
                'id'           => 'architectural_drawings',
                'label_ar'     => 'المخططات المعمارية (معتمدة من رئيس الاختصاص)',
                'label_en'     => 'Architectural Drawings (approved by discipline chief)',
                'required'     => true,
                'accept'       => ['pdf', 'dwg'],
                'max_size_mb'  => 25,
            ],
            [
                'id'           => 'structural_drawings',
                'label_ar'     => 'المخططات الإنشائية (معتمدة من رئيس الاختصاص)',
                'label_en'     => 'Structural Drawings (approved by discipline chief)',
                'required'     => true,
                'accept'       => ['pdf', 'dwg'],
                'max_size_mb'  => 25,
            ],
            [
                'id'           => 'electrical_drawings',
                'label_ar'     => 'المخططات الكهربائية (معتمدة من رئيس الاختصاص)',
                'label_en'     => 'Electrical Drawings (approved by discipline chief)',
                'required'     => true,
                'accept'       => ['pdf', 'dwg'],
                'max_size_mb'  => 25,
            ],
            [
                'id'           => 'mechanical_drawings',
                'label_ar'     => 'المخططات الميكانيكية (معتمدة من رئيس الاختصاص)',
                'label_en'     => 'Mechanical Drawings (approved by discipline chief)',
                'required'     => true,
                'accept'       => ['pdf', 'dwg'],
                'max_size_mb'  => 25,
            ],

            // ── 2. Engineering services contracts ──
            [
                'id'           => 'work_assignment_form',
                'label_ar'     => 'نموذج تكليف عمل',
                'label_en'     => 'Work Assignment Form',
                'required'     => true,
                'accept'       => ['pdf'],
                'max_size_mb'  => 10,
            ],
            [
                'id'           => 'design_services_agreement',
                'label_ar'     => 'اتفاقية خدمات هندسية تصميم + ملحق اتفاقية للتصميم',
                'label_en'     => 'Design Services Agreement + Design Addendum',
                'required'     => true,
                'accept'       => ['pdf'],
                'max_size_mb'  => 10,
            ],
            [
                'id'           => 'supervision_services_agreement',
                'label_ar'     => 'اتفاقية خدمات هندسية للإشراف + ملحق اتفاقية للإشراف',
                'label_en'     => 'Supervision Services Agreement + Supervision Addendum',
                'required'     => true,
                'accept'       => ['pdf'],
                'max_size_mb'  => 10,
            ],

            // ── 3. Ownership + site paperwork ──
            [
                'id'           => 'registration_deed',
                'label_ar'     => 'سند تسجيل',
                'label_en'     => 'Registration Deed',
                'required'     => true,
                'accept'       => ['pdf'],
                'max_size_mb'  => 10,
            ],
            [
                'id'           => 'site_plan',
                'label_ar'     => 'مخطط موقع',
                'label_en'     => 'Site Plan',
                'required'     => true,
                'accept'       => ['pdf', 'dwg'],
                'max_size_mb'  => 15,
            ],
            [
                'id'           => 'land_plan',
                'label_ar'     => 'مخطط أراضي',
                'label_en'     => 'Land Plan',
                'required'     => true,
                'accept'       => ['pdf', 'dwg'],
                'max_size_mb'  => 15,
            ],
            [
                'id'           => 'commercial_register',
                'label_ar'     => 'السجل التجاري (إذا كان المالك شركة)',
                'label_en'     => 'Commercial Registration (if owner is a company)',
                // Optional-by-default: applies only when the project's
                // owner is a company. Admin flips this via schema editor
                // per service if they want to hard-require it.
                'required'     => false,
                'accept'       => ['pdf'],
                'max_size_mb'  => 5,
            ],
            [
                'id'           => 'topographic_plan',
                'label_ar'     => 'مخطط طبوغرافي',
                'label_en'     => 'Topographic Plan',
                'required'     => true,
                'accept'       => ['pdf', 'dwg'],
                'max_size_mb'  => 15,
            ],

            // ── 4. Calculation memos (structural / electrical / mechanical) ──
            [
                'id'           => 'calculation_notes',
                'label_ar'     => 'المذكرات الحسابية للمشروع (إنشائية، كهربائية، ميكانيك) للمشاريع التي تزيد مساحتها عن 250 م²',
                'label_en'     => 'Calculation Notes (structural, electrical, mechanical) — for projects > 250 m²',
                // Optional-by-default: applies only when area > 250 m².
                'required'     => false,
                'accept'       => ['pdf'],
                'max_size_mb'  => 15,
            ],

            // ── 5. Site survey (soil testing + excavation protection) ──
            [
                'id'           => 'site_survey_report',
                'label_ar'     => 'تقرير استطلاع الموقع (فحص التربة) مدقق فنياً ومصادق عليه من النقابة',
                'label_en'     => 'Site Survey Report (soil testing) — JEA-audited',
                'required'     => true,
                'accept'       => ['pdf'],
                'max_size_mb'  => 20,
            ],

            // ── 6. Existing-building structural safety study ──
            [
                'id'           => 'structural_safety_study',
                'label_ar'     => 'دراسة إنشائية وسلامة منشأة للأبنية القائمة',
                'label_en'     => 'Structural & Building-Safety Study (existing buildings)',
                // Optional-by-default: applies to existing-building
                // scenarios (naturally DRW-P-002 and DRW-P-003).
                'required'     => false,
                'accept'       => ['pdf'],
                'max_size_mb'  => 20,
            ],
        ];
    }
}
