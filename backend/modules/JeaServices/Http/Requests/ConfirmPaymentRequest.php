<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CS-03: this is the MANUAL RECONCILIATION request, not proof-of-payment.
 * Requires an explicit `manual_reason` so every operator use has a
 * searchable audit trail. Restricted to admin — the raw
 * payment_reference alone is not sufficient authority to flip
 * `payment_status`.
 */
class ConfirmPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'payment_reference' => ['required', 'string', 'max:100'],
            'manual_reason'     => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_reference.required' => 'مرجع الدفع مطلوب.',
            'manual_reason.required'     => 'سبب التسوية اليدوية مطلوب (لا يقل عن ١٠ أحرف).',
            'manual_reason.min'          => 'يجب أن يوضّح السبب التسوية اليدوية بشكل كافٍ.',
        ];
    }
}
