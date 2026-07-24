<?php

declare(strict_types=1);

namespace Modules\JeaServices\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\JeaServices\Models\ManualReference;

/**
 * ManualReferencesSeeder — الأساس النظامي.
 *
 * يعبّئ جدول manual_references من ملخّص docs/manual-summary.md.
 * كل صف = قاعدة واحدة من كتاب التعليمات الفنية 2025 مربوطة
 * بالـ JORD ticket المُنفَّذ لها + المكان في النظام (خدمة/حقل/قاعدة).
 *
 * Idempotent: يستخدم updateOrCreate على المفتاح (jord_ticket, target_type, target_id, target_service_code)
 * فإعادة التشغيل تُحدِّث النصوص لكن لا تكرِّر الصفوف.
 *
 * ⚠️ عند إعادة التشغيل — لا يمحو التعديلات الإدارية (needs_reimplementation)
 * ما لم يكن النص الأصلي المخزَّن (text_ar_original) قد تغيَّر في هذا الملف.
 */
class ManualReferencesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->references() as $ref) {
            $this->upsert($ref);
        }
    }

    /**
     * @param  array<string, mixed>  $ref
     */
    private function upsert(array $ref): void
    {
        $key = [
            'jord_ticket'         => $ref['jord_ticket'],
            'target_type'         => $ref['target_type'],
            'target_id'           => $ref['target_id'] ?? null,
            'target_service_code' => $ref['target_service_code'] ?? null,
        ];

        $existing = ManualReference::where($key)->first();

        if ($existing && $existing->needs_reimplementation) {
            // Admin edited the text and dev hasn't acknowledged.
            // Do NOT overwrite — keep the edit visible until acknowledge.
            return;
        }

        $data = array_merge($ref, [
            'text_ar_original' => $ref['text_ar'], // baseline on first seed
        ]);

        ManualReference::updateOrCreate($key, $data);
    }

    /**
     * The initial reference set — mirrors docs/manual-summary.md.
     * Adding a rule here + updating the summary md is the workflow
     * for extending coverage. On production, this seeder runs once
     * post-migrate to populate the initial set.
     *
     * @return list<array<string, mixed>>
     */
    private function references(): array
    {
        return [
            // ─── المخططات وصلاحيتها ──────────────────────────────
            [
                'jord_ticket'         => 'JORD-58',
                'chapter'             => 'الباب الأول — الفصل الثالث',
                'section'             => 'صلاحية المخططات المعتمدة',
                'page'                => 26,
                'rule_type'           => 'deadline',
                'target_type'         => 'rule',
                'target_id'           => 'drawing_validity_months',
                'target_service_code' => null,
                'text_ar'             => 'تكون مدة صلاحية المخططات خمس سنوات من تاريخ اجازتها من النقابة، واذا رغب المالك خلال هذه المدة اعادة تصديق المخططات من قبل مكتب آخر مهما كانت الاسباب فعليه إحضار عقد تصميم بنصف الأتعاب. (كتاب التعليمات الفنية 2025 — الفصل الثالث ص.26)',
            ],
            [
                'jord_ticket'         => 'JORD-54',
                'chapter'             => 'الباب الأول — الفصل الأول',
                'section'             => 'قائمة المستندات المطلوبة',
                'page'                => 9,
                'rule_type'           => 'document',
                'target_type'         => 'service',
                'target_id'           => null,
                'target_service_code' => 'DRW-P-001', // shared with DRW-P-002..010 — see companion rows below
                'text_ar'             => 'يجب على المكتب الهندسي تقديم قائمة (15) مستنداً تشمل: المخططات المعمارية والإنشائية والكهروميكانيكية، عقود التصميم والإشراف، سند الملكية، مخطط الموقع، ملاحظات الحساب، تقرير استطلاع الموقع، ودراسة الأمان للأبنية القائمة. (كتاب التعليمات الفنية 2025 — الفصل الأول ص.9-16)',
            ],

            // ─── عقود الإشراف ─────────────────────────────────────
            [
                'jord_ticket'         => 'JORD-59',
                'chapter'             => 'الباب الأول — الفصل الثاني',
                'section'             => 'عقد الإشراف — المدة الإلزامية',
                'page'                => 27,
                'rule_type'           => 'deadline',
                'target_type'         => 'rule',
                'target_id'           => 'supervision_window_months',
                'target_service_code' => null,
                'text_ar'             => 'يكون عقد الإشراف ملزماً لكلا الطرفين خلال المدة التعاقدية لعقد الإشراف الواردة في تعليمات النقابة مضافاً اليها ستة أشهر من تاريخ اجازة المخططات من النقابة. (كتاب التعليمات الفنية 2025 — الفصل الثاني ص.27)',
            ],
            [
                'jord_ticket'         => 'JORD-83',
                'chapter'             => 'الباب الأول — الفصل الثاني',
                'section'             => 'إغلاق المكتب / وفاة المهندس المقيم',
                'page'                => 30,
                'rule_type'           => 'workflow',
                'target_type'         => 'rule',
                'target_id'           => 'supervision_transfer',
                'target_service_code' => null,
                'text_ar'             => 'لا يطلب مخالصة من المكتب السابق في حال وجود إضافات أو تعديلات. يتم إعادة شيكات المقيم للمالك إذا كان المشروع منتهياً قبل الإغلاق و/أو الوفاة. (كتاب التعليمات الفنية 2025 — الفصل الثاني ص.30)',
            ],

            // ─── استطلاع الموقع وفحص المواد ───────────────────────
            [
                'jord_ticket'         => 'JORD-60',
                'chapter'             => 'الباب الأول — الفصل الثالث',
                'section'             => 'حفظ عيّنات المواد',
                'page'                => 36,
                'rule_type'           => 'workflow',
                'target_type'         => 'service',
                'target_id'           => null,
                'target_service_code' => 'SRV-008',
                'text_ar'             => 'إشعار حفظ عيّنات المواد لمدة عشرة أيام لتوثيق نتائج فحص المواد لدى المدققين. تدقيق المخططات الخاضعة لكودة الأمان يشمل كاميرات المراقبة التلفازية لدى مدققي الأمن العام المتواجدين في النقابة. (كتاب التعليمات الفنية 2025 — الفصل الثالث ص.36)',
            ],
            [
                'jord_ticket'         => 'JORD-64',
                'chapter'             => 'الباب الأول — الفصل الثالث',
                'section'             => 'عقود فحص المواد وحماية الحفريات',
                'page'                => 40,
                'rule_type'           => 'fee',
                'target_type'         => 'service',
                'target_id'           => null,
                'target_service_code' => 'SRV-007',
                'text_ar'             => 'يطلب تقديم عقد فحص مواد من خلال مكتب فحص مواد مسجل لدى النقابة لجميع المشاريع الإنشائية الخاضعة للإشراف الكلي. أتعاب حماية الحفريات تُحسب per-m². (كتاب التعليمات الفنية 2025 — الفصل الثالث ص.40)',
            ],
            [
                'jord_ticket'         => 'JORD-74',
                'chapter'             => 'الباب الأول — الفصل التاسع',
                'section'             => 'حصص فحص المواد',
                'page'                => 125,
                'rule_type'           => 'quota',
                'target_type'         => 'service',
                'target_id'           => null,
                'target_service_code' => 'SRV-008',
                'text_ar'             => 'الحصص السنوية لمهندس فحص المواد وميكانيكا التربة محدَّدة في جدول الحصص الهندسية للمهندسين. (كتاب التعليمات الفنية 2025 — الفصل التاسع ص.124-125)',
            ],
            [
                'jord_ticket'         => 'JORD-75',
                'chapter'             => 'الباب الأول — الفصل التاسع',
                'section'             => 'حصة المسح للمشاريع الحكومية',
                'page'                => 125,
                'rule_type'           => 'quota',
                'target_type'         => 'service',
                'target_id'           => null,
                'target_service_code' => 'SRV-006',
                'text_ar'             => 'حصة المسح للمشاريع الحكومية تُحسب بالمتر الطولي (length_lm) وفق جدول الحصص. (كتاب التعليمات الفنية 2025 — الفصل التاسع ص.125)',
            ],
            [
                'jord_ticket'         => 'JORD-78',
                'chapter'             => 'الباب الأول — الفصل السابع',
                'section'             => 'أتعاب استطلاع الموقع',
                'page'                => 96,
                'rule_type'           => 'fee',
                'target_type'         => 'service',
                'target_id'           => null,
                'target_service_code' => 'SRV-006',
                'text_ar'             => 'أتعاب استطلاع الموقع 150 فلساً لكل متر طولي، مضافاً إليها 1% رسم نقابة على الإجمالي. (كتاب التعليمات الفنية 2025 — الفصل السابع ص.96)',
            ],

            // ─── الطاقة المتجددة ─────────────────────────────────
            [
                'jord_ticket'         => 'JORD-64-solar',
                'chapter'             => 'الباب الأول — الفصل الخامس',
                'section'             => 'أتعاب الطاقة المتجددة',
                'page'                => 71,
                'rule_type'           => 'fee',
                'target_type'         => 'service',
                'target_id'           => null,
                'target_service_code' => 'DRW-P-006',
                'text_ar'             => 'أتعاب مشاريع الطاقة الشمسية تُحسب per-kW بدلاً من per-m². حضور الدورة التأهيلية الخاصة بتصميم أنظمة الطاقة المتجددة بمركز تدريب النقابة شرط لممارسة هذا الاختصاص. (كتاب التعليمات الفنية 2025 — الفصل الخامس ص.71-72)',
            ],

            // ─── الرسوم والأتعاب ─────────────────────────────────
            [
                'jord_ticket'         => 'JORD-63',
                'chapter'             => 'الباب الأول — الفصل السابع',
                'section'             => 'مصفوفة أتعاب المخططات',
                'page'                => 92,
                'rule_type'           => 'fee',
                'target_type'         => 'service',
                'target_id'           => null,
                'target_service_code' => 'DRW-P-001',
                'text_ar'             => 'أتعاب التصميم/م² تُحسب من مصفوفة متعدِّدة الأبعاد تشمل: المحافظة (أمانة عمّان الكبرى أو غيرها) × صنف البناء × مساحة البناء. الأتعاب تشمل أتعاب متابعة + أتعاب تصميم للمتر المربّع. (كتاب التعليمات الفنية 2025 — الفصل السابع ص.92)',
            ],
            [
                'jord_ticket'         => 'JORD-65',
                'chapter'             => 'الباب الأول — الفصل السابع',
                'section'             => 'رسوم النقابة',
                'page'                => 96,
                'rule_type'           => 'fee',
                'target_type'         => 'rule',
                'target_id'           => 'syndicate_surcharge',
                'target_service_code' => null,
                'text_ar'             => 'تستوفي النقابة (1%) رسم أتعاب من إجمالي الأتعاب الهندسية التي تتقاضاها المكاتب الهندسية الأردنية وغير الأردنية عن ممارسة المهنة، مضافاً إليها 40 فلساً/م² لتدقيق المخططات. (كتاب التعليمات الفنية 2025 — الفصل السابع ص.96)',
            ],

            // ─── الحصص والسقوف ─────────────────────────────────
            [
                'jord_ticket'         => 'JORD-67',
                'chapter'             => 'الباب الأول — الفصل التاسع',
                'section'             => 'الحصص الهندسية السنوية',
                'page'                => 124,
                'rule_type'           => 'quota',
                'target_type'         => 'rule',
                'target_id'           => 'engineer_annual_quota',
                'target_service_code' => null,
                'text_ar'             => 'الحصص الهندسية للمهندسين لسنة كاملة موزَّعة على الاختصاصات: معماري، مدني، كهرباء، ميكانيك، فحص المواد، ميكانيكا التربة. لكل اختصاص سقف م² سنوي محدَّد بالجدول. (كتاب التعليمات الفنية 2025 — الفصل التاسع ص.124)',
            ],
            [
                'jord_ticket'         => 'JORD-70',
                'chapter'             => 'الباب الأول — الفصل التاسع',
                'section'             => 'زيادات الحصص',
                'page'                => 126,
                'rule_type'           => 'quota',
                'target_type'         => 'rule',
                'target_id'           => 'office_quota_boosts',
                'target_service_code' => null,
                'text_ar'             => 'المهندس المسجَّل رئيساً لاختصاص يمنح زيادة من الحصص المستحقة له. يُحتسب للمكتب زيادة 5% من الحصص لكونه بيت خبرة + 5% لحصوله على ISO — تُحتسب من بداية العام. (كتاب التعليمات الفنية 2025 — الفصل التاسع ص.125-126)',
            ],
            [
                'jord_ticket'         => 'JORD-71',
                'chapter'             => 'الباب الأول — الفصل التاسع',
                'section'             => 'تجاوز الحصص + ترحيل السنة',
                'page'                => 127,
                'rule_type'           => 'quota',
                'target_type'         => 'rule',
                'target_id'           => 'governorate_overflow',
                'target_service_code' => null,
                'text_ar'             => 'لا يُسمح بالتجاوز في الحصص الهندسية على أن يتم السماح بالتجاوز في نفس المعاملة للمكاتب ويُرحَّل التجاوز للعام التالي. يُمنح ما نسبته 10% من الحصص للتجاوز على المحافظات. (كتاب التعليمات الفنية 2025 — الفصل التاسع ص.127)',
            ],
            [
                'jord_ticket'         => 'JORD-72',
                'chapter'             => 'الباب الأول — الفصل التاسع',
                'section'             => 'سقف المشروع الواحد',
                'page'                => 129,
                'rule_type'           => 'quota',
                'target_type'         => 'rule',
                'target_id'           => 'per_project_cap',
                'target_service_code' => null,
                'text_ar'             => 'يجوز للمكاتب والشركات الهندسية الاستشارية الكبرى والتي تشكل بيوتاً للخبرة بتجاوز السقوف الهندسية المسموحة لها. حالة التجاوز على مشروع واحد تُعاقَب بضريبة إضافية 25%. (كتاب التعليمات الفنية 2025 — الفصل التاسع ص.129)',
            ],
            [
                'jord_ticket'         => 'JORD-73',
                'chapter'             => 'الباب الأول — الفصل العاشر',
                'section'             => 'الائتلاف بين المكاتب',
                'page'                => 131,
                'rule_type'           => 'workflow',
                'target_type'         => 'rule',
                'target_id'           => 'office_coalition',
                'target_service_code' => null,
                'text_ar'             => 'يُسمح للمكتب المتعاقد التعاون مع المكاتب الأخرى لاستكمال تقديم الخدمات الهندسية لها. يتم تسجيل الائتلاف مع تحديد الاختصاصات وأسماء رؤساء الاختصاص. يُعامَل الائتلاف بالحصص المجموعة للأعضاء مع سقف 1.5× للمشروع الواحد. (كتاب التعليمات الفنية 2025 — الفصل العاشر ص.131-136)',
            ],

            // ─── التأديب والغرامات ─────────────────────────────
            [
                'jord_ticket'         => 'JORD-82',
                'chapter'             => 'الباب الثالث — نظام المكاتب 2016',
                'section'             => 'المادة 14 — المقاول غير المرخّص',
                'page'                => 251,
                'rule_type'           => 'discipline',
                'target_type'         => 'rule',
                'target_id'           => 'legal_fine_art14',
                'target_service_code' => null,
                'text_ar'             => 'إذا لم يتم تنفيذ المشروع من خلال مقاول مرخَّص ومصنَّف لدى وزارة الأشغال العامة والإسكان ومسجَّل لدى نقابة مقاولي الإنشاءات الأردنيين، أو لم يلتزم بالتصميم والإشراف على المشروع، يُغرَّم المالك وفق الجدول المعتمد. (كتاب التعليمات الفنية 2025 — الملحق — نظام المكاتب 2016 المادة 14 — ص.251)',
            ],
            [
                'jord_ticket'         => 'JORD-81',
                'chapter'             => 'الباب الثالث — نظام الرقابة والتفتيش 2020',
                'section'             => 'إجراءات لجان التفتيش على الشكاوى',
                'page'                => 278,
                'rule_type'           => 'discipline',
                'target_type'         => 'rule',
                'target_id'           => 'complaints_inspection',
                'target_service_code' => null,
                'text_ar'             => 'يدفع المشتكي بدل خدمة مقداره (150) ديناراً لكشف اللجان على الشكوى المقدَّمة. تقوم لجان التفتيش الرقابية بإعداد تقرير الزيارة وفق النموذج المعتمد. (كتاب التعليمات الفنية 2025 — الملحق — نظام الرقابة 2020 — ص.278)',
            ],
        ];
    }
}
