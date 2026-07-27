<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** P-3: FormRequest for POST /admin/supervision-transfers/{id}/accept-decline (admin-role route group). */
class DecideSupervisionTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'outcome' => ['required', 'in:accept,decline'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
