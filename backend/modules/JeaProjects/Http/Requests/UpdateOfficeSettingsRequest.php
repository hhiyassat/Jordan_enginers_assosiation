<?php

declare(strict_types=1);

namespace Modules\JeaProjects\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** P-3: FormRequest for PATCH /admin/offices/{id} (admin-role route group). */
class UpdateOfficeSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'has_excellence_award' => ['sometimes', 'boolean'],
            'is_bit_khibra' => ['sometimes', 'boolean'],
            'has_iso_cert' => ['sometimes', 'boolean'],
        ];
    }
}
