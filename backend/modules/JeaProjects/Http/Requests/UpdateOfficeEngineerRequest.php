<?php

declare(strict_types=1);

namespace Modules\JeaProjects\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** P-3: FormRequest for PATCH /admin/offices/{officeId}/engineers/{engineerId} (admin-role route group). */
class UpdateOfficeEngineerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_specialization_head' => ['required', 'boolean'],
        ];
    }
}
