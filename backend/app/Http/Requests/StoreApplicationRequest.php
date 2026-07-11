<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SEC-006: All input validated via FormRequest, never inline.
 * FR-002: Create a draft application for an active service.
 */
class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'service_code' => ['required', 'string', 'max:20'],
            'data'         => ['required', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_code.required' => 'رمز الخدمة مطلوب.',
            'data.required'         => 'بيانات الطلب مطلوبة.',
            'data.array'            => 'بيانات الطلب يجب أن تكون كائناً.',
        ];
    }
}
