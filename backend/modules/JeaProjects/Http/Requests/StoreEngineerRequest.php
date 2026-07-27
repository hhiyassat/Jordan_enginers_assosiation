<?php

declare(strict_types=1);

namespace Modules\JeaProjects\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** P-3: FormRequest for POST /engineers. */
class StoreEngineerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'membership_number' => ['required', 'string', 'max:50'],
            'specialization' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'annual_quota_m2' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
