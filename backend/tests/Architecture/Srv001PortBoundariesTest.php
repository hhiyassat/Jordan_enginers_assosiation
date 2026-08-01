<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * TD-05 · Architecture invariants for the SRV-001 port layer.
 *
 * These are grep-based tests kept intentionally simple — they
 * enforce the highest-value invariants without needing a full AST
 * parser:
 *
 * (a) Domain\Srv001 does not import any Illuminate\Http class.
 * (b) Domain\Srv001 does not import any Eloquent model.
 * (c) Domain\Srv001 does not import a vendor HTTP client class.
 * (d) No generic engine adds a hard-coded `SRV-001` conditional
 *     (routing is service-code-agnostic via registries).
 * (e) No production Oracle / DLS / BURA URL is present in code.
 */
class Srv001PortBoundariesTest extends TestCase
{
    private const DOMAIN_DIR = __DIR__ . '/../../modules/JeaServices/Domain/Srv001';

    public function test_domain_layer_does_not_import_illuminate_http(): void
    {
        $this->assertNoImportContains('Illuminate\\Http\\', self::DOMAIN_DIR);
    }

    public function test_domain_layer_does_not_import_eloquent_models(): void
    {
        // Two exceptions are documented as domain-crossing entities:
        //   • Application — the entity every submission policy receives
        //   • RuleVersion — Srv001CalculatorOutcomeClassifier (TD-04) resolves
        //     the versioned rule's approval status; the entity is domain-crossing.
        $allowedCrossings = ['Application', 'RuleVersion'];
        foreach ($this->phpFilesUnder(self::DOMAIN_DIR) as $file) {
            $body  = file_get_contents($file);
            $this->assertNotFalse($body);
            $lines = preg_split('~\R~', $body) ?: [];
            foreach ($lines as $lineNo => $line) {
                if (! str_starts_with(trim($line), 'use ')) {
                    continue;
                }
                if (! str_contains($line, 'use Modules\\JeaServices\\Models\\')) {
                    continue;
                }
                $isAllowed = false;
                foreach ($allowedCrossings as $c) {
                    if (str_contains($line, "use Modules\\JeaServices\\Models\\{$c}")) {
                        $isAllowed = true;
                        break;
                    }
                }
                if (! $isAllowed) {
                    $this->fail(basename($file) . ':' . ($lineNo + 1) . " imports a Model that is not on the allowed crossings list");
                }
            }
        }
        $this->assertTrue(true);
    }

    public function test_domain_layer_does_not_import_vendor_http_client(): void
    {
        $this->assertNoImportContains('GuzzleHttp\\', self::DOMAIN_DIR);
    }

    public function test_generic_controller_does_not_hardcode_srv001_after_TD05(): void
    {
        $controller = __DIR__ . '/../../modules/JeaServices/Http/Controllers/ApplicationController.php';
        $src = file_get_contents($controller);
        $this->assertNotFalse($src);
        // The controller MAY reference SRV-001 only via the registry
        // lookup (already exercised in TD-03 tests). It must not
        // introduce a NEW hardcoded conditional; ensure the count of
        // `=== 'SRV-001'` checks is zero.
        $matches = preg_match_all("~=== ['\"]SRV-001['\"]~", $src);
        $this->assertSame(0, $matches,
            'TD-05 must not introduce hardcoded SRV-001 conditionals into the generic controller');
    }

    public function test_no_production_external_urls_in_source(): void
    {
        // Guard against accidental copy of a production endpoint into
        // source. Search production-shaped substrings that would only
        // appear on real integrations.
        $badPatterns = ['oracle.gov.jo', 'dls.gov.jo', 'bura.gov.jo', 'jeaesg.jo/api'];
        $dir = __DIR__ . '/../../modules/JeaServices';
        $files = $this->phpFilesUnder($dir);
        foreach ($files as $file) {
            $body = file_get_contents($file);
            if ($body === false) {
                continue;
            }
            foreach ($badPatterns as $p) {
                $this->assertStringNotContainsString(
                    $p,
                    $body,
                    "Production-shaped URL {$p} must not appear in source (found in " . basename($file) . ')',
                );
            }
        }
    }

    private function assertNoImportContains(string $needle, string $dir, ?string $ignoreSubstring = null): void
    {
        foreach ($this->phpFilesUnder($dir) as $file) {
            $body = file_get_contents($file);
            $this->assertNotFalse($body);
            $lines = preg_split('~\R~', $body) ?: [];
            foreach ($lines as $lineNo => $line) {
                if (! str_starts_with(trim($line), 'use ')) {
                    continue;
                }
                if (str_contains($line, $needle)) {
                    if ($ignoreSubstring !== null && str_contains($line, $ignoreSubstring)) {
                        continue;
                    }
                    $this->fail(
                        basename($file) . ':' . ($lineNo + 1) . " imports {$needle} — violates Domain\\Srv001 boundary",
                    );
                }
            }
        }
        $this->assertTrue(true); // no violations
    }

    /** @return list<string> */
    private function phpFilesUnder(string $dir): array
    {
        $out = [];
        if (! is_dir($dir)) {
            return $out;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                $out[] = $f->getPathname();
            }
        }
        return $out;
    }
}
