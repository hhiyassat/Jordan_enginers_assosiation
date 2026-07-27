<?php

declare(strict_types=1);

namespace Modules\JeaProjects\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** P-3: FormRequest for POST /projects. */
class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'engineer_id' => ['required', 'integer', 'exists:engineers,id'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'area_m2' => ['nullable', 'integer', 'min:1'],
            'city' => ['nullable', 'string', 'max:100'],
            'contract_no' => ['nullable', 'string', 'max:50'],
        ];
    }
}
