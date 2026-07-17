<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * ServicePlan2026Seeder
 *
 * Seeds the full JEA services catalog per service-plan-payment.pdf (2026):
 * 58 services across 7 categories with delivery phase (1..5) set on each.
 *
 * The seeder is idempotent (updateOrCreate on code) and safe to re-run
 * after schema tweaks. It also:
 *   - Adds the new JEA-SURV top-level tile (استطلاع الموقع) that the
 *     initial JeaPortalTilesSeeder didn't include.
 *   - Soft-deletes the legacy DRW-001..DRW-007 placeholders (older
 *     JeaDrawingsSeeder demo data) which are superseded by the plan's
 *     12 drawing services under the same parent.
 *
 * Phase counts should match the plan footer (20/13/12/4/9):
 *   phase 1 = 20, phase 2 = 13, phase 3 = 12, phase 4 = 4, phase 5 = 9.
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

        // 3. Bulk-seed the 58 services.
        foreach ($this->services() as $svc) {
            $this->upsert($org->id, $svc);
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

        ServiceDefinition::updateOrCreate(
            ['organization_id' => $orgId, 'code' => $data['code']],
            $data
        );
    }

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
            ['code' => 'CERT-006', 'parent_code' => 'JEA-CERT', 'phase' => 3, 'name_ar' => 'باقي الشهادات الرسمية',       'name_en' => 'Other Official Certificates'],

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
            ['code' => 'MSC-013', 'parent_code' => 'JEA-MISC', 'phase' => 5, 'name_ar' => 'الكتب والنماذج المتاحة للمكاتب',    'name_en' => 'Office Documents & Templates Library'],
            ['code' => 'MSC-014', 'parent_code' => 'JEA-MISC', 'phase' => 5, 'name_ar' => 'منصة التوظيف',                       'name_en' => 'Recruitment Platform'],
        ];
    }
}
