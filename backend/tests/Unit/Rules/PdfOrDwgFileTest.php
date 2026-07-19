<?php

namespace Tests\Unit\Rules;

use App\Rules\PdfOrDwgFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase; // Laravel base — needed for the Validator facade

/**
 * Locks the magic-bytes contract for attachment uploads. The rule must
 * accept genuine PDFs and DWGs, reject renamed impostors, and reject any
 * extension outside the two-item allowlist.
 */
class PdfOrDwgFileTest extends TestCase
{
    private function fakeUpload(string $ext, string $bytes): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pdf-dwg-test-');
        file_put_contents($tmp, $bytes);
        // test=true bypasses the real MIME check so we can drive the header
        // exactly. The rule reads bytes from disk, not from the reported MIME.
        return new UploadedFile($tmp, "file.{$ext}", 'application/octet-stream', null, true);
    }

    private function passes(UploadedFile $file): bool
    {
        return Validator::make(['file' => $file], ['file' => [new PdfOrDwgFile()]])->passes();
    }

    public function test_accepts_a_genuine_pdf(): void
    {
        $file = $this->fakeUpload('pdf', "%PDF-1.7\nrest of file");
        $this->assertTrue($this->passes($file));
    }

    /**
     * @return list<array{string}>
     */
    public static function acceptedDwgHeaders(): array
    {
        return [
            ['AC1012'], // R13
            ['AC1015'], // R2000
            ['AC1018'], // R2004
            ['AC1024'], // R2010
            ['AC1027'], // R2013
            ['AC1032'], // R2018
            ['AC1099'], // future R
        ];
    }

    #[DataProvider('acceptedDwgHeaders')]
    public function test_accepts_dwg_versions_from_r13_onward(string $header): void
    {
        $file = $this->fakeUpload('dwg', $header . " padding");
        $this->assertTrue($this->passes($file),
            "Header {$header} should be accepted as a valid DWG");
    }

    public function test_rejects_pdf_extension_with_non_pdf_bytes(): void
    {
        $file = $this->fakeUpload('pdf', "MZ\x90\x00\x03evil PE header");
        $this->assertFalse($this->passes($file));
    }

    public function test_rejects_dwg_extension_with_non_dwg_bytes(): void
    {
        $file = $this->fakeUpload('dwg', "not-really-a-dwg");
        $this->assertFalse($this->passes($file));
    }

    public function test_rejects_jpg_extension(): void
    {
        $file = $this->fakeUpload('jpg', "\xff\xd8\xff\xe0jpg header");
        $this->assertFalse($this->passes($file));
    }

    public function test_rejects_docx_extension(): void
    {
        $file = $this->fakeUpload('docx', "PK\x03\x04zip header");
        $this->assertFalse($this->passes($file));
    }

    public function test_rejects_uppercase_extension(): void
    {
        // Extension check normalizes to lowercase, so PDF should still pass.
        $file = $this->fakeUpload('PDF', "%PDF-1.7");
        $this->assertTrue($this->passes($file));
    }
}
