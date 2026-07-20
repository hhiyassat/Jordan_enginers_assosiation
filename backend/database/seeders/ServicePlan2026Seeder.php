<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * ServicePlan2026Seeder
 *
 * Seeds the full JEA services catalog per service-plan-payment.pdf (2026):
 * 57 services across 7 categories with delivery phase (1..5) set on each.
 * (Plan PDF footer lists 58; MSC-013 "الكتب والنماذج المتاحة للمكاتب"
 *  remains dropped as a vague catch-all — it's a documents library,
 *  not a workflow. CERT-006 was restored per JEA product decision.)
 *
 * The seeder is idempotent (updateOrCreate on code) and safe to re-run
 * after schema tweaks. It also:
 *   - Adds the new JEA-SURV top-level tile (استطلاع الموقع) that the
 *     initial JeaPortalTilesSeeder didn't include.
 *   - Soft-deletes the legacy DRW-001..DRW-007 placeholders (older
 *     JeaDrawingsSeeder demo data) which are superseded by the plan's
 *     12 drawing services under the same parent.
 *
 * Phase counts (post CERT-006 restore, MSC-013 still dropped): 57 total
 *   phase 1 = 20, phase 2 = 13, phase 3 = 12, phase 4 = 4, phase 5 = 8.
 */
class ServicePlan2026Seeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        // 1. Retire legacy DRW placeholders — replaced by the plan's drawings.
        $legacy = ['DRW-001', 'DRW-002', 'DRW-003', 'DRW-004', 'DRW-005', 'DRW-006', 'DRW-007'];
        $deleted = ServiceDefinition::where('organization_id', $org->id)
            ->whereIn('code', $legacy)
            ->delete();

        // 2. Ensure the new top-level tile استطلاع الموقع exists.
        $this->upsert($org->id, [
            'code' => 'JEA-SURV',
            'name_ar' => 'استطلاع الموقع',
            'name_en' => 'Site Survey',
            'description_ar' => 'تقارير استطلاع الموقع وفحص المواد',
            'description_en' => 'Site survey reports and material testing',
            'parent_code' => null,
            'phase' => null,
        ]);

        // 3. Bulk-seed the 57 services.
        foreach ($this->services() as $svc) {
            $this->upsert($org->id, $svc);
        }

        // 4. Update descriptions on top-level tiles (JEA-CERT, JEA-FIN, ...)
        //    which were seeded originally by JeaPortalTilesSeeder — the tiles
        //    themselves aren't in services() so we set them here so refresh
        //    runs keep the paragraph copy in sync with the DESCRIPTIONS map.
        foreach (['JEA-CERT', 'JEA-FIN', 'JEA-PROJ', 'JEA-MISC', 'JEA-DEC', 'JEA-ENG', 'JEA-SURV'] as $tileCode) {
            $desc = self::DESCRIPTIONS[$tileCode] ?? null;
            if (!$desc) continue;
            ServiceDefinition::where('organization_id', $org->id)
                ->where('code', $tileCode)
                ->update([
                    'description_ar' => $desc['ar'],
                    'description_en' => $desc['en'],
                ]);
        }

        // Report the counts so operators can confirm against the plan.
        $counts = [];
        for ($p = 1; $p <= 5; $p++) {
            $counts[$p] = ServiceDefinition::where('organization_id', $org->id)
                ->where('phase', $p)
                ->count();
        }
        $this->command->info("✓ Legacy placeholders soft-deleted: {$deleted}");
        $this->command->info('✓ ServicePlan2026 seeded — phase counts: '
            . "1={$counts[1]}, 2={$counts[2]}, 3={$counts[3]}, 4={$counts[4]}, 5={$counts[5]}");
    }

    /** @param array<string, mixed> $data */
    private function upsert(int $orgId, array $data): void
    {
        $data['organization_id'] = $orgId;
        $data['currency']      ??= 'JOD';
        $data['status']        ??= 'active';
        $data['name_en']       ??= $data['name_ar'];
        $data['schema']        ??= $this->placeholderSchema(
            $data['code'], $data['name_ar'], $data['name_en']
        );

        // Bilingual descriptions live in a separate map keyed by code so the
        // services() list stays readable. The map is the source of truth —
        // any short inline description gets replaced by the paragraph. Survey
        // services (SRV-*) are intentionally not in this map because
        // SurveyWorkflowsSeeder writes their descriptions from the flowchart
        // PDFs; those overwrite whatever ServicePlan2026Seeder set here.
        $desc = self::DESCRIPTIONS[$data['code']] ?? null;
        if ($desc) {
            $data['description_ar'] = $desc['ar'];
            $data['description_en'] = $desc['en'];
        }

        ServiceDefinition::updateOrCreate(
            ['organization_id' => $orgId, 'code' => $data['code']],
            $data
        );
    }

    /**
     * Bilingual description map for every non-survey service.
     * Survey services (SRV-*) get their descriptions from SurveyWorkflowsSeeder
     * because those are derived from real flowchart PDFs.
     *
     * @var array<string, array{ar: string, en: string}>
     */
    private const DESCRIPTIONS = [
        // ── Top-level tiles ──────────────────────────────────────────
        'JEA-CERT' => [
            'ar' => 'بوابة الشهادات الرسمية الصادرة عن نقابة المهندسين الأردنيين — شهادات المطابقة وسلامة المنشأ وتصنيف المكاتب واختصاصاتها وملكيتها، بالإضافة إلى باقي الشهادات الرسمية للمكاتب والمهندسين.',
            'en' => 'Gateway to official certificates issued by the Jordan Engineers Association — conformity, structural safety, office classification, ownership, and specialisation certificates, plus additional official documents for offices and engineers.',
        ],
        'JEA-FIN' => [
            'ar' => 'الخدمات المالية للمكاتب الهندسية: رواتب المهندسين المقيمين، دعم الإشراف، وإعادة التأمينات على العقود المؤجلة (استطلاع الموقع، تدعيم الحفريات، والمخططات الهندسية).',
            'en' => 'Financial services for engineering offices: resident engineer salaries, supervision support disbursement, and refunds on deferred contract securities (site survey, excavation support, and engineering drawings).',
        ],
        'JEA-PROJ' => [
            'ar' => 'ملف مشاريع المكتب. يفتح المكتب مشروعاً جديداً أو يختار مشروعاً قائماً، ومن ثم يقدم طلبات التصديق على المخططات الهندسية بأنواعها (سكنية، تجارية، صناعية، حكومية، طاقة، هدم، تعديلات) عبر ورشة عمل موحدة.',
            'en' => 'Office projects folder. The office opens a new project or selects an existing one, then submits engineering-drawings approval requests across all types (residential, commercial, industrial, government, energy, demolition, modifications) from a unified workspace.',
        ],
        'JEA-MISC' => [
            'ar' => 'خدمات متفرقة تشمل الاستعلامات العامة، تحديث البيانات، طلبات الإعفاء والمخالصات، إدارة عقود الإشراف، الكشوفات الهندسية، مكتبة النماذج، ومنصة التوظيف.',
            'en' => 'Miscellaneous services covering general inquiries, data updates, exemption and clearance requests, supervision contract management, engineering inspections, templates library, and the recruitment platform.',
        ],
        'JEA-DEC' => [
            'ar' => 'بوابة تقديم الطلبات والشكاوى إلى هيئة المكاتب الهندسية — من المكاتب أنفسها، من المواطنين، من الدوائر الحكومية، أو من المهندسين للاطلاع على القرارات وتقديم الاعتراضات.',
            'en' => 'Portal for submitting requests and complaints to the Engineering Offices Board — from offices themselves, from citizens, from government departments, or from engineers to review decisions and file objections.',
        ],
        'JEA-ENG' => [
            'ar' => 'إدارة المهندسين العاملين ضمن كادر المكتب الهندسي أو ضمن كادر مشروع محدد — إجراءات التعيين والتحويل والإقالة مع توثيق البيانات لدى النقابة.',
            'en' => 'Management of engineers working within the office staff or within a specific project team — assignment, transfer, and termination procedures with proper documentation at the JEA.',
        ],
        'JEA-SURV' => [
            'ar' => 'استطلاع الموقع الهندسي بمختلف أنواعه — تقارير التربة للأبنية المقترحة والقائمة، فحص المواد، تدعيم الحفريات، الكشف الحسي، والكتب الرسمية — بالإضافة إلى استقرار المنحدرات وإعادة التصديق. لكل خدمة مسار خاص يشمل مدققين متخصصين.',
            'en' => 'Site-survey services in all their forms — soil reports for proposed and existing buildings, material testing, excavation support, visual inspections, and official letters — plus slope stability and re-approval. Each service has a dedicated workflow with specialised auditors.',
        ],

        // ── Survey services without flowcharts — SurveyWorkflowsSeeder
        // handles the 8 that do; these 7 stay under ServicePlan2026Seeder.
        'SRV-003' => [
            'ar' => 'تقارير استطلاع الموقع للمشاريع المؤجلة التي أعيد تفعيلها. يقدم المكتب الطلب مع بيانات المشروع الأصلي وسبب التأجيل، وتخضع لمراجعة فنية مبسطة قبل إصدار التقرير المصدق.',
            'en' => 'Site-survey reports for deferred projects that have been re-activated. The office submits the request with original project data and the deferral reason, subject to streamlined technical review before the certified report is issued.',
        ],
        'SRV-004' => [
            'ar' => 'تقارير استطلاع الموقع للمشاريع الكبرى (مجمعات، أبراج، مولات). تشمل دراسات جيوتقنية معمقة وتحليلاً زلزالياً، وتخضع لمراجعة من فريق مدققين متعدد التخصصات قبل الاعتماد.',
            'en' => 'Site-survey reports for large-scale projects (complexes, towers, malls). Include enhanced geotechnical studies and seismic analysis, subject to review by a multi-disciplinary auditor panel before approval.',
        ],
        'SRV-005' => [
            'ar' => 'تقارير استطلاع الموقع لمشاريع الطاقة (محطات توليد، مزارع رياح وشمسية، ومحطات فرعية). تشمل التقييم البيئي والاشتراطات الخاصة بالطاقة المتجددة قبل الاعتماد.',
            'en' => 'Site-survey reports for energy projects (power stations, wind and solar farms, substations). Include environmental assessment and renewable-energy specific requirements before approval.',
        ],
        'SRV-006' => [
            'ar' => 'تقارير استطلاع الموقع للمشاريع الحكومية بحسب الاشتراطات الخاصة بالجهات الرسمية. تخضع لمراجعة فنية معتمدة قبل رفع التقرير المصدق للجهة الحكومية المعنية.',
            'en' => 'Site-survey reports for government projects following the specific requirements of official authorities. Subject to accredited technical review before the certified report is submitted to the government body concerned.',
        ],
        'SRV-010' => [
            'ar' => 'إعادة تصديق تقرير استطلاع موقع سبق اعتماده مع إضافات فنية جديدة (طوابق إضافية، توسعات، تعديل بيانات المشروع). تشمل مراجعة الإضافات مع التقرير الأصلي قبل الاعتماد.',
            'en' => 'Re-approval of a previously certified site-survey report with new technical additions (extra floors, extensions, project data updates). Review covers the additions together with the original report before approval.',
        ],
        'SRV-011' => [
            'ar' => 'إعادة تصديق تقرير استطلاع موقع سبق اعتماده دون إضافات فنية — لأغراض تجديد الختم أو تحديث بيانات إدارية. مسار مبسط بدون إعادة الفحص الفني.',
            'en' => 'Re-approval of a previously certified site-survey report without technical additions — for stamp renewal or administrative-data updates. Streamlined path with no full technical review.',
        ],
        'SRV-013' => [
            'ar' => 'إصدار بدل فاقد لتقرير استطلاع موقع مصدق فُقد أو تلف. يقدم المكتب طلب البدل مع الوثائق المؤيدة، ويصدر البدل بعد التحقق من الأصل في سجلات النقابة.',
            'en' => 'Issuance of a replacement copy for a certified site-survey report that was lost or damaged. The office submits the replacement request with supporting documents; the copy is issued after verification against the JEA records.',
        ],

        // ── JEA-PROJ Drawings — 12 services ──────────────────────────
        'DRW-P-001' => [
            'ar' => 'تصديق مخططات المباني السكنية والتجارية والصناعية الجديدة قبل البدء بالتنفيذ. يقدم المكتب الهندسي المخططات المعمارية والإنشائية والكهروميكانيكية للمراجعة الفنية من قبل مدققي النقابة، ثم يتم إصدار وثيقة التصديق المعتمدة.',
            'en' => 'Approval of drawings for new residential, commercial, and industrial buildings before construction begins. The office submits architectural, structural, and MEP drawings for technical review by JEA auditors, then the certified approval document is issued.',
        ],
        'DRW-P-002' => [
            'ar' => 'تصديق مخططات الأبنية القائمة لأغراض التوثيق الرسمي أو تحديث سجلات المكتب. يقدم المكتب الهندسي المخططات المعمارية والإنشائية للمبنى القائم مع صور فوتوغرافية للحالة الحالية، وتخضع للمراجعة الفنية قبل إصدار المخطط المصدق.',
            'en' => 'Approval of drawings for existing buildings for official documentation or office record updates. The office submits architectural and structural drawings of the standing structure with current-state photos, subject to technical review before the certified plan is issued.',
        ],
        'DRW-P-003' => [
            'ar' => 'تصديق مخططات الإضافات والطوابق الجديدة فوق أبنية قائمة. تشمل المراجعة الفنية التحقق من قدرة الهيكل الحالي على تحمل الأحمال الجديدة بالإضافة إلى المخططات المعمارية للإضافات قبل الاعتماد النهائي.',
            'en' => 'Approval of drawings for additions and new floors above existing buildings. Technical review includes verifying the existing structure can bear the new loads, alongside the architectural drawings of the additions before final approval.',
        ],
        'DRW-P-004' => [
            'ar' => 'تصديق مخططات هدم المباني الكاملة أو الجزئية. تشمل المراجعة تقييم مخاطر الهدم على الجيران والبيئة، تحديد أسلوب الهدم الآمن، وتأمين المخلفات، مع الاعتماد قبل البدء بأعمال الهدم.',
            'en' => 'Approval of drawings for full or partial building demolition. Review covers demolition risk to neighbours and environment, safe demolition method, and debris handling — approved before demolition begins.',
        ],
        'DRW-P-005' => [
            'ar' => 'تصديق مخططات المشاريع الكبرى (المجمعات السكنية، الأبراج، المولات، والمشاريع التي تتجاوز حجماً معيناً). تخضع لمراجعة فنية معمقة تشمل الدراسات الجيوتقنية والزلزالية بالإضافة إلى المخططات المعمارية والإنشائية.',
            'en' => 'Approval of drawings for large-scale projects (residential complexes, towers, malls, and projects exceeding a size threshold). Subject to enhanced technical review covering geotechnical and seismic studies alongside architectural and structural drawings.',
        ],
        'DRW-P-006' => [
            'ar' => 'تصديق مخططات مشاريع الطاقة (محطات توليد، مزارع رياح وشمسية، ومحطات فرعية). تشمل المراجعة الفحص الفني لمنظومات الحماية الكهربائية والاشتراطات البيئية والاستدامة.',
            'en' => 'Approval of drawings for energy projects (power stations, wind and solar farms, and substations). Review includes technical inspection of electrical protection systems, environmental requirements, and sustainability aspects.',
        ],
        'DRW-P-007' => [
            'ar' => 'تصديق مخططات المحلات التجارية الصغيرة اللازمة لاستخراج رخصة المهنة من البلديات. مسار مبسط للمحلات التي لا تتجاوز مساحة معينة، مع مراجعة فنية سريعة وإصدار المخطط المصدق.',
            'en' => 'Approval of drawings for small commercial premises required for professional-license issuance from municipalities. Streamlined path for premises below a size threshold, with fast-track technical review and issuance.',
        ],
        'DRW-P-008' => [
            'ar' => 'تصديق التعديلات على مخططات معتمدة سابقاً — سواء تعديلات فنية على التصميم أو تعديلات إدارية على البيانات. يستدعي المكتب المخطط الأصلي، يحدد نطاق التعديل، ثم يخضع للمراجعة الفنية.',
            'en' => 'Approval of modifications to previously certified drawings — whether technical design changes or administrative data corrections. The office retrieves the original drawing, defines the modification scope, and it is reviewed technically.',
        ],
        'DRW-P-009' => [
            'ar' => 'تصديق مخططات إعادة تأهيل الأبنية القائمة أو أعمال الصيانة الكبرى. تشمل مخططات الأعمال الإنشائية والمعمارية اللازمة لإعادة المبنى إلى حالة مطابقة للمعايير الحديثة.',
            'en' => 'Approval of drawings for rehabilitation of existing buildings or major maintenance works. Covers structural and architectural drawings needed to bring the building up to current codes.',
        ],
        'DRW-P-010' => [
            'ar' => 'تصديق التعديلات المطلوبة لتطابق المخططات مع اشتراطات مجلس البناء الوطني الأردني. يقدم المكتب المخططات الأصلية مع بيان تعديلات المطابقة، وتخضع لمراجعة فنية متخصصة.',
            'en' => 'Approval of modifications required to align drawings with the Jordan National Building Council requirements. The office submits the originals with a compliance-changes statement, subject to specialised technical review.',
        ],
        'DRW-P-011' => [
            'ar' => 'إعادة تصديق مخططات معتمدة سابقاً انتهت صلاحيتها أو تحتاج إلى تحديث ختم. لا يشمل تعديلات فنية، فقط تجديد التصديق للأغراض الرسمية.',
            'en' => 'Re-approval of previously certified drawings whose validity has expired or whose stamp needs refreshing. Does not include technical modifications — only renewal of the approval for official purposes.',
        ],
        'DRW-P-012' => [
            'ar' => 'تصديق مخططات أنظمة السلامة والحماية من الحريق التي تشترطها مديرية الدفاع المدني. تشمل مسارات الإخلاء، أنظمة الإطفاء والإنذار، وتغطية أجهزة الاستشعار.',
            'en' => 'Approval of safety and fire-protection system drawings required by the Civil Defence Directorate. Covers evacuation routes, extinguishing and alarm systems, and detector coverage.',
        ],

        // ── JEA-FIN Financial — 6 services ────────────────────────────
        'FIN-001' => [
            'ar' => 'صرف رواتب المهندسين المقيمين للمكاتب المستحقة لدعم الإشراف من النقابة. يتم التحقق من ساعات العمل المسجلة ومطابقتها مع عقود الإشراف قبل صرف المستحقات إلى حسابات المهندسين مباشرة.',
            'en' => 'Disbursement of resident engineer salaries to offices eligible for JEA supervision support. Recorded working hours are verified against supervision contracts before payments are made directly to the engineers\' accounts.',
        ],
        'FIN-002' => [
            'ar' => 'صرف دعم الإشراف المستحق للمكاتب الهندسية وفق آلية دعم الإشراف المعتمدة من النقابة. يشمل التحقق من عقود الإشراف السارية والحصول على موافقة المدير المالي قبل التحويل.',
            'en' => 'Disbursement of supervision support due to engineering offices under the JEA-approved supervision support scheme. Includes verification of active supervision contracts and CFO approval before transfer.',
        ],
        'FIN-003' => [
            'ar' => 'إعادة تأمينات المهندس المقيم بعد انتهاء عقد الإشراف أو إنهاء الخدمة. يقدم المكتب طلب الاسترداد مع الوثائق المؤيدة، ويتم التحقق من إنجاز الالتزامات قبل صرف المبلغ.',
            'en' => 'Refund of resident engineer securities after the supervision contract ends or service is terminated. The office submits the refund request with supporting documents, and obligations are verified before payment.',
        ],
        'FIN-004' => [
            'ar' => 'إعادة تأمينات عقود استطلاع الموقع المؤجلة التي لم تكتمل ضمن المدة المحددة. يقدم المكتب طلب الاسترداد مع بيان سبب التأجيل والوثائق الرسمية، وتخضع لمراجعة قانونية قبل الصرف.',
            'en' => 'Refund of security deposits on deferred site-survey contracts that were not completed within the prescribed period. The office submits the refund request with a deferral statement and official documents, subject to legal review before payment.',
        ],
        'FIN-005' => [
            'ar' => 'إعادة تأمينات مشاريع تدعيم الحفريات بعد إنجاز الأعمال وتسلمها من الجهة الرسمية. يتم التحقق من تقارير الإنجاز واستلام الموقع قبل صرف قيمة التأمين.',
            'en' => 'Refund of security deposits on excavation-support projects after works are completed and formally accepted by the responsible authority. Completion reports and site handover are verified before the deposit is released.',
        ],
        'FIN-006' => [
            'ar' => 'إعادة تأمينات المخططات الهندسية المؤجلة التي لم تُنفَّذ. يقدم المكتب طلب الاسترداد مع الوثائق الرسمية التي توضح سبب التأجيل وموافقة المالك على الاسترداد.',
            'en' => 'Refund of security deposits on deferred engineering drawings that were not executed. The office submits the refund request with official documents explaining the deferral reason and the owner\'s consent to the refund.',
        ],

        // ── JEA-CERT Certificates — 6 services ────────────────────────
        'CERT-001' => [
            'ar' => 'شهادة رسمية تؤكد مطابقة المشروع المنفذ للمخططات المعتمدة والاشتراطات الفنية. تصدر بعد الكشف الميداني على المبنى والتحقق من إنجاز الأعمال وفق المخططات الأصلية.',
            'en' => 'Official certificate confirming the executed project conforms to the approved drawings and technical requirements. Issued after a site inspection of the building and verification that the works were completed as originally drawn.',
        ],
        'CERT-002' => [
            'ar' => 'شهادة سلامة المنشأ الإنشائية المعتمدة من النقابة. تصدر بعد تقييم فني يشمل الفحص الميداني، مراجعة التقارير الإنشائية، والتحقق من عدم وجود تشققات أو ضعف في العناصر الحاملة.',
            'en' => 'JEA-approved structural safety certificate. Issued after technical assessment including site inspection, review of structural reports, and verification that no cracks or weakness exist in load-bearing elements.',
        ],
        'CERT-003' => [
            'ar' => 'شهادة تصنيف المكتب الهندسي حسب الفئات المعتمدة من هيئة المكاتب. تشمل مراجعة كادر المهندسين، السجل المهني، وحجم المشاريع المنجزة خلال السنوات الأخيرة.',
            'en' => 'Engineering office classification certificate according to the categories approved by the Offices Board. Covers a review of the engineering staff, professional record, and volume of projects completed in recent years.',
        ],
        'CERT-004' => [
            'ar' => 'شهادة ملكية المكتب الهندسي توثق ملكية الشركة أو الشراكة بين المهندسين المؤسسين. تصدر بعد التحقق من السجل التجاري وموافقة الشركاء وفق قوانين النقابة.',
            'en' => 'Engineering office ownership certificate documenting the company or partnership among founding engineers. Issued after verification of the commercial register and partners\' consent under JEA rules.',
        ],
        'CERT-005' => [
            'ar' => 'شهادة اختصاصات المكتب توضح التخصصات الهندسية التي يحق للمكتب ممارستها (معماري، إنشائي، ميكانيكي، كهربائي، بيئي). تعتمد بناءً على مؤهلات المهندسين المسجلين لدى المكتب.',
            'en' => 'Office specialisations certificate stating the engineering disciplines the office is authorised to practise (architectural, structural, mechanical, electrical, environmental). Issued based on the qualifications of the office\'s registered engineers.',
        ],
        'CERT-006' => [
            'ar' => 'إصدار باقي الشهادات الرسمية المتنوعة التي تحتاجها المكاتب أو المهندسون لأغراض قانونية أو رسمية داخل الأردن أو خارجه. تُعامل كل شهادة حسب متطلباتها الخاصة.',
            'en' => 'Issuance of the remaining varied official certificates that offices or engineers need for legal or official purposes inside or outside Jordan. Each certificate is handled according to its specific requirements.',
        ],

        // ── JEA-ENG Engineers — 2 services ────────────────────────────
        'ENG-001' => [
            'ar' => 'تعيين مهندس جديد في كادر المكتب الهندسي أو تحويله من مكتب آخر أو إقالته وإنهاء ارتباطه بالمكتب. تشمل الإجراءات توثيق العقد لدى النقابة، تحديث كوته المكتب، والتأمينات ذات الصلة.',
            'en' => 'Assigning a new engineer to the office staff, transferring one from another office, or terminating an engineer\'s association with the office. Includes JEA contract documentation, office quota update, and related securities handling.',
        ],
        'ENG-002' => [
            'ar' => 'تعيين مهندس على كادر مشروع محدد (بدلاً من كادر المكتب العام) أو تحويله أو إقالته من الكادر. يستخدم عادةً للمشاريع الكبرى أو مشاريع الطاقة التي تتطلب فريق مخصص.',
            'en' => 'Assigning, transferring, or terminating an engineer on a specific project team (rather than the general office staff). Typically used for large-scale or energy projects that require a dedicated team.',
        ],

        // ── JEA-DEC Board Decisions — 4 services ──────────────────────
        'DEC-001' => [
            'ar' => 'تقديم طلبات المكاتب الهندسية إلى هيئة المكاتب للاطلاع أو للحصول على قرار حول مسألة إدارية أو مهنية. يقدم المكتب الطلب مع الوثائق المؤيدة، ويتم إحالته إلى الجلسة القادمة للهيئة.',
            'en' => 'Submission of engineering-office requests to the Offices Board for review or for a decision on an administrative or professional matter. The office submits the request with supporting documents; it is scheduled for the Board\'s next session.',
        ],
        'DEC-002' => [
            'ar' => 'تقديم شكاوى المواطنين ضد مكاتب هندسية أو مهندسين. تشمل مراجعة الشكوى من الأمانة العامة قبل الإحالة إلى الهيئة، مع إمكانية استدعاء الأطراف لجلسة استماع.',
            'en' => 'Filing citizen complaints against engineering offices or engineers. Includes review of the complaint by the General Secretariat before referral to the Board, with the option of summoning the parties for a hearing.',
        ],
        'DEC-003' => [
            'ar' => 'تقديم طلبات الدوائر الحكومية للحصول على رأي فني أو قرار من هيئة المكاتب حول قضايا هندسية ذات علاقة بالمصلحة العامة أو المشاريع الحكومية.',
            'en' => 'Submission of requests from government departments to obtain a technical opinion or Board decision on engineering matters related to the public interest or government projects.',
        ],
        'DEC-004' => [
            'ar' => 'تقديم شكاوى المهندسين ضد مكاتب أخرى أو ضد قرارات إدارية اتخذت بحقهم. تخضع الشكاوى لمراجعة قانونية قبل الإحالة إلى الهيئة، مع حق تقديم الاعتراض على القرار الصادر.',
            'en' => 'Filing engineers\' complaints against other offices or against administrative decisions taken concerning them. Complaints undergo legal review before referral to the Board, with the right to appeal the resulting decision.',
        ],

        // ── JEA-MISC Miscellaneous — 14 services ──────────────────────
        'MSC-001' => [
            'ar' => 'الاستعلام عن كوته المكتب من المشاريع المسموحة سنوياً وطباعة الكشف الرسمي. يعرض الرصيد المستهلك والرصيد المتبقي بحسب فئة المكتب وتصنيفه.',
            'en' => 'Query the office\'s quota of permitted projects per year and print the official report. Displays consumed and remaining balance by office category and classification.',
        ],
        'MSC-002' => [
            'ar' => 'الاستعلام عن جميع مشاريع المكتب النشطة والمكتملة عبر واجهة موحدة. يشمل التصفية بحسب النوع، السنة، الحالة، والقيمة، مع إمكانية تصدير النتائج.',
            'en' => 'Query all of the office\'s active and completed projects through a unified interface. Includes filtering by type, year, status, and value, with the option to export the results.',
        ],
        'MSC-003' => [
            'ar' => 'الاستعلام عن كادر المكتب من المهندسين المسجلين لدى النقابة. يعرض الأسماء والتخصصات ورقم العضوية وتاريخ الانتساب لكل مهندس، مع إمكانية طباعة الكشف الرسمي.',
            'en' => 'Query the office staff of engineers registered at the JEA. Displays names, specialisations, membership number, and enrolment date for each engineer, with the option to print the official report.',
        ],
        'MSC-004' => [
            'ar' => 'تحديث البيانات الأساسية للمكتب الهندسي (العنوان، رقم الهاتف، البريد الإلكتروني، الشعار). تخضع بعض التحديثات للمراجعة قبل الاعتماد النهائي.',
            'en' => 'Update the engineering office\'s core data (address, phone, email, logo). Some updates go through review before final approval.',
        ],
        'MSC-005' => [
            'ar' => 'تقديم طلب الإعفاء الهندسي للمشاريع الصغيرة التي تقل عن حد معين. لا تحتاج إلى تصديق نقابي كامل، وتصدر شهادة إعفاء رسمية بدلاً من المخطط المصدق.',
            'en' => 'Filing an engineering exemption request for small projects below a threshold. These do not require full JEA certification; an official exemption certificate is issued in place of a certified drawing.',
        ],
        'MSC-006' => [
            'ar' => 'إصدار كشف رسمي بضريبة الدخل والمبيعات المستحقة على المكتب أو المهندس. يتم إعداده بالتنسيق مع دائرة ضريبة الدخل بناءً على السجلات المالية الرسمية.',
            'en' => 'Issuance of an official report of income and sales tax due on the office or engineer. Prepared in coordination with the Income Tax Department based on official financial records.',
        ],
        'MSC-007' => [
            'ar' => 'نقل عقد الإشراف على مشروع من مكتب إلى مكتب آخر (بموافقة الأطراف وصاحب المشروع). يتم التحقق من التزامات المكتب الحالي قبل الإنهاء، وتوثيق العقد الجديد لدى النقابة.',
            'en' => 'Transfer of a project\'s supervision contract from one office to another (with all parties\' and the project owner\'s consent). The current office\'s obligations are cleared before closure, and the new contract is documented at the JEA.',
        ],
        'MSC-008' => [
            'ar' => 'تمديد مدة عقد الإشراف على مشروع لم يكتمل ضمن المدة الأصلية. يقدم المكتب طلب التمديد مع تبرير سبب التأخير والجدول الزمني الجديد المتوقع للإنجاز.',
            'en' => 'Extension of a project\'s supervision contract period when the works were not completed within the original timeline. The office submits the extension request with a justification and the new expected completion schedule.',
        ],
        'MSC-009' => [
            'ar' => 'تأجيل تنفيذ مشروع أو إلغاؤه بشكل نهائي. تختلف الإجراءات حسب مرحلة المشروع الحالية — الطلبات المؤجلة تحتفظ بحق العودة لاحقاً، بينما الإلغاء يستدعي إعادة التأمينات.',
            'en' => 'Postpone project execution or cancel it entirely. Procedures differ by project stage — postponed requests retain the right to resume later, while cancellation triggers securities refund.',
        ],
        'MSC-010' => [
            'ar' => 'إصدار مخالصة رسمية للمكتب أو للمهندس بعد إثبات عدم وجود التزامات مالية أو مهنية معلقة لدى النقابة. تستخدم عادةً لأغراض السفر، تجديد الترخيص، أو انتقال العضوية.',
            'en' => 'Issuance of an official clearance to the office or engineer after confirming no outstanding financial or professional obligations at the JEA. Typically used for travel, licence renewal, or membership transfer.',
        ],
        'MSC-011' => [
            'ar' => 'حجز موعد لمقابلة رئيس أحد الاختصاصات في النقابة (معماري، إنشائي، ميكانيكي، كهربائي، بيئي) لطرح مسألة فنية أو مهنية. تشمل واجهة الحجز الأوقات المتاحة والتأكيد الإلكتروني.',
            'en' => 'Book an appointment with the head of one of the JEA disciplines (architectural, structural, mechanical, electrical, environmental) to raise a technical or professional matter. Booking interface includes available slots and electronic confirmation.',
        ],
        'MSC-012' => [
            'ar' => 'إجراء الكشوفات الهندسية الميدانية التي تشمل الفحص الفني للمواقع أو المباني القائمة. يشمل إصدار تقرير فني رسمي بنتائج الكشف وتوصيات المدقق.',
            'en' => 'Conducting engineering field inspections covering technical assessment of sites or existing buildings. Includes issuance of an official technical report with the inspection findings and auditor recommendations.',
        ],
        'MSC-013' => [
            'ar' => 'مكتبة الكتب والنماذج والوثائق الرسمية المتاحة للمكاتب لتحميلها واستخدامها في عملياتها اليومية — تشمل نماذج العقود، المذكرات، ونماذج الطلبات المعتمدة.',
            'en' => 'Library of books, templates, and official documents available for offices to download and use in their daily operations — includes contract templates, memos, and approved request forms.',
        ],
        'MSC-014' => [
            'ar' => 'منصة التوظيف الرسمية للنقابة تربط بين المكاتب الباحثة عن مهندسين والمهندسين الباحثين عن فرص عمل. تشمل نشر الوظائف، تلقي الطلبات، وواجهة تواصل بين الطرفين.',
            'en' => 'The JEA\'s official recruitment platform connecting offices seeking engineers with engineers seeking opportunities. Includes job postings, application intake, and a communications interface between the two parties.',
        ],
    ];

    /**
     * @param  string  $code
     * @param  string  $nameAr
     * @param  string  $nameEn
     * @return array<string, mixed>
     */
    private function placeholderSchema(string $code, string $nameAr, string $nameEn): array
    {
        return [
            'service_code' => $code,
            'name_ar'      => $nameAr,
            'name_en'      => $nameEn,
            'version'      => '0.1-plan-2026',
            'workflow'     => [
                'stages' => [[
                    'id'        => 'placeholder_review',
                    'label_ar'  => 'مراجعة أولية',
                    'label_en'  => 'Placeholder Review',
                    'role'      => 'staff',
                    'sla_hours' => 24,
                    'actions'   => ['approve', 'reject'],
                ]],
            ],
            'fee'         => ['type' => 'fixed', 'amount' => 0, 'currency' => 'JOD'],
            'sections'    => [],
            'fields'      => [],
            'documents'   => [],
            'certificate' => [
                'validity_months' => 0,
                'title_ar'        => $nameAr,
                'title_en'        => $nameEn,
                'fields_on_cert'  => [],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function services(): array
    {
        return [
            // ── 1. خدمات تصديق المخططات الهندسية (JEA-PROJ) — 12 services ──
            ['code' => 'DRW-P-001', 'parent_code' => 'JEA-PROJ', 'phase' => 1, 'name_ar' => 'مخططات الأبنية المقترحة',                   'name_en' => 'Proposed Building Drawings'],
            ['code' => 'DRW-P-002', 'parent_code' => 'JEA-PROJ', 'phase' => 1, 'name_ar' => 'مخططات الأبنية القائمة',                    'name_en' => 'Existing Building Drawings'],
            ['code' => 'DRW-P-003', 'parent_code' => 'JEA-PROJ', 'phase' => 1, 'name_ar' => 'مخططات الأبنية المقترحة فوق الأبنية القائمة', 'name_en' => 'Additions Over Existing Buildings'],
            ['code' => 'DRW-P-004', 'parent_code' => 'JEA-PROJ', 'phase' => 2, 'name_ar' => 'مخططات الهدم',                                'name_en' => 'Demolition Drawings'],
            ['code' => 'DRW-P-005', 'parent_code' => 'JEA-PROJ', 'phase' => 2, 'name_ar' => 'مخططات المشاريع الكبرى',                     'name_en' => 'Large Projects Drawings'],
            ['code' => 'DRW-P-006', 'parent_code' => 'JEA-PROJ', 'phase' => 2, 'name_ar' => 'مخططات مشاريع الطاقة',                       'name_en' => 'Energy Projects Drawings'],
            ['code' => 'DRW-P-007', 'parent_code' => 'JEA-PROJ', 'phase' => 2, 'name_ar' => 'مخططات رخص المهن',                            'name_en' => 'Professional License Drawings'],
            ['code' => 'DRW-P-008', 'parent_code' => 'JEA-PROJ', 'phase' => 2, 'name_ar' => 'المخططات التعديلية',                          'name_en' => 'Modification Drawings'],
            ['code' => 'DRW-P-009', 'parent_code' => 'JEA-PROJ', 'phase' => 2, 'name_ar' => 'مخططات إعادة التأهيل / الصيانة',              'name_en' => 'Rehabilitation / Maintenance Drawings'],
            ['code' => 'DRW-P-010', 'parent_code' => 'JEA-PROJ', 'phase' => 2, 'name_ar' => 'مخططات تعديلية / مجلس البناء الوطني',         'name_en' => 'National Building Council Modifications'],
            ['code' => 'DRW-P-011', 'parent_code' => 'JEA-PROJ', 'phase' => 1, 'name_ar' => 'إعادة التصديق',                                'name_en' => 'Re-approval'],
            ['code' => 'DRW-P-012', 'parent_code' => 'JEA-PROJ', 'phase' => 1, 'name_ar' => 'مخططات الدفاع المدني',                        'name_en' => 'Civil Defence Drawings'],

            // ── 2. استطلاع الموقع (JEA-SURV) — 14 services in 3 subcategories ──
            // 2a. استطلاع الموقع (Site Survey proper) — 10
            ['code' => 'SRV-001', 'parent_code' => 'JEA-SURV', 'subcategory_ar' => 'استطلاع الموقع', 'subcategory_en' => 'Site Survey',      'phase' => 1, 'name_ar' => 'تقارير استطلاع الموقع للأبنية المقترحة',      'name_en' => 'Site Survey — Proposed Buildings'],
            ['code' => 'SRV-002', 'parent_code' => 'JEA-SURV', 'subcategory_ar' => 'استطلاع الموقع', 'subcategory_en' => 'Site Survey',      'phase' => 1, 'name_ar' => 'تقارير استطلاع الموقع للأبنية القائمة',       'name_en' => 'Site Survey — Existing Buildings'],
            ['code' => 'SRV-003', 'parent_code' => 'JEA-SURV', 'subcategory_ar' => 'استطلاع الموقع', 'subcategory_en' => 'Site Survey',      'phase' => 1, 'name_ar' => 'تقارير استطلاع الموقع المؤجلة',               'name_en' => 'Deferred Site Survey'],
            ['code' => 'SRV-004', 'parent_code' => 'JEA-SURV', 'subcategory_ar' => 'استطلاع الموقع', 'subcategory_en' => 'Site Survey',      'phase' => 1, 'name_ar' => 'تقارير استطلاع الموقع للمشاريع الكبرى',        'name_en' => 'Site Survey — Large Projects'],
            ['code' => 'SRV-005', 'parent_code' => 'JEA-SURV', 'subcategory_ar' => 'استطلاع الموقع', 'subcategory_en' => 'Site Survey',      'phase' => 1, 'name_ar' => 'تقارير استطلاع الموقع لمشاريع الطاقة',         'name_en' => 'Site Survey — Energy Projects'],
            ['code' => 'SRV-006', 'parent_code' => 'JEA-SURV', 'subcategory_ar' => 'استطلاع الموقع', 'subcategory_en' => 'Site Survey',      'phase' => 1, 'name_ar' => 'تقارير استطلاع الموقع للمشاريع الحكومية',      'name_en' => 'Site Survey — Government Projects'],
            ['code' => 'SRV-010', 'parent_code' => 'JEA-SURV', 'subcategory_ar' => 'استطلاع الموقع', 'subcategory_en' => 'Site Survey',      'phase' => 1, 'name_ar' => 'إعادة تصديق تقارير استطلاع الموقع مع إضافات',    'name_en' => 'Survey Re-approval — With Additions'],
            ['code' => 'SRV-011', 'parent_code' => 'JEA-SURV', 'subcategory_ar' => 'استطلاع الموقع', 'subcategory_en' => 'Site Survey',      'phase' => 1, 'name_ar' => 'إعادة تصديق تقارير استطلاع الموقع بدون إضافات',  'name_en' => 'Survey Re-approval — Without Additions'],
            ['code' => 'SRV-013', 'parent_code' => 'JEA-SURV', 'subcategory_ar' => 'استطلاع الموقع', 'subcategory_en' => 'Site Survey',      'phase' => 2, 'name_ar' => 'بدل فاقد',                                        'name_en' => 'Replacement Copy'],
            ['code' => 'SRV-014', 'parent_code' => 'JEA-SURV', 'subcategory_ar' => 'استطلاع الموقع', 'subcategory_en' => 'Site Survey',      'phase' => 1, 'name_ar' => 'شهادة الكشف الحسي والكتب الرسمية',               'name_en' => 'Visual Inspection Certificate & Official Letters'],
            // 2b. فحص المواد للأبنية (Material Testing) — 2
            ['code' => 'SRV-008', 'parent_code' => 'JEA-SURV', 'subcategory_ar' => 'فحص المواد للأبنية', 'subcategory_en' => 'Material Testing', 'phase' => 1, 'name_ar' => 'تقارير فحص المواد للأبنية المقترحة',                       'name_en' => 'Material Testing — Proposed Buildings'],
            ['code' => 'SRV-009', 'parent_code' => 'JEA-SURV', 'subcategory_ar' => 'فحص المواد للأبنية', 'subcategory_en' => 'Material Testing', 'phase' => 1, 'name_ar' => 'تقارير فحص مواد للأبنية القائمة / الدراسة الإنشائية',        'name_en' => 'Material Testing — Existing / Structural Study'],
            // 2c. الحفريات (Excavations) — 2
            ['code' => 'SRV-007', 'parent_code' => 'JEA-SURV', 'subcategory_ar' => 'الحفريات', 'subcategory_en' => 'Excavations', 'phase' => 1, 'name_ar' => 'تقارير تدعيم الحفريات — تصميم وإشراف',                     'name_en' => 'Excavation Support — Design & Supervision'],
            ['code' => 'SRV-012', 'parent_code' => 'JEA-SURV', 'subcategory_ar' => 'الحفريات', 'subcategory_en' => 'Excavations', 'phase' => 1, 'name_ar' => 'تقارير الحفريات — تصميم وإشراف',                           'name_en' => 'Excavation Reports — Design & Supervision'],

            // ── 3. الخدمات المالية (JEA-FIN) — 6 services ──
            ['code' => 'FIN-001', 'parent_code' => 'JEA-FIN', 'phase' => 3, 'name_ar' => 'صرف رواتب المهندسين المقيمين / آلية دعم الإشراف', 'name_en' => 'Resident Engineers Salary / Supervision Support'],
            ['code' => 'FIN-002', 'parent_code' => 'JEA-FIN', 'phase' => 3, 'name_ar' => 'صرف دعم الإشراف للمكاتب / آلية دعم الإشراف',      'name_en' => 'Office Supervision Support Payment'],
            ['code' => 'FIN-003', 'parent_code' => 'JEA-FIN', 'phase' => 3, 'name_ar' => 'إعادة تأمينات المهندس المقيم',                     'name_en' => 'Resident Engineer Insurance Refund'],
            ['code' => 'FIN-004', 'parent_code' => 'JEA-FIN', 'phase' => 3, 'name_ar' => 'إعادة تأمينات عقود استطلاع الموقع المؤجلة',        'name_en' => 'Deferred Survey Contract Refund'],
            ['code' => 'FIN-005', 'parent_code' => 'JEA-FIN', 'phase' => 3, 'name_ar' => 'إعادة تأمينات تدعيم الحفريات',                     'name_en' => 'Excavation Support Refund'],
            ['code' => 'FIN-006', 'parent_code' => 'JEA-FIN', 'phase' => 3, 'name_ar' => 'إعادة تأمينات المخططات الهندسية المؤجلة',          'name_en' => 'Deferred Engineering Drawings Refund'],

            // ── 4. الشهادات (JEA-CERT) — 6 services ──
            ['code' => 'CERT-001', 'parent_code' => 'JEA-CERT', 'phase' => 1, 'name_ar' => 'شهادة المطابقة',            'name_en' => 'Conformity Certificate'],
            ['code' => 'CERT-002', 'parent_code' => 'JEA-CERT', 'phase' => 1, 'name_ar' => 'سلامة المنشأ',              'name_en' => 'Structural Safety Certificate'],
            ['code' => 'CERT-003', 'parent_code' => 'JEA-CERT', 'phase' => 3, 'name_ar' => 'شهادة تصنيف مكتب',           'name_en' => 'Office Classification Certificate'],
            ['code' => 'CERT-004', 'parent_code' => 'JEA-CERT', 'phase' => 3, 'name_ar' => 'شهادة ملكية مكتب',           'name_en' => 'Office Ownership Certificate'],
            ['code' => 'CERT-005', 'parent_code' => 'JEA-CERT', 'phase' => 3, 'name_ar' => 'شهادة اختصاصات مكتب',        'name_en' => 'Office Specialisations Certificate'],
            ['code' => 'CERT-006', 'parent_code' => 'JEA-CERT', 'phase' => 3, 'name_ar' => 'باقي الشهادات الرسمية',      'name_en' => 'Other Official Certificates'],

            // ── 5. المهندسون في المكاتب (JEA-ENG) — 2 services ──
            ['code' => 'ENG-001', 'parent_code' => 'JEA-ENG', 'phase' => 5, 'name_ar' => 'تعيين / تحويل / إقالة مهندس (كادر المكتب)',  'name_en' => 'Assign / Transfer / Terminate Engineer (Office Staff)'],
            ['code' => 'ENG-002', 'parent_code' => 'JEA-ENG', 'phase' => 5, 'name_ar' => 'تعيين / تحويل / إقالة مهندس (كادر المشروع)', 'name_en' => 'Assign / Transfer / Terminate Engineer (Project Staff)'],

            // ── 6. قرارات هيئة المكاتب (JEA-DEC) — 4 services ──
            ['code' => 'DEC-001', 'parent_code' => 'JEA-DEC', 'phase' => 4, 'name_ar' => 'تقديم طلبات المكاتب للهيئة',       'name_en' => 'Submit Office Requests to Board'],
            ['code' => 'DEC-002', 'parent_code' => 'JEA-DEC', 'phase' => 4, 'name_ar' => 'تقديم شكاوى المواطنين للهيئة',      'name_en' => 'Submit Citizen Complaints to Board'],
            ['code' => 'DEC-003', 'parent_code' => 'JEA-DEC', 'phase' => 4, 'name_ar' => 'تقديم طلبات الدوائر الحكومية',      'name_en' => 'Submit Government Department Requests'],
            ['code' => 'DEC-004', 'parent_code' => 'JEA-DEC', 'phase' => 4, 'name_ar' => 'تقديم شكاوى المهندسين',              'name_en' => 'Submit Engineers Complaints'],

            // ── 7. خدمات أخرى (JEA-MISC) — 14 services ──
            ['code' => 'MSC-001', 'parent_code' => 'JEA-MISC', 'phase' => 3, 'name_ar' => 'استعلام وطباعة كشف كوته المكتب', 'name_en' => 'Office Quota Report — Query & Print'],
            ['code' => 'MSC-002', 'parent_code' => 'JEA-MISC', 'phase' => 5, 'name_ar' => 'الاستعلام عن مشاريع المكتب',       'name_en' => 'Office Projects Inquiry'],
            ['code' => 'MSC-003', 'parent_code' => 'JEA-MISC', 'phase' => 5, 'name_ar' => 'الاستعلام عن كادر المكتب',         'name_en' => 'Office Staff Inquiry'],
            ['code' => 'MSC-004', 'parent_code' => 'JEA-MISC', 'phase' => 5, 'name_ar' => 'تحديث بيانات المكاتب الهندسية',    'name_en' => 'Update Office Data'],
            ['code' => 'MSC-005', 'parent_code' => 'JEA-MISC', 'phase' => 2, 'name_ar' => 'طلبات الإعفاء الهندسي للمهندسين',   'name_en' => 'Engineering Exemption Requests'],
            ['code' => 'MSC-006', 'parent_code' => 'JEA-MISC', 'phase' => 3, 'name_ar' => 'كشف ضريبة الدخل والمبيعات',        'name_en' => 'Income & Sales Tax Report'],
            ['code' => 'MSC-007', 'parent_code' => 'JEA-MISC', 'phase' => 2, 'name_ar' => 'نقل عقد الإشراف',                   'name_en' => 'Transfer Supervision Contract'],
            ['code' => 'MSC-008', 'parent_code' => 'JEA-MISC', 'phase' => 2, 'name_ar' => 'تمديد عقد الإشراف',                 'name_en' => 'Extend Supervision Contract'],
            ['code' => 'MSC-009', 'parent_code' => 'JEA-MISC', 'phase' => 2, 'name_ar' => 'تأجيل / إلغاء مشروع',              'name_en' => 'Postpone / Cancel Project'],
            ['code' => 'MSC-010', 'parent_code' => 'JEA-MISC', 'phase' => 2, 'name_ar' => 'مخالصات المكاتب والمهندسين',        'name_en' => 'Office & Engineer Clearance'],
            ['code' => 'MSC-011', 'parent_code' => 'JEA-MISC', 'phase' => 5, 'name_ar' => 'مقابلة رؤساء الاختصاص',             'name_en' => 'Meeting with Discipline Heads'],
            ['code' => 'MSC-012', 'parent_code' => 'JEA-MISC', 'phase' => 5, 'name_ar' => 'الكشوفات الهندسية',                 'name_en' => 'Engineering Inspections'],
            // MSC-013 "الكتب والنماذج المتاحة للمكاتب" dropped as a vague
            // catch-all — the documents library isn't a workflow.
            ['code' => 'MSC-014', 'parent_code' => 'JEA-MISC', 'phase' => 5, 'name_ar' => 'منصة التوظيف',                       'name_en' => 'Recruitment Platform'],
        ];
    }
}
