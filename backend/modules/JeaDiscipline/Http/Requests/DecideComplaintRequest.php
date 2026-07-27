<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** P-3: FormRequest for POST /admin/complaints/{id}/decide (admin-role route group). */
class DecideComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:sanction,dismiss'],
            'sanction_kind' => ['required_if:decision,sanction', 'in:warning,suspension_1yr,suspension_2yr,deregistration'],
            'reason' => ['required_if:decision,sanction', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
