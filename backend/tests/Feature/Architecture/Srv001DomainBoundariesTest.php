<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * TD-01A · Architecture boundary — the SRV-001 Domain layer must not
 * depend on Legacy pilot implementations or on raw Engine classes.
 *
 * Delegation to Legacy* is allowed ONLY through an adapter in
 * `Modules\JeaServices\Adapters\Srv001\` (outside the Domain
 * namespace). See JDG-TD01A-02.
 *
 * This test scans every file under the Domain namespace and asserts
 * (a) no import of a Governance\Srv001\Legacy* class, and (b) no
 * import of a raw Engine calculator (ExplorationRequirementMatrix,
 * WellsCountCalculator, NetDepthTable).
 *
 * If this test fails, either:
 *   - the boundary regressed (add a port + adapter instead), or
 *   - the target class has been approved and integrated (in which
 *     case update JDG-TD01A-02 + this test + the adapter register).
 */
class Srv001DomainBoundariesTest extends TestCase
{
    private const DOMAIN_ROOT = 'modules/JeaServices/Domain/Srv001';

    /** @var list<string> Fully-qualified prefixes forbidden in Domain\Srv001. */
    private const FORBIDDEN_PREFIXES = [
        'Modules\\JeaServices\\Governance\\Srv001\\Legacy',
        'Modules\\JeaServices\\Engine\\ExplorationRequirementMatrix',
        'Modules\\JeaServices\\Engine\\WellsCountCalculator',
        'Modules\\JeaServices\\Engine\\NetDepthTable',
        // Runtime guards / registry — Domain must not reach into runtime.
        'Modules\\JeaServices\\Engine\\Srv001Guard',
        'Modules\\JeaServices\\Engine\\ServiceSubmissionGuardRegistry',
    ];

    public function test_domain_srv001_files_do_not_import_forbidden_prefixes(): void
    {
        $root = base_path(self::DOMAIN_ROOT);
        $this->assertDirectoryExists($root, 'Domain root missing — expected ' . self::DOMAIN_ROOT);

        $violations = [];
        foreach ($this->allPhpFiles($root) as $file) {
            $contents = file_get_contents($file);
            $this->assertNotFalse($contents, "unable to read {$file}");

            foreach (self::FORBIDDEN_PREFIXES as $prefix) {
                // Only check inside `use ...;` import statements — comments +
                // docstrings that mention the class by name are allowed
                // (JDG-TD01A-02 documentation references would otherwise
                // false-positive).
                if (preg_match(
                    '/^\s*use\s+' . preg_quote($prefix, '/') . '/m',
                    $contents,
                )) {
                    $violations[] = sprintf(
                        '%s imports forbidden prefix `%s`',
                        substr($file, strlen(base_path()) + 1),
                        $prefix,
                    );
                }
            }
        }

        $this->assertSame([], $violations, "Domain\\Srv001 boundary violated:\n  - " . implode("\n  - ", $violations));
    }

    public function test_domain_srv001_calculators_declare_only_port_dependencies(): void
    {
        // Explicit whitelist for Calculators/ imports (namespace-level).
        // Adds a targeted safety net beyond the general boundary above.
        $calculatorsDir = base_path(self::DOMAIN_ROOT . '/Calculators');
        $this->assertDirectoryExists($calculatorsDir);

        $allowedPrefixes = [
            'Modules\\JeaServices\\Domain\\Srv001\\Contracts\\',
            'Modules\\JeaServices\\Governance\\ServiceCalculationPolicy',
            'Modules\\JeaServices\\Governance\\ServiceCalculationResult',
        ];

        foreach ($this->allPhpFiles($calculatorsDir) as $file) {
            $contents = file_get_contents($file) ?: '';
            preg_match_all('/^\s*use\s+([^\s;]+);/m', $contents, $m);

            foreach ($m[1] as $import) {
                $allowed = false;
                foreach ($allowedPrefixes as $prefix) {
                    if (str_starts_with($import, $prefix)) {
                        $allowed = true;
                        break;
                    }
                }
                $this->assertTrue(
                    $allowed,
                    sprintf(
                        '%s imports %s — Domain\\Srv001\\Calculators may only import from Domain\\Srv001\\Contracts\\* + ServiceCalculationPolicy/Result',
                        substr($file, strlen(base_path()) + 1),
                        $import,
                    ),
                );
            }
        }
    }

    /**
     * @return \Generator<int, string>
     */
    private function allPhpFiles(string $root): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $entry) {
            if ($entry->isFile() && $entry->getExtension() === 'php') {
                yield $entry->getPathname();
            }
        }
    }
}
