<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** P-3: FormRequest for PATCH /admin/services/{id}/fee. */
class UpdateServiceFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canEditServices();
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'المسؤولون والمستخدم الأعلى فقط يمكنهم تعديل رسوم الخدمة.',
        ], 403));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:fixed,per_unit,free'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'basis' => ['nullable', 'string', 'max:64'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
