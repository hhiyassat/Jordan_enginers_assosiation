<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * CatalogWorkflowsSeeder
 *
 * Applies the same treatment SurveyWorkflowsSeeder gives the 8 flowchart-backed
 * survey services to the remaining 44 catalogue services: replaces the
 * placeholder_review stub with a real workflow.stages array based on category
 * norms, adds workflow metadata + a modification variant where applicable, and
 * annotates schema.workflow_source with 'catalog:2026' (as opposed to a
 * flowchart PDF) so future edits know where the workflow came from.
 *
 * Grouped by category with shared workflow templates:
 *   drawings          — 5-stage standard (JEA-PROJ children, DRW-P-*)
 *   drawingsSafety    — adds a safety-review stage (demolition, civil defence)
 *   drawingsEnhanced  — committee review for large projects + energy
 *   drawingsSimple    — 3-stage for re-approval only
 *   financial         — 4-stage disbursement flow (FIN-*)
 *   certificate       — 5-stage issuance flow (CERT-*)
 *   certificateSite   — adds a site-inspection stage (conformity + safety)
 *   engineer          — 4-stage registration flow (ENG-*)
 *   board             — 5-stage board decision flow (DEC-*)
 *   directResponse    — 2-stage inquiry / report generation
 *   inspection        — 4-stage inspection service (MSC-012)
 *   booking           — 3-stage appointment booking (MSC-011)
 *   contract          — 4-stage supervision-contract management
 *   recruitment       — 3-stage recruitment platform
 *   modificationVar   — reusable variant (data-source → classify → review → payment → issue)
 *
 * Run: php artisan db:seed --class=CatalogWorkflowsSeeder
 */
class CatalogWorkflowsSeeder extends Seeder
{
    private const SOURCE = 'catalog:2026';

    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        $mappings = $this->buildMappings();

        $updated = 0;
        foreach ($mappings as $code => $workflowBundle) {
            $service = ServiceDefinition::where('organization_id', $org->id)
                ->where('code', $code)
                ->first();
            if (!$service) continue;

            $schema = $service->schema;
            $schema['workflow'] = $workflowBundle;
            $schema['workflow_source'] = self::SOURCE;
            // Preserve any flowchart_source that may already exist — a service
            // could have a flowchart written later, this seeder just fills
            // the gap when there isn't one.
            $service->schema = $schema;
            $service->save();
            $updated++;
        }

