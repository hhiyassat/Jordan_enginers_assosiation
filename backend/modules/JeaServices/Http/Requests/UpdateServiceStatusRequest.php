<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** P-3: FormRequest for PATCH /services/{id}/status. */
class UpdateServiceStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canEditServices();
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'المسؤولون والمستخدم الأعلى فقط يمكنهم تغيير حالة الخدمة.',
        ], 403));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:active,inactive,draft'],
        ];
    }
}
