<?php

declare(strict_types=1);

namespace Modules\JeaDues\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** P-3: FormRequest for POST /admin/dues/{obligationId}/pay (admin-role route group). */
class PayRecurringDuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'payment_reference' => ['required', 'string', 'max:128'],
        ];
    }
}