        $this->command->info("✓ Catalog workflows applied to {$updated} services (out of " . count($mappings) . ' mapped).');
    }

    /* ── Stage helpers ────────────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function stage(string $id, string $labelAr, string $labelEn, string $role, int $slaHours, array $actions): array
    {
        return [
            'id'        => $id,
            'label_ar'  => $labelAr,
            'label_en'  => $labelEn,
            'role'      => $role,
            'sla_hours' => $slaHours,
            'actions'   => $actions,
        ];
    }

    /** @return array<string, mixed> */
    private function officeSubmission(): array
    {
        return $this->stage('office_submission', 'تقديم الطلب من المكتب الهندسي', 'Office Submission', 'applicant', 24, ['submit']);
    }

    /** @return array<string, mixed> */
    private function firstAuditor(string $labelAr = 'مراجعة المدقق الأول', string $labelEn = 'First Auditor Review'): array
    {
        return $this->stage('first_auditor_review', $labelAr, $labelEn, 'staff', 48, ['approve', 'request_modifications', 'reject']);
    }

    /** @return array<string, mixed> */
    private function secondAuditor(string $labelAr = 'مراجعة المدقق الثاني', string $labelEn = 'Second Auditor Review'): array
    {
        return $this->stage('second_auditor_review', $labelAr, $labelEn, 'auditor', 72, ['approve', 'request_modifications', 'reject', 'override_first_auditor']);
    }

    /** @return array<string, mixed> */
    private function payment(string $labelAr = 'دفع الرسوم والضريبة', string $labelEn = 'Pay Fees + Tax'): array
    {
        return $this->stage('payment', $labelAr, $labelEn, 'staff', 24, ['confirm_payment']);
    }

    /** @return array<string, mixed> */
    private function issueDocuments(string $labelAr = 'إصدار الوصولات والوثيقة المصدقة', string $labelEn = 'Issue Receipts & Certified Document'): array
    {
        return $this->stage('issue_documents', $labelAr, $labelEn, 'staff', 24, ['issue_certificate']);
    }

    /* ── Workflow templates ───────────────────────────────────────────── */

    /**
     * Standard 5-stage drawings/certification workflow with second-auditor override.
     * Used by most JEA-PROJ children and by CERT-003..006.
     *
     * @return array<string, mixed>
     */
    private function standardCertification(): array
    {
        return [
            'stages' => [
                $this->officeSubmission(),
                $this->firstAuditor(),
                $this->secondAuditor(),
                $this->payment(),
                $this->issueDocuments(),
            ],
            'metadata' => [
                'has_first_auditor'         => true,
                'second_can_override_first' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function drawingsSafety(): array
    {
        // Adds a dedicated safety-review stage before the technical review —
        // used for demolition and civil-defence drawings where public safety
        // dominates the review criteria.
        return [
            'stages' => [
                $this->officeSubmission(),
                $this->stage('safety_review', 'مراجعة السلامة العامة', 'Public Safety Review', 'auditor', 48, ['approve', 'request_modifications', 'reject']),
                $this->secondAuditor('المراجعة الفنية النهائية', 'Final Technical Review'),
                $this->payment(),
                $this->issueDocuments(),
            ],
            'metadata' => [
                'has_safety_review' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function drawingsEnhanced(): array
    {
        // Large projects and energy projects go through a committee review
        // rather than a single-auditor review — captured as a distinct stage.
        return [
            'stages' => [
                $this->officeSubmission(),
                $this->firstAuditor('المراجعة الفنية الأولية', 'Initial Technical Review'),
                $this->stage('committee_review', 'مراجعة اللجنة المتخصصة', 'Specialist Committee Review', 'auditor', 168, ['approve', 'request_modifications', 'reject']),
                $this->payment(),
                $this->issueDocuments(),
            ],
            'metadata' => [
                'has_committee_review' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function drawingsSimple(): array
    {
        // Re-approval — no technical review, just stamp renewal.
        return [
            'stages' => [
                $this->officeSubmission(),
                $this->stage('validity_check', 'التحقق من صلاحية التصديق السابق', 'Prior Approval Validity Check', 'staff', 24, ['approve', 'reject']),
                $this->payment(),
                $this->issueDocuments('إصدار الوصولات والختم المجدد', 'Issue Receipts & Renewed Stamp'),
            ],
            'metadata' => [
                'is_re_approval' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function financial(): array
    {
        return [
            'stages' => [
                $this->officeSubmission(),
                $this->stage('financial_verification', 'التحقق المالي والوثائق المؤيدة', 'Financial Verification & Supporting Documents', 'staff', 48, ['approve', 'request_modifications', 'reject']),
                $this->stage('cfo_approval', 'موافقة المدير المالي', 'CFO Approval', 'admin', 72, ['approve', 'reject']),
                $this->stage('disbursement', 'صرف المستحقات إلى الحسابات', 'Disburse Payment to Accounts', 'staff', 24, ['disburse_payment']),
            ],
            'metadata' => [
                'is_financial_disbursement' => true,
                'no_certificate_issued'     => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function certificate(): array
    {
        // Office / classification / ownership / specialisation certificates —
        // no site visit needed.
        return [
            'stages' => [
                $this->officeSubmission(),
                $this->stage('document_review', 'مراجعة الوثائق المؤيدة', 'Supporting Documents Review', 'staff', 48, ['approve', 'request_modifications', 'reject']),
                $this->secondAuditor('اعتماد الهيئة', 'Board Approval'),
                $this->payment(),
                $this->stage('issue_certificate', 'إصدار الشهادة الرسمية', 'Issue Official Certificate', 'staff', 24, ['issue_certificate']),
            ],
            'metadata' => [
                'issues_certificate' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function certificateSite(): array
    {
        // Conformity + structural-safety certificates need a physical site
        // inspection before the technical review — modelled as an extra stage.
        return [
            'stages' => [
                $this->officeSubmission(),
                $this->stage('site_inspection', 'الكشف الميداني على الموقع', 'Field Site Inspection', 'staff', 96, ['approve', 'reject']),
                $this->stage('document_review', 'مراجعة الوثائق والتقارير الفنية', 'Documents & Technical Reports Review', 'staff', 48, ['approve', 'request_modifications', 'reject']),
                $this->secondAuditor('الاعتماد النهائي', 'Final Approval'),
                $this->payment(),
                $this->stage('issue_certificate', 'إصدار الشهادة الرسمية', 'Issue Official Certificate', 'staff', 24, ['issue_certificate']),
            ],
            'metadata' => [
                'issues_certificate' => true,
                'has_site_inspection' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function engineer(): array
    {
        return [
            'stages' => [
                $this->officeSubmission(),
                $this->stage('data_verification', 'التحقق من بيانات المهندس', 'Engineer Data Verification', 'staff', 48, ['approve', 'request_modifications', 'reject']),
                $this->stage('board_approval', 'اعتماد الهيئة', 'Board Approval', 'auditor', 72, ['approve', 'reject']),
                $this->stage('registration_update', 'تحديث السجل والتأمينات', 'Registration & Securities Update', 'staff', 24, ['register_engineer']),
            ],
            'metadata' => [
                'affects_office_quota' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function board(): array
    {
        return [
            'stages' => [
                $this->officeSubmission(),
                $this->stage('secretariat_review', 'مراجعة الأمانة العامة', 'General Secretariat Review', 'staff', 72, ['approve', 'request_modifications', 'reject']),
                $this->stage('board_hearing', 'جلسة الاستماع أمام الهيئة', 'Board Hearing Session', 'auditor', 336, ['approve', 'reject']),
                $this->stage('decision', 'إصدار القرار', 'Issue Decision', 'auditor', 72, ['issue_decision']),
                $this->stage('notification', 'إشعار الأطراف', 'Notify Parties', 'staff', 24, ['notify_parties']),
            ],
            'metadata' => [
                'is_board_decision' => true,
                'appeal_allowed'    => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function directResponse(): array
    {
        return [
            'stages' => [
                $this->stage('office_request', 'طلب الخدمة من المكتب', 'Office Requests the Service', 'applicant', 1, ['submit']),
                $this->stage('serve_response', 'إعداد وتسليم النتيجة', 'Prepare & Serve Response', 'staff', 24, ['serve_document']),
            ],
            'metadata' => [
                'is_direct_response' => true,
                'no_review_needed'   => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function dataUpdate(): array
    {
        return [
            'stages' => [
                $this->officeSubmission(),
                $this->stage('data_verification', 'التحقق من التحديثات المطلوبة', 'Verify Requested Updates', 'staff', 48, ['approve', 'request_modifications', 'reject']),
                $this->stage('apply_updates', 'اعتماد التحديث في السجل', 'Apply Updates to the Record', 'staff', 24, ['confirm_data_update']),
            ],
            'metadata' => [
                'is_data_update' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function inspection(): array
    {
        return [
            'stages' => [
                $this->officeSubmission(),
                $this->stage('schedule_inspection', 'جدولة موعد الكشف', 'Schedule Inspection Appointment', 'staff', 48, ['schedule_inspection']),
                $this->stage('conduct_inspection', 'إجراء الكشف الميداني', 'Conduct Field Inspection', 'staff', 96, ['conduct_inspection']),
                $this->stage('issue_report', 'إصدار تقرير الكشف المصدق', 'Issue Certified Inspection Report', 'auditor', 48, ['issue_certificate']),
            ],
            'metadata' => [
                'has_field_inspection' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function booking(): array
    {
        return [
            'stages' => [
                $this->officeSubmission(),
                $this->stage('select_slot', 'اختيار الموعد المتاح', 'Select Available Slot', 'applicant', 24, ['book_slot']),
                $this->stage('confirm_appointment', 'تأكيد الموعد وإشعار الطرفين', 'Confirm Appointment & Notify Both Parties', 'staff', 24, ['notify_parties']),
            ],
            'metadata' => [
                'is_appointment_booking' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function contract(): array
    {
        // Supervision-contract management (transfer / extend / cancel /
        // clearance). Same shape for all four — differences are in payload
        // fields, not workflow stages.
        return [
            'stages' => [
                $this->officeSubmission(),
                $this->stage('obligations_check', 'التحقق من الالتزامات القائمة', 'Verify Outstanding Obligations', 'staff', 72, ['approve', 'request_modifications', 'reject']),
                $this->payment('دفع الرسوم', 'Pay Fees'),
                $this->issueDocuments('إصدار العقد المعدل الموثق', 'Issue Notarised Amended Contract'),
            ],
            'metadata' => [
                'is_contract_management' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function recruitment(): array
    {
        return [
            'stages' => [
                $this->stage('office_posting', 'نشر الوظيفة من قبل المكتب', 'Office Posts the Job', 'applicant', 24, ['submit']),
                $this->stage('moderation', 'مراجعة الإعلان قبل النشر', 'Moderate Posting Before Publication', 'staff', 24, ['approve', 'reject']),
                $this->stage('publish_and_match', 'نشر الإعلان ومطابقة المرشحين', 'Publish Posting & Match Candidates', 'staff', 24, ['publish_posting', 'match_candidate']),
            ],
            'metadata' => [
                'is_recruitment' => true,
                'no_certificate_issued' => true,
            ],
        ];
    }

    /* ── Modification variant ────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function modificationVariant(): array
    {
        return [
            'label_ar' => 'تعديل طلب سابق',
            'label_en' => 'Modify Previous Application',
            'stages'   => [
                $this->stage('select_data_source', 'حالة الطلب: نظام جديد أم قديم / ورقي', 'Application Source: New System vs Legacy / Paper', 'applicant', 24, ['choose_new_system', 'choose_legacy_system']),
                $this->stage('ingest_or_reenter_data', 'استدعاء البيانات وتحديد التعديلات أو إعادة الإدخال', 'Retrieve Data & Define Modifications — or Re-enter', 'applicant', 48, ['confirm_data']),
                $this->stage('classify_modification', 'تحديد نوع التعديل: فني أم إداري', 'Modification Type: Technical vs Administrative', 'staff', 24, ['classify_technical', 'classify_administrative']),
                $this->secondAuditor(),
                $this->payment('الدفع الإلكتروني والضريبة', 'Electronic Payment + Tax'),
                $this->issueDocuments(),
            ],
        ];
    }

    /* ── Code → workflow bundle ──────────────────────────────────────── */

    /** @return array<string, array<string, mixed>> */
    private function buildMappings(): array
    {
        $withMod = fn(array $wf) => $wf + ['variants' => ['modification' => $this->modificationVariant()]];

        return [
            // ── JEA-PROJ Drawings ────────────────────────────────────
            'DRW-P-001' => $this->standardCertification(),
            'DRW-P-002' => $this->standardCertification(),
            'DRW-P-003' => $this->standardCertification(),
            'DRW-P-004' => $this->drawingsSafety(),
            'DRW-P-005' => $this->drawingsEnhanced(),
            'DRW-P-006' => $this->drawingsEnhanced(),
            'DRW-P-007' => $this->standardCertification(),
            'DRW-P-008' => $withMod($this->standardCertification()),
            'DRW-P-009' => $withMod($this->standardCertification()),
            'DRW-P-010' => $withMod($this->standardCertification()),
            'DRW-P-011' => $this->drawingsSimple(),
            'DRW-P-012' => $this->drawingsSafety(),

            // ── JEA-FIN Financial ─────────────────────────────────────
            'FIN-001' => $this->financial(),
            'FIN-002' => $this->financial(),
            'FIN-003' => $this->financial(),
            'FIN-004' => $this->financial(),
            'FIN-005' => $this->financial(),
            'FIN-006' => $this->financial(),

            // ── JEA-CERT Certificates ─────────────────────────────────
            'CERT-001' => $this->certificateSite(),
            'CERT-002' => $this->certificateSite(),
            'CERT-003' => $this->certificate(),
            'CERT-004' => $this->certificate(),
            'CERT-005' => $this->certificate(),
            // CERT-006 "باقي الشهادات الرسمية" — catch-all for miscellaneous
            // certificates. Uses the same review-and-issue flow as the other
            // JEA-CERT rows; the specific certificate type is captured in the
            // application form so reviewers can route it to the right desk.
            'CERT-006' => $this->certificate(),

            // ── JEA-ENG Engineers ─────────────────────────────────────
            'ENG-001' => $this->engineer(),
            'ENG-002' => $this->engineer(),

            // ── JEA-DEC Board Decisions ───────────────────────────────
            'DEC-001' => $this->board(),
            'DEC-002' => $this->board(),
            'DEC-003' => $this->board(),
            'DEC-004' => $this->board(),

            // ── JEA-SURV survey services without a flowchart ─────────
            //    (the 8 with flowcharts are handled by SurveyWorkflowsSeeder;
            //    this seeder covers the remaining 7 with catalog templates)
            'SRV-003' => $this->standardCertification(),
            'SRV-004' => $this->drawingsEnhanced(),
            'SRV-005' => $this->drawingsEnhanced(),
            'SRV-006' => $this->standardCertification(),
            'SRV-010' => $withMod($this->standardCertification()),
            'SRV-011' => $this->drawingsSimple(),
            'SRV-013' => $this->drawingsSimple(),

            // ── JEA-MISC Miscellaneous ────────────────────────────────
            'MSC-001' => $this->directResponse(),
            'MSC-002' => $this->directResponse(),
            'MSC-003' => $this->directResponse(),
            'MSC-004' => $this->dataUpdate(),
            'MSC-005' => $this->standardCertification(),
            'MSC-006' => $this->directResponse(),
            'MSC-007' => $this->contract(),
            'MSC-008' => $this->contract(),
            'MSC-009' => $this->contract(),
            'MSC-010' => $this->contract(),
            'MSC-011' => $this->booking(),
            'MSC-012' => $this->inspection(),
            // MSC-013 workflow retired with the catch-all row.
            'MSC-014' => $this->recruitment(),
        ];
    }
}
