<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** P-3: FormRequest for POST /admin/legal-fines (admin-role route group). */
class StoreLegalFineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'in:unlicensed_contractor_small,unlicensed_contractor_large'],
            'amount_jod' => ['required', 'numeric', 'min:0'],
            'target_display' => ['required', 'string', 'max:255'],
            'project_area_m2' => ['nullable', 'integer', 'min:1'],
            'application_id' => ['nullable', 'integer', 'exists:applications,id'],
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
