<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** P-3: FormRequest for POST /complaints — any authenticated user may file. */
class StoreComplaintRequest extends FormRequest
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
            'kind' => ['required', 'in:fee_undercutting,contracting_ban,safety_violation,other'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            // Optional display name when reporter is external
            // (municipal officer, contractor without a login yet).
            'reporter_display' => ['nullable', 'string', 'max:128'],
        ];
    }
}
