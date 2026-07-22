<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use Modules\JeaServices\Engine\SchemaValidator;
use Modules\JeaServices\Models\ServiceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Pins the hardening from the JORD-1 and JORD-7 review tasks:
 *   • pattern injection is contained,
 *   • uncompilable patterns fail closed with a specific error,
 *   • strict typing on document-id lookups.
 *
 * SchemaValidatorTest already covers the happy-path validation surface;
 * these cases are the security regressions specifically.
 */
class SchemaValidatorHardeningTest extends TestCase
{
    private function makeService(array $fields, array $documents = []): ServiceDefinition
    {
        $svc = new ServiceDefinition();
        $svc->setRawAttributes(['schema' => json_encode([
            'fields'    => $fields,
            'documents' => $documents,
        ])]);
        $svc->syncOriginal();
        return $svc;
    }

    // ── JORD-1: regex safety ─────────────────────────────────────────

    public function test_valid_pattern_still_matches_after_hardening(): void
    {
        $svc = $this->makeService([[
            'id' => 'contract', 'label_ar' => 'العقد', 'type' => 'text', 'required' => true,
            'pattern' => '^\d{10}$', // 10-digit contract number
        ]]);
        $this->assertNull((new SchemaValidator($svc))->validateData(['contract' => '2628700029']));
    }

    public function test_valid_pattern_rejects_a_non_matching_value(): void
    {
        $svc = $this->makeService([[
            'id' => 'contract', 'label_ar' => 'العقد', 'type' => 'text', 'required' => true,
            'pattern' => '^\d{10}$',
        ]]);
        $errors = (new SchemaValidator($svc))->validateData(['contract' => 'abc']);
        $this->assertNotNull($errors);
        $this->assertStringContainsString('التنسيق', $errors['contract']);
    }

    public function test_uncompilable_pattern_fails_closed_instead_of_silently_accepting(): void
    {
        // Regression: previously a broken pattern threw a PHP warning
        // and preg_match returned false — which was indistinguishable
        // from a legitimate "no match", so a bad schema silently
        // accepted every value. Now we detect the compile failure and
        // report an explicit error keyed on the field.
        $svc = $this->makeService([[
            'id' => 'x', 'label_ar' => 'x', 'type' => 'text', 'required' => true,
            'pattern' => '(unclosed[', // uncompilable
        ]]);
        $errors = (new SchemaValidator($svc))->validateData(['x' => 'anything']);
        $this->assertNotNull($errors);
        $this->assertStringContainsString('نمط التحقق في المخطط غير صالح', $errors['x']);
    }

    public function test_delimiter_injection_via_pattern_is_neutralised(): void
    {
        // Prior behaviour: pattern='abc/i' + str_replace('/', '\/') would
        // turn into '/abc\/i/' — the trailing 'i' modifier would attach.
        // The new wrapper uses `~D` as the delimiter/modifier pair and
        // escapes stray tildes, so a modifier-injection attempt just
        // fails to match cleanly.
        $svc = $this->makeService([[
            'id' => 'x', 'label_ar' => 'x', 'type' => 'text', 'required' => true,
            'pattern' => 'ABC~i', // was: modifier-injection surface
        ]]);
        // Uppercase would only match if `i` sneaked in as a modifier —
        // it must NOT, so ABC does not match this literal pattern.
        $errors = (new SchemaValidator($svc))->validateData(['x' => 'abc']);
        $this->assertNotNull($errors, 'Case-insensitive match must NOT sneak through pattern injection');
    }

    public function test_non_string_pattern_is_ignored_rather_than_crashing(): void
    {
        // The pattern field is meant to be a string but a bad AI schema
        // might set it to an object/array. Prior code would concat a
        // stringified array into the delimiter and hit undefined behaviour.
        $svc = $this->makeService([[
            'id' => 'x', 'label_ar' => 'x', 'type' => 'text', 'required' => true,
            'pattern' => ['not', 'a', 'string'],
        ]]);
        // No fatal — the pattern block is skipped, value passes.
        $this->assertNull((new SchemaValidator($svc))->validateData(['x' => 'anything']));
    }

    // ── JORD-7: strict document validation ───────────────────────────

    public function test_missing_required_document_is_reported(): void
    {
        $svc = $this->makeService([], [[
            'id' => 'demolition_drawings', 'label_ar' => 'مخططات الهدم',
            'required' => true, 'accept' => ['pdf', 'dwg'], 'max_size_mb' => 50,
        ]]);
        $errors = (new SchemaValidator($svc))->validateDocuments([]);
        $this->assertSame(['demolition_drawings' => 'مخططات الهدم مطلوب.'], $errors);
    }

    public function test_uploaded_ids_are_string_strict_compared(): void
    {
        // Prior behaviour: in_array without strict=true treated the
        // schema-authored string id 'demolition_drawings' as equal to
        // (int) 0, so an accidental numeric-zero upload id passed.
        $svc = $this->makeService([], [[
            'id' => 'demolition_drawings', 'label_ar' => 'مخططات الهدم',
            'required' => true, 'accept' => ['pdf'], 'max_size_mb' => 5,
        ]]);
        $errors = (new SchemaValidator($svc))->validateDocuments([0]);
        // Missing → error still fired even though 0 was in the list.
        $this->assertNotNull($errors);
        $this->assertArrayHasKey('demolition_drawings', $errors);
    }

    public function test_matching_upload_clears_the_error(): void
    {
        $svc = $this->makeService([], [[
            'id' => 'demolition_drawings', 'label_ar' => 'مخططات الهدم',
            'required' => true, 'accept' => ['pdf'], 'max_size_mb' => 5,
        ]]);
        $this->assertNull((new SchemaValidator($svc))->validateDocuments(['demolition_drawings']));
    }

    public function test_malformed_document_entry_is_skipped_gracefully(): void
    {
        // Schema authoring error — no id on the document. Prior code
        // would try to write to $errors[null] which triggered a PHP
        // warning. The strict check silently skips instead.
        $svc = $this->makeService([], [[
            'label_ar' => 'missing id', 'required' => true, 'accept' => ['pdf'], 'max_size_mb' => 5,
        ]]);
        $this->assertNull((new SchemaValidator($svc))->validateDocuments([]));
    }
}
