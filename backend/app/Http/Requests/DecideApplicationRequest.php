<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SEC-006: FormRequest for reviewer decision.
 * FR-010: Approve / reject / request_modifications.
 * EDA B-4: Notes required for non-approve decisions — enforced here.
 */
class DecideApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isReviewer() ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'decision'    => ['required', 'in:approved,rejected,modifications_requested'],
            'notes'       => ['nullable', 'string', 'max:2000'],
            'annotations' => ['nullable', 'array'],
        ];

        // EDA B-4: Notes required for non-approve decisions
        if ($this->input('decision') !== 'approved') {
            $rules['notes'][] = 'required';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'القرار مطلوب.',
            'decision.in'       => 'القرار يجب أن يكون: approved أو rejected أو modifications_requested.',
            'notes.required'    => 'ملاحظات القرار مطلوبة عند الرفض أو طلب التعديل.',
        ];
    }
}
