<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * SurveyWorkflowsSeeder
 *
 * Replaces the placeholder workflow on each استطلاع الموقع service with
 * the real workflow extracted from the 12 drawio flowchart PDFs in
 * /Users/husseinhiyassat/tenders/engineering/flowcahrt/.
 *
 * The stages array is the primary path (happy path). Modification flows
 * are stored under schema.workflow.variants.modification so the frontend
 * can offer them as an alternative entry point without polluting the
 * default state machine.
 *
 * Historical note: this seeder previously created SRV-015 (Slope Stability)
 * out of a standalone drawio flowchart. That row was dropped from the JEA
 * services plan and is no longer seeded — the workflow and upsert helpers
 * are kept for reference and can be reintroduced by re-adding the code to
 * $mappings + calling $this->upsertSlopeStability() from run().
 *
 * Run: php artisan db:seed --class=SurveyWorkflowsSeeder
 */
class SurveyWorkflowsSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        // Replace the placeholder workflow on each service in scope.
        // SRV-015 (Slope Stability) intentionally omitted — dropped from
        // the JEA services plan. See historical note above.
        $mappings = [
            'SRV-001' => $this->soilProposedWorkflow(),
            'SRV-002' => $this->soilExistingWorkflow(),
            'SRV-007' => $this->excavationSupportDesignWorkflow(),
            'SRV-008' => $this->materialsProposedWorkflow(),
            'SRV-009' => $this->materialsExistingWorkflow(),
            'SRV-012' => $this->excavationSupervisionWorkflow(),
            'SRV-014' => $this->visualInspectionWorkflow(),
        ];

        $updated = 0;
        foreach ($mappings as $code => $workflowBundle) {
            $service = ServiceDefinition::where('organization_id', $org->id)
                ->where('code', $code)
                ->first();
            if (!$service) continue;

            $schema = $service->schema;
            $schema['workflow'] = $workflowBundle['workflow'];
            $schema['flowchart_source'] = $workflowBundle['source'];
            $service->schema = $schema;

            // Descriptions summarise the flowchart — set at the row level so
            // the frontend service cards can show them without unpacking the
            // schema. Two-language pair mandated by NFR-003.
            if (isset($workflowBundle['description_ar'])) {
                $service->description_ar = $workflowBundle['description_ar'];
            }
            if (isset($workflowBundle['description_en'])) {
                $service->description_en = $workflowBundle['description_en'];
            }

            $service->save();
            $updated++;
        }

        $this->command->info("✓ Survey workflows + descriptions applied to {$updated} services (out of " . count($mappings) . ' mapped).');
    }

    private function upsertSlopeStability(int $orgId): void
    {
        ServiceDefinition::updateOrCreate(
            ['organization_id' => $orgId, 'code' => 'SRV-015'],
            [
                'organization_id' => $orgId,
                'code'            => 'SRV-015',
                'parent_code'     => 'JEA-SURV',
                'subcategory_ar'  => 'الحفريات',
                'subcategory_en'  => 'Excavations',
                'phase'           => 1,
                'name_ar'         => 'تقارير استقرار المنحدرات',
                'name_en'         => 'Slope Stability Reports',
                'currency'        => 'JOD',
                'status'          => 'active',
                'schema'          => [
                    'service_code' => 'SRV-015',
                    'name_ar'      => 'تقارير استقرار المنحدرات',
                    'name_en'      => 'Slope Stability Reports',
                    'version'      => '1.0-flowchart',
                    'workflow'     => ['stages' => []],  // filled in by run()
                    'fee'          => ['type' => 'fixed', 'amount' => 0, 'currency' => 'JOD'],
                    'sections'     => [],
                    'fields'       => [],
                    'documents'    => [],
                    'certificate'  => [
                        'validity_months' => 12,
                        'title_ar'        => 'تقرير استقرار المنحدرات المصدق',
                        'title_en'        => 'Certified Slope Stability Report',
                        'fields_on_cert'  => [],
                    ],
                ],
            ]
        );
    }

    /* ── Stage helpers ────────────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function stageOfficeSubmission(): array
    {
        return [
            'id'        => 'office_submission',
            'label_ar'  => 'تقديم الطلب من المكتب الهندسي',
            'label_en'  => 'Office Submission',
            'role'      => 'applicant',
            'sla_hours' => 24,
            'actions'   => ['submit'],
        ];
    }

    /** @return array<string, mixed> */
    private function stageFirstAuditor(string $labelAr = 'مراجعة المدقق الأول', string $labelEn = 'First Auditor Review'): array
    {
        return [
            'id'        => 'first_auditor_review',
            'label_ar'  => $labelAr,
            'label_en'  => $labelEn,
            'role'      => 'staff',
            'sla_hours' => 48,
            'actions'   => ['approve', 'request_modifications', 'reject'],
        ];
    }

    /** @return array<string, mixed> */
    private function stageSecondAuditor(string $labelAr = 'مراجعة المدقق الثاني — وحدة استطلاع الموقع', string $labelEn = 'Second Auditor Review — Site Survey Unit'): array
    {
        return [
            'id'        => 'second_auditor_review',
            'label_ar'  => $labelAr,
            'label_en'  => $labelEn,
            'role'      => 'auditor',
            'sla_hours' => 72,
            'actions'   => ['approve', 'request_modifications', 'reject', 'override_first_auditor'],
        ];
    }

    /** @return array<string, mixed> */
    private function stagePayment(string $labelAr = 'دفع المستحقات المالية وضريبة المبيعات', string $labelEn = 'Pay Fees & Sales Tax'): array
    {
        return [
            'id'        => 'payment',
            'label_ar'  => $labelAr,
            'label_en'  => $labelEn,
            'role'      => 'staff',
            'sla_hours' => 24,
            'actions'   => ['confirm_payment'],
        ];
    }

    /** @return array<string, mixed> */
    private function stageIssueDocuments(): array
    {
        return [
            'id'        => 'issue_documents',
            'label_ar'  => 'إصدار الوصولات والعقد المصدق (PDF)',
            'label_en'  => 'Issue Receipts & Certified Contract (PDF)',
            'role'      => 'staff',
            'sla_hours' => 24,
            'actions'   => ['issue_certificate'],
        ];
    }

    /* ── Modification variant (shared by SRV-008/009/007/015 mods) ──── */

    /** @return list<array<string, mixed>> */
    private function modificationVariantStages(): array
    {
        return [
            [
                'id'        => 'select_data_source',
                'label_ar'  => 'حالة الطلب: نظام جديد أم قديم / ورقي',
                'label_en'  => 'Application Source: New System vs Legacy / Paper',
                'role'      => 'applicant',
                'sla_hours' => 24,
                'actions'   => ['choose_new_system', 'choose_legacy_system'],
            ],
            [
                'id'        => 'ingest_or_reenter_data',
                'label_ar'  => 'استدعاء البيانات وتحديد محددات التعديل أو إعادة الإدخال',
                'label_en'  => 'Retrieve Data & Modification Bounds — or Re-enter From Scratch',
                'role'      => 'applicant',
                'sla_hours' => 48,
                'actions'   => ['confirm_data'],
            ],
            [
                'id'        => 'classify_modification',
                'label_ar'  => 'تحديد نوع التعديل: فني أم إداري',
                'label_en'  => 'Modification Type: Technical vs Administrative',
                'role'      => 'staff',
                'sla_hours' => 24,
                'actions'   => ['classify_technical', 'classify_administrative'],
            ],
            $this->stageSecondAuditor(),
            $this->stagePayment('الدفع الإلكتروني والضريبة', 'Electronic Payment + Tax'),
            $this->stageIssueDocuments(),
        ];
    }

    /* ── Per-service workflows ───────────────────────────────────────── */

    /** @return array{workflow: array<string, mixed>, source: string, description_ar: string, description_en: string} */
    private function materialsProposedWorkflow(): array
    {
        return [
            'source'         => 'flowcahrt/عقد مواد مقترح.pdf',
            'description_ar' => 'عقد فحص المواد للأبنية المقترحة. يقدم المكتب الهندسي الطلب، ثم يراجعه المدقق الثاني في وحدة استطلاع الموقع. عند الاعتماد يتم دفع المستحقات المالية وضريبة المبيعات، ويصدر النظام الوصولات والنسخة المصدقة. يوجد استكمال إجراء لاحق في المرحلة 4.',
            'description_en' => 'Materials-testing contract for proposed buildings. The engineering office submits, then the second auditor at the Site Survey Unit reviews. On approval, fees and sales tax are paid; the system issues receipts and a certified copy. A follow-up procedure runs in phase 4.',
            'workflow' => [
                'stages' => [
                    $this->stageOfficeSubmission(),
                    $this->stageSecondAuditor(),
                    $this->stagePayment(),
                    $this->stageIssueDocuments(),
                ],
                'metadata' => [
                    'has_first_auditor'    => false,
                    'phase_4_continuation' => true,
                    'notes_ar'             => 'يتم استكمال الإجراء لاحقاً في المرحلة 4.',
                ],
                'variants' => [
                    'modification' => [
                        'source'   => 'flowcahrt/تعديل عقد مواد مقترح.drawio.pdf',
                        'label_ar' => 'تعديل عقد مواد مقترح',
                        'label_en' => 'Modify Proposed Materials Contract',
                        'stages'   => $this->modificationVariantStages(),
                    ],
                ],
            ],
        ];
    }

    /** @return array{workflow: array<string, mixed>, source: string, description_ar: string, description_en: string} */
    private function materialsExistingWorkflow(): array
    {
        return [
            'source'         => 'flowcahrt/قائم.drawio.pdf',
            'description_ar' => 'عقد فحص المواد للأبنية القائمة أو الدراسة الإنشائية. يقدم المكتب الهندسي الطلب، يراجعه مدقق فحص التربة الأول، ثم المدقق الثاني الذي يمكنه اعتماد قرار الأول أو تجاوزه. بعد الاعتماد النهائي يتم الدفع الإلكتروني، وإصدار العقد المصدق (PDF) والوصولات.',
            'description_en' => 'Materials-testing contract for existing buildings or a structural study. The office submits, the first soil auditor reviews, then the second auditor may confirm or override the first. After final approval an electronic payment is taken and the certified contract (PDF) + receipts are issued.',
            'workflow' => [
                'stages' => [
                    $this->stageOfficeSubmission(),
                    $this->stageFirstAuditor('مدقق فحص التربة الأول', 'First Soil Auditor'),
                    $this->stageSecondAuditor(),
                    $this->stagePayment(),
                    $this->stageIssueDocuments(),
                ],
                'metadata' => [
                    'has_first_auditor'         => true,
                    'second_can_override_first' => true,
                ],
                'variants' => [
                    'modification' => [
                        'source'   => 'flowcahrt/تعديلات مواد قائم.drawio.pdf',
                        'label_ar' => 'تعديلات مواد قائم',
                        'label_en' => 'Modify Existing Materials Contract',
                        'stages'   => $this->modificationVariantStages(),
                    ],
                ],
            ],
        ];
    }

    /** @return array{workflow: array<string, mixed>, source: string, description_ar: string, description_en: string} */
    private function soilProposedWorkflow(): array
    {
        return [
            'source'         => 'flowcahrt/تربة مقترح.drawio.pdf',
            'description_ar' => 'تقارير استطلاع الموقع للأبنية المقترحة. يقدم المكتب الهندسي الطلب، يراجعه مدقق التربة الأول (مدقق خارجي)، ثم المدقق الثاني في وحدة الاستطلاع. المدقق الثاني يمكنه تجاوز قرار المدقق الأول عند الاختلاف. بعد الاعتماد يتم دفع الرسوم والضريبة ثم إصدار التقرير المصدق والوصولات.',
            'description_en' => 'Site-survey reports for proposed buildings. The office submits, an external first soil auditor reviews, then the second auditor at the Survey Unit can either confirm the first auditor or override the decision. On final approval fees and tax are paid, then the certified report and receipts are issued.',
            'workflow' => [
                'stages' => [
                    $this->stageOfficeSubmission(),
                    $this->stageFirstAuditor('مدقق خارجي — مدقق فحص التربة الأول', 'External First Soil Auditor'),
                    $this->stageSecondAuditor(),
                    $this->stagePayment('دفع الرسوم والضريبة', 'Pay Fees + Tax'),
                    [
                        'id'        => 'issue_documents',
                        'label_ar'  => 'إصدار الوصولات والتقرير المصدق',
                        'label_en'  => 'Issue Receipts & Certified Report',
                        'role'      => 'staff',
                        'sla_hours' => 24,
                        'actions'   => ['issue_certificate'],
                    ],
                ],
                'metadata' => [
                    'has_first_auditor'         => true,
                    'first_auditor_is_external' => true,
                    'second_can_override_first' => true,
                ],
            ],
        ];
    }

    /** @return array{workflow: array<string, mixed>, source: string, description_ar: string, description_en: string} */
    private function soilExistingWorkflow(): array
    {
        return [
            'source'         => 'flowcahrt/تربة قائم.drawio.pdf',
            'description_ar' => 'تقارير استطلاع الموقع للأبنية القائمة. يقدم المكتب الهندسي الطلب، يراجعه مدقق التربة الأول ثم المدقق الثاني في وحدة الاستطلاع مع إمكانية تجاوز قرار الأول. بعد الاعتماد يتم الدفع الإلكتروني وإصدار العقد المصدق والوصولات.',
            'description_en' => 'Site-survey reports for existing buildings. The office submits, the first soil auditor reviews, then the second auditor at the Survey Unit — with the option to override the first. On approval an electronic payment is taken and the certified contract + receipts are issued.',
            'workflow' => [
                'stages' => [
                    $this->stageOfficeSubmission(),
                    $this->stageFirstAuditor('مدقق فحص التربة الأول', 'First Soil Auditor'),
                    $this->stageSecondAuditor(),
                    $this->stagePayment('الدفع الإلكتروني', 'Electronic Payment'),
                    $this->stageIssueDocuments(),
                ],
                'metadata' => [
                    'has_first_auditor'         => true,
                    'second_can_override_first' => true,
                ],
            ],
        ];
    }

    /** @return array{workflow: array<string, mixed>, source: string, description_ar: string, description_en: string} */
    private function excavationSupportDesignWorkflow(): array
    {
        return [
            'source'         => 'flowcahrt/تصميم تدعيم.drawio.pdf',
            'description_ar' => 'تصميم تدعيم الحفريات مع الإشراف. يقدم المكتب الطلب، يراجعه مدقق التدعيم الخارجي ثم المدقق الثاني في وحدة الاستطلاع. بعد الاعتماد يحدد ما إذا كان الإشراف من نفس مكتب التصميم أم من مكتب آخر. في حال مكاتب منفصلة يتم الدفع الإلكتروني لكل من مكتب التصميم ومكتب الإشراف مع التحقق من كلا الطرفين قبل إصدار التقرير المصدق وعقد الإشراف المصدق والوصولات. يوجد إجراء مالي يعد لاحقاً.',
            'description_en' => 'Excavation-support design together with supervision. The office submits, an external support auditor and then the second auditor at the Survey Unit review. After approval, the applicant chooses whether supervision comes from the same office or an external one. When separate, both the design office and the supervision office pay electronically and both payments are verified before issuing the certified report, certified supervision contract, and receipts. A later financial step follows.',
            'workflow' => [
                'stages' => [
                    $this->stageOfficeSubmission(),
                    $this->stageFirstAuditor('مدقق خارجي — مدقق التدعيم', 'External Support Auditor'),
                    $this->stageSecondAuditor(),
                    [
                        'id'        => 'supervision_setup',
                        'label_ar'  => 'تجهيز متطلبات الإشراف — من نفس المكتب أم مكتب آخر',
                        'label_en'  => 'Set Up Supervision — Same Office vs External Office',
                        'role'      => 'staff',
                        'sla_hours' => 48,
                        'actions'   => ['choose_internal_supervision', 'choose_external_supervision'],
                    ],
                    [
                        'id'        => 'design_office_payment',
                        'label_ar'  => 'الدفع الإلكتروني لمكتب التصميم + الضريبة',
                        'label_en'  => 'Design Office Payment + Tax',
                        'role'      => 'staff',
                        'sla_hours' => 24,
                        'actions'   => ['confirm_payment', 'return_to_office'],
                    ],
                    [
                        'id'        => 'supervision_office_payment',
                        'label_ar'  => 'الدفع الإلكتروني لمكتب الإشراف + الضريبة',
                        'label_en'  => 'Supervision Office Payment + Tax',
                        'role'      => 'staff',
                        'sla_hours' => 24,
                        'actions'   => ['confirm_payment', 'return_to_office'],
                    ],
                    [
                        'id'        => 'issue_documents',
                        'label_ar'  => 'إصدار الوصولات والتقرير المصدق وعقد الإشراف المصدق',
                        'label_en'  => 'Issue Receipts, Certified Report & Supervision Contract',
                        'role'      => 'staff',
                        'sla_hours' => 24,
                        'actions'   => ['issue_certificate'],
                    ],
                ],
                'metadata' => [
                    'has_first_auditor'      => true,
                    'has_supervision_branch' => true,
                    'phase_later_continuation' => true,
                    'notes_ar'               => 'يوجد إجراء مالي يعد لاحقاً بعد إصدار المستندات.',
                ],
                'variants' => [
                    'modification' => [
                        'source'   => 'flowcahrt/تعديلات تصميم التدعيم .drawio.pdf',
                        'label_ar' => 'تعديلات تصميم التدعيم',
                        'label_en' => 'Modify Excavation Support Design',
                        'stages'   => $this->modificationVariantStages(),
                    ],
                ],
            ],
        ];
    }

    /** @return array{workflow: array<string, mixed>, source: string, description_ar: string, description_en: string} */
    private function excavationSupervisionWorkflow(): array
    {
        return [
            'source'         => 'flowcahrt/الاشرا على الحفريات.drawio.pdf',
            'description_ar' => 'الإشراف على أعمال الحفريات أو أعمال التدعيم. يختار المكتب نوع العقد (إشراف على الحفريات أو إشراف على أعمال التدعيم)، يراجعه المدقق الثاني في وحدة الاستطلاع، ثم يتم الدفع الإلكتروني وإصدار العقد المصدق والوصولات. يوجد إجراء مالي يعد لاحقاً.',
            'description_en' => 'Supervision of excavation works or excavation-support works. The office chooses the contract type (excavation vs. support-works supervision), the second auditor at the Survey Unit reviews, then an electronic payment is taken and the certified contract and receipts are issued. A later financial step follows.',
            'workflow' => [
                'stages' => [
                    $this->stageOfficeSubmission(),
                    [
                        'id'        => 'contract_type_selection',
                        'label_ar'  => 'اختيار نوع العقد: إشراف على الحفريات أم إشراف على أعمال التدعيم',
                        'label_en'  => 'Contract Type: Excavation Supervision vs Support Works Supervision',
                        'role'      => 'applicant',
                        'sla_hours' => 24,
                        'actions'   => ['choose_excavation', 'choose_support_works'],
                    ],
                    $this->stageSecondAuditor(),
                    $this->stagePayment('الدفع الإلكتروني', 'Electronic Payment'),
                    $this->stageIssueDocuments(),
                ],
                'metadata' => [
                    'has_contract_type_branch' => true,
                    'phase_later_continuation' => true,
                    'notes_ar'                 => 'إجراء مالي يعد لاحقاً بعد إصدار المستندات.',
                ],
            ],
        ];
    }

    /** @return array{workflow: array<string, mixed>, source: string, description_ar: string, description_en: string} */
    private function visualInspectionWorkflow(): array
    {
        return [
            'source'         => 'flowcahrt/الكشف الحسي.drawio.pdf',
            'description_ar' => 'شهادة الكشف الحسي والكتب الرسمية. يقدم المكتب الطلب، يراجعه المدقق الثاني في وحدة الاستطلاع، ثم يتم الدفع الإلكتروني وإصدار العقد المصدق والوصولات. بعد الإصدار يمكن للمكتب فتح خارطة كشف حسي جديدة لطلب آخر أو إنهاء الإجراء.',
            'description_en' => 'Visual-inspection certificate and official letters. The office submits, the second auditor at the Survey Unit reviews, then an electronic payment is taken and the certified contract and receipts are issued. After issuance, the office can either open a new visual-inspection map for another request or finalize.',
            'workflow' => [
                'stages' => [
                    $this->stageOfficeSubmission(),
                    $this->stageSecondAuditor(),
                    $this->stagePayment('الدفع الإلكتروني', 'Electronic Payment'),
                    $this->stageIssueDocuments(),
                    [
                        'id'        => 'additional_inspection_check',
                        'label_ar'  => 'هل يوجد كشف حسي آخر؟',
                        'label_en'  => 'Is There Another Visual Inspection?',
                        'role'      => 'applicant',
                        'sla_hours' => 24,
                        'actions'   => ['open_new_inspection', 'finalize'],
                    ],
                ],
                'metadata' => [
                    'has_iteration_loop' => true,
                ],
            ],
        ];
    }

    /** @return array{workflow: array<string, mixed>, source: string, description_ar: string, description_en: string} */
    private function slopeStabilityWorkflow(): array
    {
        return [
            'source'         => 'flowcahrt/استقرار المنحدرات.drawio.pdf',
            'description_ar' => 'تقارير استقرار المنحدرات. يقدم المكتب الطلب، يراجعه المدقق الثاني في وحدة الاستطلاع، ثم يمر عبر صفحة الربط مع الخدمات ذات الصلة قبل الاعتماد النهائي من المدقق الثاني. بعد الاعتماد يتم الدفع الإلكتروني والضريبة ثم إصدار العقد المصدق والوصولات.',
            'description_en' => 'Slope-stability reports. The office submits, the second auditor at the Survey Unit reviews, then the request passes through a linked-services page before the second auditor gives final approval. After approval, an electronic payment (including tax) is taken and the certified contract + receipts are issued.',
            'workflow' => [
                'stages' => [
                    $this->stageOfficeSubmission(),
                    $this->stageSecondAuditor(),
                    $this->stagePayment('الدفع الإلكتروني + الضريبة', 'Electronic Payment + Tax'),
                    $this->stageIssueDocuments(),
                ],
                'metadata' => [
                    'has_link_page' => true,
                    'notes_ar'      => 'الرابط لصفحة أخرى للربط مع خدمات ذات صلة.',
                ],
                'variants' => [
                    'modification' => [
                        'source'   => 'flowcahrt/تعديل استقرار المنحدرات .drawio.pdf',
                        'label_ar' => 'تعديل استقرار المنحدرات',
                        'label_en' => 'Modify Slope Stability Report',
                        'stages'   => $this->modificationVariantStages(),
                    ],
                ],
            ],
        ];
    }
}
