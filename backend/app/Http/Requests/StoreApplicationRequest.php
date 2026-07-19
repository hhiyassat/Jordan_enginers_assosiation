<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SEC-006: All input validated via FormRequest, never inline.
 * FR-002: Create a draft application for an active service.
 */
class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'service_code' => ['required', 'string', 'max:20'],
            // `present` (not `required`) — the key must be posted, but an
            // empty object is fine at draft-save. Per-field enforcement
            // runs in SchemaValidator on POST /submit, so drafts stay
            // draftable even when no field has been filled yet. This is
            // load-bearing for schemas without any applicant-facing fields
            // (e.g. the tile-level placeholders and drawing services where
            // all inputs come from the linked project + attached documents).
            'data'         => ['present', 'array'],
            // Optional link to the applicant's project. When present the
            // Apply flow renders the project's read-only header instead of
            // asking the applicant to re-type the project's fields.
            'project_id'   => ['sometimes', 'nullable', 'integer', 'exists:projects,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_code.required' => 'رمز الخدمة مطلوب.',
            'data.present'          => 'حقل بيانات الطلب مفقود من الطلب.',
            'data.array'            => 'بيانات الطلب يجب أن تكون كائناً.',
            'project_id.exists'     => 'المشروع غير موجود.',
        ];
    }
}
