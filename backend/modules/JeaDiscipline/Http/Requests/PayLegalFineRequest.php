<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** P-3: FormRequest for POST /admin/legal-fines/{id}/pay (admin-role route group). */
class PayLegalFineRequest extends FormRequest
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
