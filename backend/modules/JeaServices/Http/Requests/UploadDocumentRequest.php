<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Requests;

use App\Rules\PdfOrDwgFile;
use Illuminate\Foundation\Http\FormRequest;

/** P-3: FormRequest for POST /applications/{id}/documents. */
class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'document_id' => ['required', 'string'],
            // SEC-008 hardened: only PDF drawings and DWG source files are
            // accepted as application attachments. The PdfOrDwgFile rule
            // inspects the leading bytes (not just the extension) to reject
            // renamed executables. 50 MB outer cap matches the schema-level
            // per-slot cap enforced downstream by SchemaValidator.
            'file' => ['required', 'file', 'max:51200', new PdfOrDwgFile],
        ];
    }
}
