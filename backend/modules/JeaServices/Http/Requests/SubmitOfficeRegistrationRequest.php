<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Requests;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\JeaProjects\Engine\Disciplines;
use Modules\JeaServices\Engine\JeaMembershipVerifier;
use Modules\JeaServices\Engine\OfficeRegistrationValidator;
use Modules\JeaServices\Models\OfficeRegistrationRequest;

/**
 * SubmitOfficeRegistrationRequest — public office signup shape.
 *
 * Field-shape rules live here (FormRequest). Business rules
 * (min-engineers, single-engineer discipline, JEA membership check) are
 * delegated to OfficeRegistrationValidator via withValidator() so
 * the two concerns stay separate + individually testable.
 */
class SubmitOfficeRegistrationRequest extends FormRequest
{
    /** Public endpoint — anyone (unauthenticated) may submit. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Allow both 'civil' (alias) and canonical disciplines — Disciplines::normalize
        // folds 'civil' → 'structural' downstream.
        $acceptableDisciplines = array_merge(Disciplines::all(), [Disciplines::CIVIL_ALIAS]);

        return [
            'office_name_ar' => ['required', 'string', 'max:200',
                'unique:organizations,name_ar',
                $this->uniquePendingOrApprovedRule('office_name_ar')],
            'office_name_en' => ['required', 'string', 'max:200',
                'unique:organizations,name_en',
                $this->uniquePendingOrApprovedRule('office_name_en')],

            'office_license_no' => ['required', 'string', 'max:50',
                'unique:office_registration_requests,office_license_no,NULL,id,deleted_at,NULL'],

            'office_city'       => ['required', 'string', 'max:100'],
            'office_address_ar' => ['required', 'string', 'max:500'],
            'office_phone'      => ['required', 'string', 'max:30'],
            'office_email'      => ['required', 'email', 'max:255',
                'unique:users,email',
                'unique:office_registration_requests,office_email,NULL,id,deleted_at,NULL,status,pending'],

            'engineers'                     => ['required', 'array', 'min:1'],
            'engineers.*.name_ar'           => ['required', 'string', 'max:200'],
            'engineers.*.name_en'           => ['nullable', 'string', 'max:200'],
            'engineers.*.membership_number' => ['required', 'string', 'max:50'],
            'engineers.*.specialization'    => ['required', 'string',
                'in:' . implode(',', $acceptableDisciplines)],
        ];
    }

    /**
     * A tiny closure rule to check uniqueness against pending/approved
     * OfficeRegistrationRequest rows (in addition to the organizations
     * table check). Prevents two identical pending submissions.
     */
    private function uniquePendingOrApprovedRule(string $column): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($column) {
            $exists = OfficeRegistrationRequest::query()
                ->where($column, $value)
                ->whereIn('status', [
                    OfficeRegistrationRequest::STATUS_PENDING,
                    OfficeRegistrationRequest::STATUS_APPROVED,
                ])
                ->exists();
            if ($exists) {
                $fail("قيمة الحقل '{$attribute}' مستخدمة سابقاً في طلب تسجيل مكتب معلَّق أو معتمد.");
            }
        };
    }

    /**
     * After shape validation passes, run business-rule validation via
     * OfficeRegistrationValidator. Merges its errors into the same 422
     * response the FormRequest would return.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                // Field-shape issues already present; don't waste JEA API
                // calls on a payload that won't submit anyway.
                return;
            }
            $engineers = $this->input('engineers', []);
            $ruleErrors = app(OfficeRegistrationValidator::class)
                ->validate($engineers);
            foreach ($ruleErrors as $key => $msg) {
                $v->errors()->add($key, $msg);
            }
        });
    }

    public function messages(): array
    {
        return [
            'office_name_ar.required' => 'اسم المكتب بالعربية مطلوب.',
            'office_name_ar.unique'   => 'اسم المكتب بالعربية مستخدم لمكتب مسجَّل مسبقاً.',
            'office_name_en.required' => 'اسم المكتب بالإنجليزية مطلوب.',
            'office_name_en.unique'   => 'اسم المكتب بالإنجليزية مستخدم لمكتب مسجَّل مسبقاً.',
            'office_license_no.required' => 'رقم رخصة المكتب مطلوب.',
            'office_license_no.unique'   => 'رقم الرخصة مستخدم في طلب تسجيل سابق.',
            'office_email.required' => 'البريد الإلكتروني للمكتب مطلوب.',
            'office_email.email'    => 'البريد الإلكتروني غير صالح.',
            'office_email.unique'   => 'البريد الإلكتروني مسجَّل لحساب مستخدم آخر.',
            'engineers.required'    => 'يجب إضافة مهندس واحد على الأقل للمكتب.',
            'engineers.min'         => 'يجب إضافة مهندس واحد على الأقل للمكتب.',
        ];
    }
}
