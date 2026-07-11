<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * JeaServicesSeeder
 *
 * Seeds the Jordan Engineers Association (JEA) organization with
 * all essential services from the JEA engineers services portal.
 *
 * Run:  php artisan db:seed --class=JeaServicesSeeder
 * Reset: php artisan migrate:fresh --seed   (runs all seeders)
 */
class JeaServicesSeeder extends Seeder
{
    public function run(): void
    {
        // ── Organization ─────────────────────────────────────────────────
        $org = Organization::firstOrCreate(
            ['slug' => 'jea'],
            [
                'name_ar'   => 'نقابة المهندسين الأردنيين',
                'name_en'   => 'Jordan Engineers Association',
                'is_active' => true,
            ]
        );

        // ── Users ─────────────────────────────────────────────────────────
        $users = [
            ['name' => 'مدير النظام',         'email' => 'admin@jea.dev',    'role' => 'admin'],
            ['name' => 'موظف استقبال الطلبات', 'email' => 'staff@jea.dev',    'role' => 'staff'],
            ['name' => 'المدقق القانوني',       'email' => 'auditor@jea.dev',  'role' => 'auditor'],
            ['name' => 'م. أحمد الهياصات',     'email' => 'eng@jea.dev',      'role' => 'applicant'],
        ];

        foreach ($users as $u) {
            User::firstOrCreate(
                ['email' => $u['email']],
                [
                    ...$u,
                    'organization_id'    => $org->id,
                    'password'           => Hash::make('Jea1234!'),
                    'password_changed_at' => now(),
                    'is_active'          => true,
                ]
            );
        }

        // ── Services ──────────────────────────────────────────────────────
        foreach ($this->services($org->id) as $svc) {
            ServiceDefinition::firstOrCreate(
                ['code' => $svc['code'], 'organization_id' => $org->id],
                $svc
            );
        }

        $this->command->info('✓ JEA organization: jea');
        $this->command->info('✓ Users: admin@jea.dev / staff@jea.dev / auditor@jea.dev / eng@jea.dev');
        $this->command->info('✓ Password: Jea1234!');
        $this->command->info('✓ Services seeded: ' . count($this->services($org->id)));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Service definitions
    // ─────────────────────────────────────────────────────────────────────

    private function services(int $orgId): array
    {
        return [

            // ── 1. طلب انتساب مهندس جديد ─────────────────────────────
            [
                'organization_id' => $orgId,
                'code'            => 'JEA-MEM-001',
                'name_ar'         => 'طلب انتساب مهندس جديد',
                'name_en'         => 'New Engineer Membership',
                'description_ar'  => 'تسجيل المهندس الجديد والحصول على عضوية نقابة المهندسين الأردنيين',
                'description_en'  => 'Register as a new engineer member of the Jordan Engineers Association',
                'currency'        => 'JOD',
                'status'          => 'active',
                'schema'          => [
                    'service_code' => 'JEA-MEM-001',
                    'name_ar'      => 'طلب انتساب مهندس جديد',
                    'name_en'      => 'New Engineer Membership',
                    'version'      => '1.0',
                    'workflow'     => [
                        'stages' => [
                            ['id' => 'docs_review',  'label_ar' => 'مراجعة الوثائق',    'label_en' => 'Documents Review',  'role' => 'staff',   'sla_hours' => 48, 'actions' => ['approve', 'reject', 'request_modifications']],
                            ['id' => 'fee_payment',  'label_ar' => 'سداد الرسوم',        'label_en' => 'Fee Payment',       'role' => 'staff',   'sla_hours' => 72, 'actions' => ['approve', 'reject']],
                            ['id' => 'final_review', 'label_ar' => 'المراجعة النهائية',  'label_en' => 'Final Approval',    'role' => 'auditor', 'sla_hours' => 24, 'actions' => ['approve', 'reject']],
                        ],
                    ],
                    'fee'      => ['type' => 'fixed', 'amount' => 50, 'currency' => 'JOD'],
                    'sections' => [
                        ['id' => 'personal',     'label_ar' => 'المعلومات الشخصية',     'label_en' => 'Personal Information'],
                        ['id' => 'academic',     'label_ar' => 'المؤهل العلمي',          'label_en' => 'Academic Qualification'],
                        ['id' => 'employment',   'label_ar' => 'جهة العمل',              'label_en' => 'Employment'],
                        ['id' => 'specialization', 'label_ar' => 'التخصص الهندسي',      'label_en' => 'Engineering Specialization'],
                    ],
                    'fields' => [
                        ['id' => 'full_name_ar',      'label_ar' => 'الاسم الكامل بالعربية',   'label_en' => 'Full Name (Arabic)',    'type' => 'text',   'required' => true,  'section' => 'personal'],
                        ['id' => 'full_name_en',      'label_ar' => 'الاسم الكامل بالإنجليزية', 'label_en' => 'Full Name (English)',   'type' => 'text',   'required' => true,  'section' => 'personal'],
                        ['id' => 'national_id',       'label_ar' => 'رقم الهوية الوطنية',       'label_en' => 'National ID Number',    'type' => 'text',   'required' => true,  'section' => 'personal'],
                        ['id' => 'birth_date',        'label_ar' => 'تاريخ الميلاد',            'label_en' => 'Date of Birth',         'type' => 'date',   'required' => true,  'section' => 'personal'],
                        ['id' => 'gender',            'label_ar' => 'الجنس',                    'label_en' => 'Gender',                'type' => 'radio',  'required' => true,  'section' => 'personal', 'options' => [['value' => 'male', 'label_ar' => 'ذكر', 'label_en' => 'Male'], ['value' => 'female', 'label_ar' => 'أنثى', 'label_en' => 'Female']]],
                        ['id' => 'mobile',            'label_ar' => 'رقم الجوال',               'label_en' => 'Mobile Number',         'type' => 'text',   'required' => true,  'section' => 'personal'],
                        ['id' => 'email',             'label_ar' => 'البريد الإلكتروني',        'label_en' => 'Email Address',         'type' => 'email',  'required' => true,  'section' => 'personal'],
                        ['id' => 'specialization',    'label_ar' => 'التخصص الهندسي',           'label_en' => 'Engineering Specialization', 'type' => 'select', 'required' => true, 'section' => 'specialization',
                            'options' => [
                                ['value' => 'civil',          'label_ar' => 'مدني',          'label_en' => 'Civil'],
                                ['value' => 'electrical',     'label_ar' => 'كهربائي',       'label_en' => 'Electrical'],
                                ['value' => 'mechanical',     'label_ar' => 'ميكانيكي',      'label_en' => 'Mechanical'],
                                ['value' => 'architectural',  'label_ar' => 'معماري',        'label_en' => 'Architectural'],
                                ['value' => 'chemical',       'label_ar' => 'كيميائي',       'label_en' => 'Chemical'],
                                ['value' => 'industrial',     'label_ar' => 'صناعي',         'label_en' => 'Industrial'],
                                ['value' => 'communications', 'label_ar' => 'اتصالات',       'label_en' => 'Communications'],
                                ['value' => 'computer',       'label_ar' => 'حاسوب',         'label_en' => 'Computer'],
                                ['value' => 'mining',         'label_ar' => 'تعدين ومناجم',  'label_en' => 'Mining'],
                                ['value' => 'agricultural',   'label_ar' => 'زراعي',         'label_en' => 'Agricultural'],
                            ],
                        ],
                        ['id' => 'university',        'label_ar' => 'الجامعة',                  'label_en' => 'University',            'type' => 'text',   'required' => true,  'section' => 'academic'],
                        ['id' => 'graduation_year',   'label_ar' => 'سنة التخرج',               'label_en' => 'Graduation Year',       'type' => 'number', 'required' => true,  'section' => 'academic'],
                        ['id' => 'degree',            'label_ar' => 'الدرجة العلمية',           'label_en' => 'Degree',                'type' => 'select', 'required' => true,  'section' => 'academic',
                            'options' => [
                                ['value' => 'bachelor', 'label_ar' => 'بكالوريوس', 'label_en' => 'Bachelor'],
                                ['value' => 'master',   'label_ar' => 'ماجستير',   'label_en' => 'Master'],
                                ['value' => 'phd',      'label_ar' => 'دكتوراه',   'label_en' => 'PhD'],
                            ],
                        ],
                        ['id' => 'employer',          'label_ar' => 'جهة العمل الحالية',        'label_en' => 'Current Employer',      'type' => 'text',   'required' => false, 'section' => 'employment'],
                        ['id' => 'job_title',         'label_ar' => 'المسمى الوظيفي',           'label_en' => 'Job Title',             'type' => 'text',   'required' => false, 'section' => 'employment'],
                    ],
                    'documents' => [
                        ['id' => 'national_id_copy',  'label_ar' => 'صورة الهوية الوطنية',     'label_en' => 'National ID Copy',      'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 5],
                        ['id' => 'degree_cert',       'label_ar' => 'صورة شهادة التخرج',       'label_en' => 'Degree Certificate',    'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 5],
                        ['id' => 'personal_photo',    'label_ar' => 'صورة شخصية',              'label_en' => 'Personal Photo',        'required' => true,  'accept' => ['jpg', 'png'],         'max_size_mb' => 2],
                        ['id' => 'equivalency_cert',  'label_ar' => 'شهادة معادلة (للجامعات خارج الأردن)', 'label_en' => 'Equivalency Certificate', 'required' => false, 'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 5,
                            'conditional' => ['field' => 'university', 'value' => '__non_jordan__']],
                    ],
                    'certificate' => [
                        'validity_months' => 0,
                        'title_ar'        => 'بطاقة عضوية نقابة المهندسين الأردنيين',
                        'title_en'        => 'JEA Membership Card',
                        'fields_on_cert'  => ['full_name_ar', 'full_name_en', 'national_id', 'specialization'],
                    ],
                ],
            ],

            // ── 2. تجديد الاشتراك السنوي ──────────────────────────────
            [
                'organization_id' => $orgId,
                'code'            => 'JEA-MEM-002',
                'name_ar'         => 'تجديد الاشتراك السنوي',
                'name_en'         => 'Annual Subscription Renewal',
                'description_ar'  => 'تجديد اشتراك العضوية السنوية في نقابة المهندسين الأردنيين',
                'description_en'  => 'Renew annual membership subscription in the Jordan Engineers Association',
                'currency'        => 'JOD',
                'status'          => 'active',
                'schema'          => [
                    'service_code' => 'JEA-MEM-002',
                    'name_ar'      => 'تجديد الاشتراك السنوي',
                    'name_en'      => 'Annual Subscription Renewal',
                    'version'      => '1.0',
                    'workflow'     => [
                        'stages' => [
                            ['id' => 'payment_verification', 'label_ar' => 'التحقق من الدفع', 'label_en' => 'Payment Verification', 'role' => 'staff', 'sla_hours' => 24, 'actions' => ['approve', 'reject']],
                        ],
                    ],
                    'fee'      => ['type' => 'fixed', 'amount' => 30, 'currency' => 'JOD'],
                    'sections' => [
                        ['id' => 'member_info', 'label_ar' => 'بيانات العضو', 'label_en' => 'Member Information'],
                    ],
                    'fields' => [
                        ['id' => 'membership_number', 'label_ar' => 'رقم العضوية',        'label_en' => 'Membership Number',  'type' => 'text',   'required' => true,  'section' => 'member_info'],
                        ['id' => 'full_name_ar',       'label_ar' => 'الاسم الكامل',        'label_en' => 'Full Name',          'type' => 'text',   'required' => true,  'section' => 'member_info'],
                        ['id' => 'renewal_year',       'label_ar' => 'سنة التجديد',         'label_en' => 'Renewal Year',       'type' => 'number', 'required' => true,  'section' => 'member_info'],
                        ['id' => 'mobile',             'label_ar' => 'رقم الجوال',          'label_en' => 'Mobile Number',      'type' => 'text',   'required' => true,  'section' => 'member_info'],
                        ['id' => 'employer',           'label_ar' => 'جهة العمل الحالية',   'label_en' => 'Current Employer',   'type' => 'text',   'required' => false, 'section' => 'member_info'],
                    ],
                    'documents' => [
                        ['id' => 'payment_receipt', 'label_ar' => 'إيصال الدفع', 'label_en' => 'Payment Receipt', 'required' => false, 'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 3],
                    ],
                    'certificate' => [
                        'validity_months' => 12,
                        'title_ar'        => 'شهادة تجديد اشتراك سنوي',
                        'title_en'        => 'Annual Renewal Certificate',
                        'fields_on_cert'  => ['membership_number', 'full_name_ar', 'renewal_year'],
                    ],
                ],
            ],

            // ── 3. تسجيل مكتب هندسي ──────────────────────────────────
            [
                'organization_id' => $orgId,
                'code'            => 'JEA-OFF-001',
                'name_ar'         => 'تسجيل مكتب هندسي',
                'name_en'         => 'Engineering Office Registration',
                'description_ar'  => 'تسجيل مكتب هندسي استشاري لدى نقابة المهندسين الأردنيين والحصول على الترخيص',
                'description_en'  => 'Register an engineering consulting office with JEA and obtain the license',
                'currency'        => 'JOD',
                'status'          => 'active',
                'schema'          => [
                    'service_code' => 'JEA-OFF-001',
                    'name_ar'      => 'تسجيل مكتب هندسي',
                    'name_en'      => 'Engineering Office Registration',
                    'version'      => '1.0',
                    'workflow'     => [
                        'stages' => [
                            ['id' => 'docs_review',    'label_ar' => 'مراجعة الوثائق',      'label_en' => 'Documents Review',    'role' => 'staff',   'sla_hours' => 48,  'actions' => ['approve', 'reject', 'request_modifications']],
                            ['id' => 'site_inspection','label_ar' => 'فحص الموقع',           'label_en' => 'Site Inspection',     'role' => 'staff',   'sla_hours' => 72,  'actions' => ['approve', 'reject', 'request_modifications']],
                            ['id' => 'final_approval', 'label_ar' => 'الموافقة النهائية',   'label_en' => 'Final Approval',      'role' => 'auditor', 'sla_hours' => 24,  'actions' => ['approve', 'reject']],
                        ],
                    ],
                    'fee'      => ['type' => 'fixed', 'amount' => 150, 'currency' => 'JOD'],
                    'sections' => [
                        ['id' => 'office_info',   'label_ar' => 'بيانات المكتب',         'label_en' => 'Office Information'],
                        ['id' => 'owner_info',    'label_ar' => 'بيانات المالك / الشريك المسؤول', 'label_en' => 'Owner / Responsible Partner'],
                        ['id' => 'activity_info', 'label_ar' => 'نشاط المكتب',           'label_en' => 'Office Activity'],
                    ],
                    'fields' => [
                        ['id' => 'office_name_ar',    'label_ar' => 'اسم المكتب بالعربية',       'label_en' => 'Office Name (Arabic)',    'type' => 'text',   'required' => true,  'section' => 'office_info'],
                        ['id' => 'office_name_en',    'label_ar' => 'اسم المكتب بالإنجليزية',    'label_en' => 'Office Name (English)',   'type' => 'text',   'required' => false, 'section' => 'office_info'],
                        ['id' => 'office_address',    'label_ar' => 'عنوان المكتب',               'label_en' => 'Office Address',          'type' => 'textarea','required' => true, 'section' => 'office_info'],
                        ['id' => 'office_phone',      'label_ar' => 'هاتف المكتب',               'label_en' => 'Office Phone',            'type' => 'text',   'required' => true,  'section' => 'office_info'],
                        ['id' => 'office_email',      'label_ar' => 'البريد الإلكتروني للمكتب', 'label_en' => 'Office Email',            'type' => 'email',  'required' => false, 'section' => 'office_info'],
                        ['id' => 'owner_name_ar',     'label_ar' => 'اسم المالك / الشريك المسؤول', 'label_en' => 'Owner Name',           'type' => 'text',   'required' => true,  'section' => 'owner_info'],
                        ['id' => 'owner_membership',  'label_ar' => 'رقم عضوية المالك في النقابة', 'label_en' => 'Owner JEA Membership Number', 'type' => 'text', 'required' => true, 'section' => 'owner_info'],
                        ['id' => 'owner_specialization', 'label_ar' => 'تخصص المالك',           'label_en' => 'Owner Specialization',    'type' => 'select', 'required' => true,  'section' => 'owner_info',
                            'options' => [
                                ['value' => 'civil',         'label_ar' => 'مدني',         'label_en' => 'Civil'],
                                ['value' => 'electrical',    'label_ar' => 'كهربائي',      'label_en' => 'Electrical'],
                                ['value' => 'mechanical',    'label_ar' => 'ميكانيكي',     'label_en' => 'Mechanical'],
                                ['value' => 'architectural', 'label_ar' => 'معماري',       'label_en' => 'Architectural'],
                                ['value' => 'multi',         'label_ar' => 'متعدد التخصصات', 'label_en' => 'Multi-disciplinary'],
                            ],
                        ],
                        ['id' => 'office_activities', 'label_ar' => 'أنشطة المكتب',              'label_en' => 'Office Activities',       'type' => 'checkbox_group', 'required' => true, 'section' => 'activity_info',
                            'options' => [
                                ['value' => 'design',          'label_ar' => 'تصميم هندسي',       'label_en' => 'Engineering Design'],
                                ['value' => 'supervision',     'label_ar' => 'إشراف وإدارة مشاريع', 'label_en' => 'Project Supervision'],
                                ['value' => 'consulting',      'label_ar' => 'استشارات هندسية',   'label_en' => 'Engineering Consulting'],
                                ['value' => 'surveying',       'label_ar' => 'مساحة ورسم',        'label_en' => 'Surveying & Drafting'],
                                ['value' => 'testing',         'label_ar' => 'فحص واختبار',       'label_en' => 'Testing & Inspection'],
                            ],
                        ],
                        ['id' => 'num_engineers',     'label_ar' => 'عدد المهندسين في المكتب',   'label_en' => 'Number of Engineers',     'type' => 'number', 'required' => true,  'section' => 'activity_info'],
                    ],
                    'documents' => [
                        ['id' => 'trade_registration',  'label_ar' => 'صورة السجل التجاري',         'label_en' => 'Trade Registration Copy',     'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 5],
                        ['id' => 'owner_id',             'label_ar' => 'صورة هوية المالك',           'label_en' => 'Owner ID Copy',                'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 3],
                        ['id' => 'owner_membership_card','label_ar' => 'بطاقة عضوية المالك في النقابة', 'label_en' => 'Owner JEA Membership Card', 'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 3],
                        ['id' => 'office_lease',         'label_ar' => 'عقد إيجار / ملكية المكتب', 'label_en' => 'Office Lease / Ownership',     'required' => true,  'accept' => ['pdf', 'jpg'],         'max_size_mb' => 5],
                        ['id' => 'partnership_contract', 'label_ar' => 'عقد الشراكة (إن وجد)',       'label_en' => 'Partnership Contract (if any)','required' => false, 'accept' => ['pdf'],                'max_size_mb' => 5],
                    ],
                    'certificate' => [
                        'validity_months' => 12,
                        'title_ar'        => 'ترخيص مكتب هندسي',
                        'title_en'        => 'Engineering Office License',
                        'fields_on_cert'  => ['office_name_ar', 'owner_name_ar', 'owner_specialization'],
                    ],
                ],
            ],

            // ── 4. تجديد ترخيص مكتب هندسي ───────────────────────────
            [
                'organization_id' => $orgId,
                'code'            => 'JEA-OFF-002',
                'name_ar'         => 'تجديد ترخيص مكتب هندسي',
                'name_en'         => 'Engineering Office License Renewal',
                'description_ar'  => 'تجديد ترخيص مزاولة المهنة للمكتب الهندسي الاستشاري',
                'description_en'  => 'Renew the professional practice license for an engineering consulting office',
                'currency'        => 'JOD',
                'status'          => 'active',
                'schema'          => [
                    'service_code' => 'JEA-OFF-002',
                    'name_ar'      => 'تجديد ترخيص مكتب هندسي',
                    'name_en'      => 'Engineering Office License Renewal',
                    'version'      => '1.0',
                    'workflow'     => [
                        'stages' => [
                            ['id' => 'docs_check',    'label_ar' => 'فحص الوثائق والمستحقات', 'label_en' => 'Documents & Dues Check', 'role' => 'staff',   'sla_hours' => 24, 'actions' => ['approve', 'reject', 'request_modifications']],
                            ['id' => 'final_approval','label_ar' => 'إصدار الترخيص المجدد',   'label_en' => 'Renewed License Issuance','role' => 'auditor', 'sla_hours' => 24, 'actions' => ['approve', 'reject']],
                        ],
                    ],
                    'fee'      => ['type' => 'fixed', 'amount' => 80, 'currency' => 'JOD'],
                    'sections' => [
                        ['id' => 'office_info',  'label_ar' => 'بيانات المكتب',         'label_en' => 'Office Information'],
                        ['id' => 'renewal_info', 'label_ar' => 'بيانات التجديد',        'label_en' => 'Renewal Details'],
                    ],
                    'fields' => [
                        ['id' => 'office_license_number', 'label_ar' => 'رقم ترخيص المكتب الحالي', 'label_en' => 'Current Office License Number', 'type' => 'text',   'required' => true,  'section' => 'office_info'],
                        ['id' => 'office_name_ar',        'label_ar' => 'اسم المكتب',               'label_en' => 'Office Name',                   'type' => 'text',   'required' => true,  'section' => 'office_info'],
                        ['id' => 'office_phone',          'label_ar' => 'هاتف المكتب',              'label_en' => 'Office Phone',                  'type' => 'text',   'required' => true,  'section' => 'office_info'],
                        ['id' => 'renewal_year',          'label_ar' => 'سنة التجديد',              'label_en' => 'Renewal Year',                  'type' => 'number', 'required' => true,  'section' => 'renewal_info'],
                        ['id' => 'changes_note',          'label_ar' => 'ملاحظات (أي تغييرات على المكتب)', 'label_en' => 'Notes (Any changes to the office)', 'type' => 'textarea', 'required' => false, 'section' => 'renewal_info'],
                    ],
                    'documents' => [
                        ['id' => 'old_license',        'label_ar' => 'نسخة الترخيص القديم',          'label_en' => 'Old License Copy',       'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 3],
                        ['id' => 'trade_registration', 'label_ar' => 'السجل التجاري السنوي المجدد',   'label_en' => 'Updated Trade Registration', 'required' => true, 'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 5],
                        ['id' => 'dues_clearance',     'label_ar' => 'براءة ذمة اشتراكات النقابة', 'label_en' => 'JEA Dues Clearance',     'required' => true,  'accept' => ['pdf'],                'max_size_mb' => 2],
                    ],
                    'certificate' => [
                        'validity_months' => 12,
                        'title_ar'        => 'تجديد ترخيص مكتب هندسي',
                        'title_en'        => 'Engineering Office License Renewal',
                        'fields_on_cert'  => ['office_license_number', 'office_name_ar', 'renewal_year'],
                    ],
                ],
            ],

            // ── 5. طلب تصريح بناء ────────────────────────────────────
            [
                'organization_id' => $orgId,
                'code'            => 'JEA-BP-001',
                'name_ar'         => 'طلب الموافقة على مخططات البناء',
                'name_en'         => 'Building Plans Approval',
                'description_ar'  => 'تقديم مخططات البناء للمراجعة الهندسية والحصول على موافقة النقابة',
                'description_en'  => 'Submit building plans for engineering review and JEA approval',
                'currency'        => 'JOD',
                'status'          => 'active',
                'schema'          => [
                    'service_code' => 'JEA-BP-001',
                    'name_ar'      => 'طلب الموافقة على مخططات البناء',
                    'name_en'      => 'Building Plans Approval',
                    'version'      => '1.0',
                    'workflow'     => [
                        'stages' => [
                            ['id' => 'initial_check',    'label_ar' => 'الفحص الأولي للمخططات',   'label_en' => 'Initial Plans Check',    'role' => 'staff',   'sla_hours' => 48,  'actions' => ['approve', 'reject', 'request_modifications']],
                            ['id' => 'technical_review', 'label_ar' => 'المراجعة التقنية',         'label_en' => 'Technical Review',        'role' => 'auditor', 'sla_hours' => 72,  'actions' => ['approve', 'reject', 'request_modifications']],
                            ['id' => 'final_stamp',      'label_ar' => 'ختم وإصدار الموافقة',     'label_en' => 'Stamp & Issue Approval',  'role' => 'staff',   'sla_hours' => 24,  'actions' => ['approve', 'reject']],
                        ],
                    ],
                    'fee'      => ['type' => 'tiered', 'currency' => 'JOD'],
                    'sections' => [
                        ['id' => 'project_info',     'label_ar' => 'معلومات المشروع',    'label_en' => 'Project Information'],
                        ['id' => 'owner_info',       'label_ar' => 'معلومات المالك',     'label_en' => 'Owner Information'],
                        ['id' => 'designer_info',    'label_ar' => 'معلومات المصمم',     'label_en' => 'Designer Information'],
                        ['id' => 'structural_info',  'label_ar' => 'البيانات الإنشائية', 'label_en' => 'Structural Data'],
                    ],
                    'fields' => [
                        ['id' => 'project_name',       'label_ar' => 'اسم المشروع',                 'label_en' => 'Project Name',         'type' => 'text',    'required' => true,  'section' => 'project_info'],
                        ['id' => 'project_location',   'label_ar' => 'موقع المشروع / العنوان',      'label_en' => 'Project Location',     'type' => 'textarea','required' => true,  'section' => 'project_info'],
                        ['id' => 'land_parcel',        'label_ar' => 'رقم القطعة والحوض',           'label_en' => 'Land Parcel & Basin',  'type' => 'text',    'required' => true,  'section' => 'project_info'],
                        ['id' => 'project_type',       'label_ar' => 'نوع المشروع',                 'label_en' => 'Project Type',         'type' => 'select',  'required' => true,  'section' => 'project_info',
                            'options' => [
                                ['value' => 'residential',   'label_ar' => 'سكني',           'label_en' => 'Residential'],
                                ['value' => 'commercial',    'label_ar' => 'تجاري',           'label_en' => 'Commercial'],
                                ['value' => 'industrial',    'label_ar' => 'صناعي',           'label_en' => 'Industrial'],
                                ['value' => 'mixed',         'label_ar' => 'مختلط',           'label_en' => 'Mixed Use'],
                                ['value' => 'institutional', 'label_ar' => 'مؤسسي / حكومي',   'label_en' => 'Institutional'],
                            ],
                        ],
                        ['id' => 'total_area',         'label_ar' => 'المساحة الإجمالية (م²)',     'label_en' => 'Total Area (m²)',       'type' => 'number',  'required' => true,  'section' => 'structural_info'],
                        ['id' => 'num_floors',         'label_ar' => 'عدد الطوابق',               'label_en' => 'Number of Floors',      'type' => 'number',  'required' => true,  'section' => 'structural_info'],
                        ['id' => 'structure_type',     'label_ar' => 'نوع الهيكل الإنشائي',       'label_en' => 'Structure Type',        'type' => 'select',  'required' => true,  'section' => 'structural_info',
                            'options' => [
                                ['value' => 'concrete', 'label_ar' => 'خرسانة مسلحة', 'label_en' => 'Reinforced Concrete'],
                                ['value' => 'steel',    'label_ar' => 'هيكل حديدي',   'label_en' => 'Steel Structure'],
                                ['value' => 'masonry',  'label_ar' => 'بناء حجري',    'label_en' => 'Masonry'],
                            ],
                        ],
                        ['id' => 'owner_name_ar',      'label_ar' => 'اسم المالك',                'label_en' => 'Owner Name',            'type' => 'text',    'required' => true,  'section' => 'owner_info'],
                        ['id' => 'owner_id_number',    'label_ar' => 'رقم هوية المالك',          'label_en' => 'Owner ID Number',       'type' => 'text',    'required' => true,  'section' => 'owner_info'],
                        ['id' => 'owner_phone',        'label_ar' => 'هاتف المالك',              'label_en' => 'Owner Phone',           'type' => 'text',    'required' => true,  'section' => 'owner_info'],
                        ['id' => 'designer_name',      'label_ar' => 'اسم المهندس المصمم',      'label_en' => 'Designer Engineer',     'type' => 'text',    'required' => true,  'section' => 'designer_info'],
                        ['id' => 'designer_membership','label_ar' => 'رقم عضوية المصمم في النقابة', 'label_en' => 'Designer JEA Number', 'type' => 'text',   'required' => true,  'section' => 'designer_info'],
                    ],
                    'documents' => [
                        ['id' => 'architectural_plans', 'label_ar' => 'المخططات المعمارية',          'label_en' => 'Architectural Plans',    'required' => true,  'accept' => ['pdf', 'dwg'],  'max_size_mb' => 50],
                        ['id' => 'structural_plans',    'label_ar' => 'المخططات الإنشائية',          'label_en' => 'Structural Plans',       'required' => true,  'accept' => ['pdf', 'dwg'],  'max_size_mb' => 50],
                        ['id' => 'electrical_plans',    'label_ar' => 'المخططات الكهربائية',         'label_en' => 'Electrical Plans',       'required' => true,  'accept' => ['pdf', 'dwg'],  'max_size_mb' => 30],
                        ['id' => 'plumbing_plans',      'label_ar' => 'مخططات الصرف الصحي والسباكة','label_en' => 'Plumbing & Sanitary',    'required' => true,  'accept' => ['pdf', 'dwg'],  'max_size_mb' => 30],
                        ['id' => 'land_deed',           'label_ar' => 'سند الملكية / وكالة',        'label_en' => 'Land Deed / Power of Attorney', 'required' => true, 'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 5],
                        ['id' => 'soil_report',         'label_ar' => 'تقرير تحسسات التربة',        'label_en' => 'Soil Investigation Report', 'required' => false, 'accept' => ['pdf'],         'max_size_mb' => 10],
                    ],
                    'certificate' => [
                        'validity_months' => 12,
                        'title_ar'        => 'موافقة على مخططات البناء',
                        'title_en'        => 'Building Plans Approval',
                        'fields_on_cert'  => ['project_name', 'owner_name_ar', 'project_location', 'project_type'],
                    ],
                ],
            ],

            // ── 6. الكشف الميداني على الموقع ─────────────────────────
            [
                'organization_id' => $orgId,
                'code'            => 'JEA-SURV-001',
                'name_ar'         => 'الكشف الميداني على الموقع',
                'name_en'         => 'Site Field Inspection',
                'description_ar'  => 'طلب إجراء كشف هندسي ميداني على الموقع من قبل مهندسي النقابة',
                'description_en'  => 'Request an engineering field inspection of the site by JEA engineers',
                'currency'        => 'JOD',
                'status'          => 'active',
                'schema'          => [
                    'service_code' => 'JEA-SURV-001',
                    'name_ar'      => 'الكشف الميداني على الموقع',
                    'name_en'      => 'Site Field Inspection',
                    'version'      => '1.0',
                    'workflow'     => [
                        'stages' => [
                            ['id' => 'scheduling',    'label_ar' => 'جدولة موعد الكشف',   'label_en' => 'Schedule Inspection',    'role' => 'staff',   'sla_hours' => 24, 'actions' => ['approve', 'reject', 'request_modifications']],
                            ['id' => 'inspection',    'label_ar' => 'إجراء الكشف الميداني','label_en' => 'Conduct Inspection',     'role' => 'staff',   'sla_hours' => 72, 'actions' => ['approve', 'request_modifications']],
                            ['id' => 'report_review', 'label_ar' => 'مراجعة تقرير الكشف', 'label_en' => 'Review Inspection Report','role' => 'auditor', 'sla_hours' => 48, 'actions' => ['approve', 'reject']],
                        ],
                    ],
                    'fee'      => ['type' => 'fixed', 'amount' => 75, 'currency' => 'JOD'],
                    'sections' => [
                        ['id' => 'request_info', 'label_ar' => 'بيانات الطلب',   'label_en' => 'Request Details'],
                        ['id' => 'site_info',    'label_ar' => 'بيانات الموقع',  'label_en' => 'Site Information'],
                        ['id' => 'applicant',    'label_ar' => 'بيانات مقدم الطلب', 'label_en' => 'Applicant Details'],
                    ],
                    'fields' => [
                        ['id' => 'inspection_purpose', 'label_ar' => 'الغرض من الكشف', 'label_en' => 'Inspection Purpose', 'type' => 'select', 'required' => true, 'section' => 'request_info',
                            'options' => [
                                ['value' => 'building_permit',    'label_ar' => 'تصريح بناء',              'label_en' => 'Building Permit'],
                                ['value' => 'violation_check',    'label_ar' => 'كشف مخالفة',              'label_en' => 'Violation Inspection'],
                                ['value' => 'structural_safety',  'label_ar' => 'سلامة إنشائية',           'label_en' => 'Structural Safety'],
                                ['value' => 'completion_cert',    'label_ar' => 'شهادة إنجاز',             'label_en' => 'Completion Certificate'],
                                ['value' => 'dispute',            'label_ar' => 'نزاع هندسي',              'label_en' => 'Engineering Dispute'],
                                ['value' => 'other',              'label_ar' => 'أخرى',                    'label_en' => 'Other'],
                            ],
                        ],
                        ['id' => 'inspection_details', 'label_ar' => 'تفاصيل إضافية عن طلب الكشف', 'label_en' => 'Additional Details', 'type' => 'textarea', 'required' => false, 'section' => 'request_info'],
                        ['id' => 'preferred_date',     'label_ar' => 'التاريخ المفضل للكشف',        'label_en' => 'Preferred Inspection Date', 'type' => 'date', 'required' => true, 'section' => 'request_info'],
                        ['id' => 'site_address',       'label_ar' => 'عنوان الموقع التفصيلي',       'label_en' => 'Site Detailed Address', 'type' => 'textarea', 'required' => true, 'section' => 'site_info'],
                        ['id' => 'land_parcel',        'label_ar' => 'رقم القطعة والحوض',           'label_en' => 'Land Parcel & Basin',  'type' => 'text', 'required' => true,  'section' => 'site_info'],
                        ['id' => 'governorate',        'label_ar' => 'المحافظة',                    'label_en' => 'Governorate',           'type' => 'select', 'required' => true, 'section' => 'site_info',
                            'options' => [
                                ['value' => 'amman',    'label_ar' => 'عمّان',    'label_en' => 'Amman'],
                                ['value' => 'zarqa',    'label_ar' => 'الزرقاء',  'label_en' => 'Zarqa'],
                                ['value' => 'irbid',    'label_ar' => 'إربد',     'label_en' => 'Irbid'],
                                ['value' => 'balqa',    'label_ar' => 'البلقاء',  'label_en' => 'Balqa'],
                                ['value' => 'aqaba',    'label_ar' => 'العقبة',   'label_en' => 'Aqaba'],
                                ['value' => 'mafraq',   'label_ar' => 'المفرق',   'label_en' => 'Mafraq'],
                                ['value' => 'karak',    'label_ar' => 'الكرك',    'label_en' => 'Karak'],
                                ['value' => 'madaba',   'label_ar' => 'مادبا',    'label_en' => 'Madaba'],
                                ['value' => 'jerash',   'label_ar' => 'جرش',      'label_en' => 'Jerash'],
                                ['value' => 'ajloun',   'label_ar' => 'عجلون',    'label_en' => 'Ajloun'],
                                ['value' => 'maan',     'label_ar' => 'معان',     'label_en' => "Ma'an"],
                                ['value' => 'tafileh',  'label_ar' => 'الطفيلة', 'label_en' => 'Tafileh'],
                            ],
                        ],
                        ['id' => 'applicant_name',   'label_ar' => 'اسم مقدم الطلب',     'label_en' => 'Applicant Name',   'type' => 'text',  'required' => true,  'section' => 'applicant'],
                        ['id' => 'applicant_role',   'label_ar' => 'صفة مقدم الطلب',     'label_en' => 'Applicant Role',   'type' => 'select', 'required' => true, 'section' => 'applicant',
                            'options' => [
                                ['value' => 'owner',      'label_ar' => 'مالك',           'label_en' => 'Owner'],
                                ['value' => 'contractor', 'label_ar' => 'مقاول',          'label_en' => 'Contractor'],
                                ['value' => 'engineer',   'label_ar' => 'مهندس مشرف',    'label_en' => 'Supervising Engineer'],
                                ['value' => 'attorney',   'label_ar' => 'وكيل قانوني',   'label_en' => 'Legal Attorney'],
                            ],
                        ],
                        ['id' => 'applicant_phone',  'label_ar' => 'رقم هاتف مقدم الطلب', 'label_en' => 'Applicant Phone', 'type' => 'text',  'required' => true,  'section' => 'applicant'],
                    ],
                    'documents' => [
                        ['id' => 'land_deed',          'label_ar' => 'صورة سند الملكية',           'label_en' => 'Land Deed Copy',          'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 5],
                        ['id' => 'location_map',       'label_ar' => 'خريطة الموقع',               'label_en' => 'Location Map',            'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 5],
                        ['id' => 'existing_plans',     'label_ar' => 'مخططات موجودة (إن وجدت)',    'label_en' => 'Existing Plans (if any)', 'required' => false, 'accept' => ['pdf', 'dwg', 'jpg'], 'max_size_mb' => 30],
                        ['id' => 'site_photos',        'label_ar' => 'صور للموقع الحالي',          'label_en' => 'Current Site Photos',     'required' => false, 'accept' => ['jpg', 'png'],         'max_size_mb' => 20],
                    ],
                    'certificate' => [
                        'validity_months' => 6,
                        'title_ar'        => 'تقرير الكشف الميداني',
                        'title_en'        => 'Field Inspection Report',
                        'fields_on_cert'  => ['site_address', 'inspection_purpose', 'applicant_name'],
                    ],
                ],
            ],

            // ── 7. طلب شهادة خبرة ────────────────────────────────────
            [
                'organization_id' => $orgId,
                'code'            => 'JEA-CERT-001',
                'name_ar'         => 'طلب شهادة خبرة هندسية',
                'name_en'         => 'Engineering Experience Certificate',
                'description_ar'  => 'طلب إصدار شهادة خبرة هندسية مصدقة من نقابة المهندسين الأردنيين',
                'description_en'  => 'Request an engineering experience certificate certified by JEA',
                'currency'        => 'JOD',
                'status'          => 'active',
                'schema'          => [
                    'service_code' => 'JEA-CERT-001',
                    'name_ar'      => 'طلب شهادة خبرة هندسية',
                    'name_en'      => 'Engineering Experience Certificate',
                    'version'      => '1.0',
                    'workflow'     => [
                        'stages' => [
                            ['id' => 'verification', 'label_ar' => 'التحقق من بيانات المهندس', 'label_en' => 'Engineer Data Verification', 'role' => 'staff',   'sla_hours' => 24, 'actions' => ['approve', 'reject', 'request_modifications']],
                            ['id' => 'issuance',     'label_ar' => 'إصدار الشهادة',            'label_en' => 'Certificate Issuance',       'role' => 'auditor', 'sla_hours' => 24, 'actions' => ['approve', 'reject']],
                        ],
                    ],
                    'fee'      => ['type' => 'fixed', 'amount' => 20, 'currency' => 'JOD'],
                    'sections' => [
                        ['id' => 'engineer_info',    'label_ar' => 'بيانات المهندس',       'label_en' => 'Engineer Information'],
                        ['id' => 'experience_info',  'label_ar' => 'بيانات الخبرة',        'label_en' => 'Experience Details'],
                        ['id' => 'purpose_info',     'label_ar' => 'الغرض من الشهادة',     'label_en' => 'Certificate Purpose'],
                    ],
                    'fields' => [
                        ['id' => 'membership_number',  'label_ar' => 'رقم العضوية في النقابة', 'label_en' => 'JEA Membership Number', 'type' => 'text',   'required' => true,  'section' => 'engineer_info'],
                        ['id' => 'full_name_ar',        'label_ar' => 'الاسم الكامل بالعربية', 'label_en' => 'Full Name (Arabic)',    'type' => 'text',   'required' => true,  'section' => 'engineer_info'],
                        ['id' => 'full_name_en',        'label_ar' => 'الاسم الكامل بالإنجليزية', 'label_en' => 'Full Name (English)', 'type' => 'text',  'required' => true,  'section' => 'engineer_info'],
                        ['id' => 'specialization',     'label_ar' => 'التخصص الهندسي',        'label_en' => 'Engineering Specialization', 'type' => 'text', 'required' => true, 'section' => 'engineer_info'],
                        ['id' => 'employer_name',      'label_ar' => 'اسم جهة العمل',         'label_en' => 'Employer Name',         'type' => 'text',   'required' => true,  'section' => 'experience_info'],
                        ['id' => 'employer_address',   'label_ar' => 'عنوان جهة العمل',       'label_en' => 'Employer Address',      'type' => 'text',   'required' => true,  'section' => 'experience_info'],
                        ['id' => 'employment_start',   'label_ar' => 'تاريخ بداية العمل',     'label_en' => 'Employment Start Date', 'type' => 'date',   'required' => true,  'section' => 'experience_info'],
                        ['id' => 'employment_end',     'label_ar' => 'تاريخ نهاية العمل (أو حتى الآن)', 'label_en' => 'Employment End Date', 'type' => 'date', 'required' => false, 'section' => 'experience_info'],
                        ['id' => 'job_title',          'label_ar' => 'المسمى الوظيفي',        'label_en' => 'Job Title',             'type' => 'text',   'required' => true,  'section' => 'experience_info'],
                        ['id' => 'certificate_language','label_ar' => 'لغة الشهادة',          'label_en' => 'Certificate Language',  'type' => 'radio',  'required' => true,  'section' => 'purpose_info',
                            'options' => [
                                ['value' => 'arabic',   'label_ar' => 'عربي فقط',     'label_en' => 'Arabic Only'],
                                ['value' => 'english',  'label_ar' => 'إنجليزي فقط',  'label_en' => 'English Only'],
                                ['value' => 'both',     'label_ar' => 'عربي وإنجليزي', 'label_en' => 'Arabic & English'],
                            ],
                        ],
                        ['id' => 'certificate_purpose','label_ar' => 'الغرض من الشهادة',      'label_en' => 'Certificate Purpose',   'type' => 'select', 'required' => true,  'section' => 'purpose_info',
                            'options' => [
                                ['value' => 'employment',    'label_ar' => 'تقديم لوظيفة',    'label_en' => 'Job Application'],
                                ['value' => 'visa',          'label_ar' => 'طلب تأشيرة',      'label_en' => 'Visa Application'],
                                ['value' => 'tender',        'label_ar' => 'مناقصة',           'label_en' => 'Tender'],
                                ['value' => 'professional',  'label_ar' => 'اعتراف مهني',     'label_en' => 'Professional Recognition'],
                                ['value' => 'other',         'label_ar' => 'أخرى',             'label_en' => 'Other'],
                            ],
                        ],
                    ],
                    'documents' => [
                        ['id' => 'employment_letter', 'label_ar' => 'كتاب من جهة العمل',          'label_en' => 'Employment Letter',         'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 3],
                        ['id' => 'membership_card',   'label_ar' => 'بطاقة العضوية في النقابة',   'label_en' => 'JEA Membership Card',       'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 2],
                        ['id' => 'national_id',       'label_ar' => 'صورة الهوية الوطنية',        'label_en' => 'National ID Copy',          'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 3],
                    ],
                    'certificate' => [
                        'validity_months' => 6,
                        'title_ar'        => 'شهادة خبرة هندسية',
                        'title_en'        => 'Engineering Experience Certificate',
                        'fields_on_cert'  => ['full_name_ar', 'full_name_en', 'membership_number', 'specialization', 'employer_name', 'job_title', 'employment_start'],
                    ],
                ],
            ],

            // ── 8. طلب خطاب رسمي ─────────────────────────────────────
            [
                'organization_id' => $orgId,
                'code'            => 'JEA-LTR-001',
                'name_ar'         => 'طلب خطاب رسمي من النقابة',
                'name_en'         => 'Official Letter Request',
                'description_ar'  => 'طلب إصدار خطاب رسمي موقع وممهور من نقابة المهندسين الأردنيين',
                'description_en'  => 'Request an official signed and stamped letter from JEA',
                'currency'        => 'JOD',
                'status'          => 'active',
                'schema'          => [
                    'service_code' => 'JEA-LTR-001',
                    'name_ar'      => 'طلب خطاب رسمي من النقابة',
                    'name_en'      => 'Official Letter Request',
                    'version'      => '1.0',
                    'workflow'     => [
                        'stages' => [
                            ['id' => 'review', 'label_ar' => 'مراجعة الطلب وإعداد الخطاب', 'label_en' => 'Review & Prepare Letter', 'role' => 'staff', 'sla_hours' => 24, 'actions' => ['approve', 'reject', 'request_modifications']],
                        ],
                    ],
                    'fee'      => ['type' => 'fixed', 'amount' => 10, 'currency' => 'JOD'],
                    'sections' => [
                        ['id' => 'applicant_info', 'label_ar' => 'بيانات مقدم الطلب', 'label_en' => 'Applicant Information'],
                        ['id' => 'letter_info',    'label_ar' => 'بيانات الخطاب',     'label_en' => 'Letter Details'],
                    ],
                    'fields' => [
                        ['id' => 'membership_number', 'label_ar' => 'رقم العضوية',        'label_en' => 'Membership Number',      'type' => 'text',     'required' => true,  'section' => 'applicant_info'],
                        ['id' => 'full_name_ar',       'label_ar' => 'الاسم الكامل',       'label_en' => 'Full Name',              'type' => 'text',     'required' => true,  'section' => 'applicant_info'],
                        ['id' => 'mobile',             'label_ar' => 'رقم الجوال',         'label_en' => 'Mobile Number',          'type' => 'text',     'required' => true,  'section' => 'applicant_info'],
                        ['id' => 'letter_type',        'label_ar' => 'نوع الخطاب',         'label_en' => 'Letter Type',            'type' => 'select',   'required' => true,  'section' => 'letter_info',
                            'options' => [
                                ['value' => 'membership_proof',   'label_ar' => 'إثبات عضوية',              'label_en' => 'Membership Proof'],
                                ['value' => 'good_standing',      'label_ar' => 'خطاب براءة ذمة',          'label_en' => 'Good Standing Letter'],
                                ['value' => 'recommendation',     'label_ar' => 'خطاب توصية',              'label_en' => 'Recommendation Letter'],
                                ['value' => 'to_embassy',         'label_ar' => 'خطاب إلى سفارة',          'label_en' => 'Letter to Embassy'],
                                ['value' => 'to_employer',        'label_ar' => 'خطاب إلى جهة العمل',     'label_en' => 'Letter to Employer'],
                                ['value' => 'to_bank',            'label_ar' => 'خطاب إلى بنك',           'label_en' => 'Letter to Bank'],
                                ['value' => 'other',              'label_ar' => 'أخرى',                    'label_en' => 'Other'],
                            ],
                        ],
                        ['id' => 'addressee',          'label_ar' => 'الجهة الموجه إليها الخطاب', 'label_en' => 'Letter Addressee', 'type' => 'text',     'required' => true,  'section' => 'letter_info'],
                        ['id' => 'letter_purpose',     'label_ar' => 'الغرض من الخطاب',           'label_en' => 'Letter Purpose',   'type' => 'textarea', 'required' => true,  'section' => 'letter_info'],
                        ['id' => 'letter_language',    'label_ar' => 'لغة الخطاب',               'label_en' => 'Letter Language',  'type' => 'radio',    'required' => true,  'section' => 'letter_info',
                            'options' => [
                                ['value' => 'arabic',  'label_ar' => 'عربي',  'label_en' => 'Arabic'],
                                ['value' => 'english', 'label_ar' => 'إنجليزي', 'label_en' => 'English'],
                            ],
                        ],
                        ['id' => 'num_copies',         'label_ar' => 'عدد النسخ المطلوبة',       'label_en' => 'Number of Copies', 'type' => 'number',   'required' => true,  'section' => 'letter_info'],
                    ],
                    'documents' => [
                        ['id' => 'national_id', 'label_ar' => 'صورة الهوية الوطنية', 'label_en' => 'National ID Copy', 'required' => true, 'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 3],
                    ],
                    'certificate' => [
                        'validity_months' => 3,
                        'title_ar'        => 'خطاب رسمي',
                        'title_en'        => 'Official Letter',
                        'fields_on_cert'  => ['full_name_ar', 'membership_number', 'letter_type', 'addressee'],
                    ],
                ],
            ],

            // ── 9. الانتساب لصندوق التكافل الاجتماعي ─────────────────
            [
                'organization_id' => $orgId,
                'code'            => 'JEA-SOC-001',
                'name_ar'         => 'الانتساب لصندوق التكافل الاجتماعي',
                'name_en'         => 'Social Solidarity Fund Registration',
                'description_ar'  => 'التسجيل في صندوق التكافل الاجتماعي لنقابة المهندسين للحصول على تغطية العجز والوفاة',
                'description_en'  => 'Register in JEA Social Solidarity Fund for disability and death coverage',
                'currency'        => 'JOD',
                'status'          => 'active',
                'schema'          => [
                    'service_code' => 'JEA-SOC-001',
                    'name_ar'      => 'الانتساب لصندوق التكافل الاجتماعي',
                    'name_en'      => 'Social Solidarity Fund Registration',
                    'version'      => '1.0',
                    'workflow'     => [
                        'stages' => [
                            ['id' => 'eligibility_check', 'label_ar' => 'التحقق من الأهلية',   'label_en' => 'Eligibility Check',    'role' => 'staff',   'sla_hours' => 48, 'actions' => ['approve', 'reject', 'request_modifications']],
                            ['id' => 'final_enrollment',  'label_ar' => 'التسجيل النهائي',      'label_en' => 'Final Enrollment',     'role' => 'auditor', 'sla_hours' => 24, 'actions' => ['approve', 'reject']],
                        ],
                    ],
                    'fee'      => ['type' => 'fixed', 'amount' => 25, 'currency' => 'JOD'],
                    'sections' => [
                        ['id' => 'member_info',      'label_ar' => 'بيانات المهندس',           'label_en' => 'Engineer Information'],
                        ['id' => 'beneficiary_info', 'label_ar' => 'بيانات المستفيد / الورثة', 'label_en' => 'Beneficiary Information'],
                        ['id' => 'health_info',      'label_ar' => 'الحالة الصحية',            'label_en' => 'Health Status'],
                    ],
                    'fields' => [
                        ['id' => 'membership_number',    'label_ar' => 'رقم العضوية',           'label_en' => 'Membership Number',      'type' => 'text',   'required' => true,  'section' => 'member_info'],
                        ['id' => 'full_name_ar',          'label_ar' => 'الاسم الكامل',          'label_en' => 'Full Name',              'type' => 'text',   'required' => true,  'section' => 'member_info'],
                        ['id' => 'national_id',           'label_ar' => 'رقم الهوية الوطنية',   'label_en' => 'National ID',            'type' => 'text',   'required' => true,  'section' => 'member_info'],
                        ['id' => 'birth_date',            'label_ar' => 'تاريخ الميلاد',         'label_en' => 'Date of Birth',          'type' => 'date',   'required' => true,  'section' => 'member_info'],
                        ['id' => 'mobile',                'label_ar' => 'رقم الجوال',             'label_en' => 'Mobile Number',          'type' => 'text',   'required' => true,  'section' => 'member_info'],
                        ['id' => 'marital_status',        'label_ar' => 'الحالة الاجتماعية',    'label_en' => 'Marital Status',         'type' => 'radio',  'required' => true,  'section' => 'member_info',
                            'options' => [
                                ['value' => 'single',   'label_ar' => 'أعزب / عزباء', 'label_en' => 'Single'],
                                ['value' => 'married',  'label_ar' => 'متزوج / متزوجة', 'label_en' => 'Married'],
                                ['value' => 'divorced', 'label_ar' => 'مطلق / مطلقة', 'label_en' => 'Divorced'],
                                ['value' => 'widowed',  'label_ar' => 'أرمل / أرملة', 'label_en' => 'Widowed'],
                            ],
                        ],
                        ['id' => 'beneficiary_name',      'label_ar' => 'اسم المستفيد الأول',   'label_en' => 'Primary Beneficiary',    'type' => 'text',   'required' => true,  'section' => 'beneficiary_info'],
                        ['id' => 'beneficiary_relation',  'label_ar' => 'صلة القرابة',           'label_en' => 'Relation',               'type' => 'select', 'required' => true,  'section' => 'beneficiary_info',
                            'options' => [
                                ['value' => 'spouse',  'label_ar' => 'زوج / زوجة', 'label_en' => 'Spouse'],
                                ['value' => 'child',   'label_ar' => 'ابن / ابنة',  'label_en' => 'Child'],
                                ['value' => 'parent',  'label_ar' => 'أب / أم',     'label_en' => 'Parent'],
                                ['value' => 'sibling', 'label_ar' => 'أخ / أخت',    'label_en' => 'Sibling'],
                            ],
                        ],
                        ['id' => 'beneficiary_id',        'label_ar' => 'رقم هوية المستفيد',    'label_en' => 'Beneficiary ID',         'type' => 'text',   'required' => true,  'section' => 'beneficiary_info'],
                        ['id' => 'pre_existing_conditions','label_ar' => 'هل تعاني من أمراض مزمنة؟', 'label_en' => 'Pre-existing Conditions?', 'type' => 'radio', 'required' => true, 'section' => 'health_info',
                            'options' => [
                                ['value' => 'no',  'label_ar' => 'لا', 'label_en' => 'No'],
                                ['value' => 'yes', 'label_ar' => 'نعم', 'label_en' => 'Yes'],
                            ],
                        ],
                        ['id' => 'conditions_details',    'label_ar' => 'تفاصيل الأمراض (إن وجدت)', 'label_en' => 'Conditions Details', 'type' => 'textarea', 'required' => false, 'section' => 'health_info',
                            'conditional' => ['field' => 'pre_existing_conditions', 'value' => 'yes'],
                        ],
                    ],
                    'documents' => [
                        ['id' => 'national_id_copy',    'label_ar' => 'صورة الهوية الوطنية',         'label_en' => 'National ID Copy',           'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 3],
                        ['id' => 'membership_card',     'label_ar' => 'بطاقة عضوية النقابة',         'label_en' => 'JEA Membership Card',         'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 2],
                        ['id' => 'beneficiary_id_copy', 'label_ar' => 'صورة هوية المستفيد',          'label_en' => 'Beneficiary ID Copy',         'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 3],
                        ['id' => 'medical_report',      'label_ar' => 'تقرير طبي (للأمراض المزمنة)', 'label_en' => 'Medical Report (if applicable)', 'required' => false, 'accept' => ['pdf'],             'max_size_mb' => 5,
                            'conditional' => ['field' => 'pre_existing_conditions', 'value' => 'yes'],
                        ],
                    ],
                    'certificate' => [
                        'validity_months' => 0,
                        'title_ar'        => 'شهادة الانتساب لصندوق التكافل الاجتماعي',
                        'title_en'        => 'Social Solidarity Fund Enrollment Certificate',
                        'fields_on_cert'  => ['full_name_ar', 'membership_number', 'national_id'],
                    ],
                ],
            ],

            // ── 10. تسجيل شركة هندسية ────────────────────────────────
            [
                'organization_id' => $orgId,
                'code'            => 'JEA-COMP-001',
                'name_ar'         => 'تسجيل شركة هندسية استشارية',
                'name_en'         => 'Engineering Consulting Company Registration',
                'description_ar'  => 'تسجيل شركة هندسية استشارية لدى نقابة المهندسين الأردنيين وإدراجها في السجل الرسمي',
                'description_en'  => 'Register an engineering consulting company with JEA and include it in the official registry',
                'currency'        => 'JOD',
                'status'          => 'active',
                'schema'          => [
                    'service_code' => 'JEA-COMP-001',
                    'name_ar'      => 'تسجيل شركة هندسية استشارية',
                    'name_en'      => 'Engineering Consulting Company Registration',
                    'version'      => '1.0',
                    'workflow'     => [
                        'stages' => [
                            ['id' => 'docs_review',     'label_ar' => 'مراجعة وثائق الشركة',   'label_en' => 'Company Documents Review', 'role' => 'staff',   'sla_hours' => 72, 'actions' => ['approve', 'reject', 'request_modifications']],
                            ['id' => 'partners_verify', 'label_ar' => 'التحقق من الشركاء',      'label_en' => 'Partners Verification',    'role' => 'staff',   'sla_hours' => 48, 'actions' => ['approve', 'reject', 'request_modifications']],
                            ['id' => 'final_approval',  'label_ar' => 'الموافقة النهائية',      'label_en' => 'Final Approval',           'role' => 'auditor', 'sla_hours' => 24, 'actions' => ['approve', 'reject']],
                        ],
                    ],
                    'fee'      => ['type' => 'fixed', 'amount' => 300, 'currency' => 'JOD'],
                    'sections' => [
                        ['id' => 'company_info',    'label_ar' => 'بيانات الشركة',           'label_en' => 'Company Information'],
                        ['id' => 'partners_info',   'label_ar' => 'بيانات الشركاء المهندسين', 'label_en' => 'Engineer Partners'],
                        ['id' => 'activity_info',   'label_ar' => 'نشاط الشركة',             'label_en' => 'Company Activity'],
                    ],
                    'fields' => [
                        ['id' => 'company_name_ar',   'label_ar' => 'اسم الشركة بالعربية',      'label_en' => 'Company Name (Arabic)',    'type' => 'text',           'required' => true,  'section' => 'company_info'],
                        ['id' => 'company_name_en',   'label_ar' => 'اسم الشركة بالإنجليزية',   'label_en' => 'Company Name (English)',  'type' => 'text',           'required' => false, 'section' => 'company_info'],
                        ['id' => 'company_type',      'label_ar' => 'نوع الشركة القانوني',      'label_en' => 'Legal Company Type',      'type' => 'select',         'required' => true,  'section' => 'company_info',
                            'options' => [
                                ['value' => 'limited',     'label_ar' => 'شركة ذات مسؤولية محدودة', 'label_en' => 'Limited Liability Company'],
                                ['value' => 'partnership', 'label_ar' => 'شركة تضامن',              'label_en' => 'Partnership'],
                                ['value' => 'joint_stock', 'label_ar' => 'شركة مساهمة خاصة',       'label_en' => 'Private Shareholding'],
                            ],
                        ],
                        ['id' => 'registration_number','label_ar' => 'رقم التسجيل في وزارة الصناعة والتجارة', 'label_en' => 'MoIT Registration Number', 'type' => 'text', 'required' => true, 'section' => 'company_info'],
                        ['id' => 'company_address',   'label_ar' => 'عنوان الشركة',             'label_en' => 'Company Address',         'type' => 'textarea',       'required' => true,  'section' => 'company_info'],
                        ['id' => 'company_phone',     'label_ar' => 'هاتف الشركة',              'label_en' => 'Company Phone',           'type' => 'text',           'required' => true,  'section' => 'company_info'],
                        ['id' => 'company_email',     'label_ar' => 'البريد الإلكتروني للشركة', 'label_en' => 'Company Email',           'type' => 'email',          'required' => false, 'section' => 'company_info'],
                        ['id' => 'num_engineers',     'label_ar' => 'عدد المهندسين في الشركة',  'label_en' => 'Number of Engineers',     'type' => 'number',         'required' => true,  'section' => 'partners_info'],
                        ['id' => 'managing_partner',  'label_ar' => 'اسم الشريك المدير',        'label_en' => 'Managing Partner Name',   'type' => 'text',           'required' => true,  'section' => 'partners_info'],
                        ['id' => 'managing_partner_membership', 'label_ar' => 'رقم عضوية الشريك المدير', 'label_en' => 'Managing Partner JEA #', 'type' => 'text', 'required' => true, 'section' => 'partners_info'],
                        ['id' => 'company_activities','label_ar' => 'أنشطة الشركة',             'label_en' => 'Company Activities',      'type' => 'checkbox_group', 'required' => true,  'section' => 'activity_info',
                            'options' => [
                                ['value' => 'design',       'label_ar' => 'التصميم الهندسي',          'label_en' => 'Engineering Design'],
                                ['value' => 'supervision',  'label_ar' => 'الإشراف وإدارة المشاريع', 'label_en' => 'Project Supervision'],
                                ['value' => 'consulting',   'label_ar' => 'الاستشارات الهندسية',     'label_en' => 'Engineering Consulting'],
                                ['value' => 'feasibility',  'label_ar' => 'دراسات الجدوى',           'label_en' => 'Feasibility Studies'],
                                ['value' => 'surveying',    'label_ar' => 'المساحة والرسم',           'label_en' => 'Surveying & Drafting'],
                                ['value' => 'testing',      'label_ar' => 'الفحص والاختبار',          'label_en' => 'Testing & Inspection'],
                                ['value' => 'environment',  'label_ar' => 'الدراسات البيئية',         'label_en' => 'Environmental Studies'],
                                ['value' => 'energy',       'label_ar' => 'الطاقة المتجددة',          'label_en' => 'Renewable Energy'],
                            ],
                        ],
                    ],
                    'documents' => [
                        ['id' => 'company_registration', 'label_ar' => 'شهادة تسجيل الشركة',               'label_en' => 'Company Registration Certificate', 'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 5],
                        ['id' => 'articles_of_assoc',    'label_ar' => 'عقد التأسيس ونظام الشركة',         'label_en' => 'Articles of Association',           'required' => true,  'accept' => ['pdf'],                'max_size_mb' => 10],
                        ['id' => 'trade_license',        'label_ar' => 'الرخصة التجارية',                  'label_en' => 'Trade License',                     'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 5],
                        ['id' => 'partners_ids',         'label_ar' => 'صور هويات الشركاء المهندسين',      'label_en' => 'Partners IDs',                      'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 10],
                        ['id' => 'partners_memberships', 'label_ar' => 'بطاقات عضوية الشركاء في النقابة', 'label_en' => 'Partners JEA Memberships',           'required' => true,  'accept' => ['pdf', 'jpg', 'png'], 'max_size_mb' => 10],
                        ['id' => 'company_lease',        'label_ar' => 'عقد إيجار / ملكية الشركة',        'label_en' => 'Company Office Lease/Deed',          'required' => true,  'accept' => ['pdf', 'jpg'],         'max_size_mb' => 5],
                    ],
                    'certificate' => [
                        'validity_months' => 12,
                        'title_ar'        => 'شهادة تسجيل شركة هندسية استشارية',
                        'title_en'        => 'Engineering Consulting Company Registration Certificate',
                        'fields_on_cert'  => ['company_name_ar', 'company_name_en', 'managing_partner', 'registration_number'],
                    ],
                ],
            ],

        ];
    }
}
