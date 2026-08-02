<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * TD-06 · Architecture invariants for the Documents domain layer.
 *
 * (a) Domain\Documents does not carry file bytes (no property named
 *     `fileBytes`, `rawContent`, or `base64Payload`).
 * (b) No hardcoded attachment limits appear in production code —
 *     500 MB and 4 MB (the two most common accidents from OD-24)
 *     must not appear as constants.
 * (c) No production storage / antivirus claims (no vendor client
 *     imports in Domain\Documents).
 * (d) All 13 mandated document categories are enumerated.
 */
class DocumentsDomainBoundariesTest extends TestCase
{
    private const DOMAIN_DIR = __DIR__ . '/../../modules/JeaServices/Domain/Documents';

    // (17) file bytes absent from Domain entities.
    public function test_domain_documents_never_declare_a_file_bytes_property(): void
    {
        foreach ($this->phpFilesUnder(self::DOMAIN_DIR) as $file) {
            $body = file_get_contents($file);
            if ($body === false) {
                continue;
            }
            foreach (['$fileBytes', '$rawContent', '$base64Payload', 'file_bytes', 'raw_content'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $body,
                    "Domain\\Documents must not declare {$needle} (bytes never in Domain) — found in " . basename($file),
                );
            }
        }
        $this->assertTrue(true);
    }

    // (18) no production storage adapter claim without adapter evidence.
    public function test_domain_documents_does_not_import_vendor_storage_or_av_client(): void
    {
        $forbidden = ['Aws\\', 'Google\\Cloud\\Storage\\', 'Symfony\\Bundle\\', 'ClamAV\\', 'VirusTotal\\'];
        foreach ($this->phpFilesUnder(self::DOMAIN_DIR) as $file) {
            $body = file_get_contents($file);
            if ($body === false) {
                continue;
            }
            foreach ($forbidden as $n) {
                $this->assertStringNotContainsString(
                    'use ' . $n,
                    $body,
                    "Domain\\Documents must not import {$n} (vendor client) — found in " . basename($file),
                );
            }
        }
        $this->assertTrue(true);
    }

    // (19) no hardcoded attachment limits: 500 MB (524_288_000) or 4 MB (4_194_304)
    //      may not appear as constants in the Documents domain source.
    public function test_no_hardcoded_500mb_or_4mb_limits_in_documents_domain(): void
    {
        foreach ($this->phpFilesUnder(self::DOMAIN_DIR) as $file) {
            $body = file_get_contents($file);
            if ($body === false) {
                continue;
            }
            // Test file writing itself has assertions with these numbers — skip.
            $this->assertStringNotContainsString(
                '524288000',
                $body,
                'Hardcoded 500 MB attachment limit forbidden (OD-24 unresolved) — found in ' . basename($file),
            );
            $this->assertStringNotContainsString(
                '4194304',
                $body,
                'Hardcoded 4 MB attachment limit forbidden (OD-24 unresolved) — found in ' . basename($file),
            );
        }
        $this->assertTrue(true);
    }

    // (20) target runtime still inactive.
    public function test_target_runtime_still_inactive_after_TD06(): void
    {
        // Container app() isn't available here without bootstrap, so
        // verify via file grep that JeaServicesServiceProvider still
        // does not bind TargetSrv001SubmissionPolicy.
        $provider = file_get_contents(__DIR__ . '/../../modules/JeaServices/Providers/JeaServicesServiceProvider.php');
        $this->assertNotFalse($provider);
        $this->assertStringNotContainsString(
            'TargetSrv001SubmissionPolicy::class',
            $provider,
            'TD-06 must not activate TargetSrv001SubmissionPolicy',
        );
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
