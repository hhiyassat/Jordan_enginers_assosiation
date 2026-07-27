<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** P-3: FormRequest for POST /services. */
class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canEditServices();
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'المسؤولون والمستخدم الأعلى فقط يمكنهم إنشاء خدمات جديدة.',
        ], 403));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:service_definitions,code'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'currency' => ['nullable', 'string', 'size:3'],
            'schema' => ['required', 'array'],
            'status' => ['nullable', 'in:active,inactive,draft'],
        ];
    }
}
