<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * P-3: FormRequest for PATCH /admin/manual-references/{id}.
 *
 * The admin/superuser check runs in authorize() (before validation),
 * matching the original inline-validate() order: a non-admin caller
 * gets the 403 Arabic message even if their body is also malformed.
 */
class UpdateManualReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isAdmin() || $user->isSuperuser());
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'المسؤولون فقط يمكنهم تعديل النصوص النظامية.',
        ], 403));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'text_ar' => ['required', 'string', 'min:10'],
            'edit_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
