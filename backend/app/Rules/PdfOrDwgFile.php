<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * PdfOrDwgFile
 *
 * Verifies an uploaded file is genuinely a PDF or a DWG by reading its
 * leading bytes — extension and reported MIME type are NOT trusted. An
 * attacker who renames malware.exe to plans.pdf gets past the mimes:
 * rule (because Laravel guesses MIME from the extension when the file
 * has no proper header), but they can't fake the header itself without
 * making the file a real PDF or DWG.
 *
 * Magic bytes accepted:
 *   PDF  →  ASCII "%PDF-"  at offset 0
 *   DWG  →  ASCII "AC1"    at offset 0 (versions AC1012 through AC1032
 *                          cover R13 through R2018+; AutoCAD DWG files
 *                          always start with "AC1"). We tolerate any
 *                          "AC1XXX" so we don't have to chase every new
 *                          release, but we reject "AC0..." (very old)
 *                          and anything else.
 *
 * Used by ApplicationController::uploadDocument. Also enforces the
 * extension separately as a defense in depth — some object stores serve
 * content-type based on extension, so a mismatched .exe with a PDF header
 * would still be dangerous to serve back.
 */
class PdfOrDwgFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('يجب أن يكون الملف مرفقاً صحيحاً.');
            return;
        }

        $ext = strtolower($value->getClientOriginalExtension());
        if (! in_array($ext, ['pdf', 'dwg'], true)) {
            $fail('نوع الملف غير مسموح — يُقبل PDF أو DWG فقط.');
            return;
        }

        // Read exactly enough bytes to identify either header. PDF is
        // "%PDF-" (5 bytes); DWG is "AC1" followed by 3 digits. 8 bytes
        // covers both without loading the whole file.
        $handle = @fopen($value->getRealPath(), 'rb');
        if ($handle === false) {
            $fail('تعذر قراءة الملف للتحقق من صحته.');
            return;
        }
        $head = (string) fread($handle, 8);
        fclose($handle);

        $isPdf = str_starts_with($head, '%PDF-');
        // AC1XXX where XXX is three digits — reject old AC10.. and non-DWG.
        $isDwg = (bool) preg_match('/^AC1\d{3}/', $head);

        if ($ext === 'pdf' && ! $isPdf) {
            $fail('محتوى الملف لا يطابق ملف PDF — قد يكون مُعاد التسمية.');
            return;
        }
        if ($ext === 'dwg' && ! $isDwg) {
            $fail('محتوى الملف لا يطابق ملف DWG — قد يكون مُعاد التسمية.');
            return;
        }
    }
}
