<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** P-3: FormRequest for PUT /applications/{id}. */
class UpdateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // `present`, not `required` — an empty {} is a legitimate draft
        // state; per-field enforcement runs in SchemaValidator on
        // POST /submit, same reasoning as StoreApplicationRequest.
        return [
            'data' => ['present', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.present' => 'حقل بيانات الطلب مفقود من الطلب.',
            'data.array' => 'بيانات الطلب يجب أن تكون كائناً.',
        ];
    }
}
