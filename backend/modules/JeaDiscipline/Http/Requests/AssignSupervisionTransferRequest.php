<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** P-3: FormRequest for POST /admin/supervision-transfers/{id}/assign (admin-role route group). */
class AssignSupervisionTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'target_office_user_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
