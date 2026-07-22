<?php

namespace Modules\JeaServices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isStaff();
    }

    public function rules(): array
    {
        return [
            'payment_reference' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_reference.required' => 'مرجع الدفع مطلوب.',
        ];
    }
}
