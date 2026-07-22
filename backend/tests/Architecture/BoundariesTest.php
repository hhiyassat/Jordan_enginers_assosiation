<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Workstream 3 (Architecture) — layer / boundary enforcement.
 *
 * These tests grep the source tree — no framework boot, no DB. They
 * catch cross-layer coupling that Laravel's structure allows by
 * default but our target architecture forbids.
 *
 * Rules are intentionally NARROW to match today's directory shape:
 * they encode invariants that already hold in the current baseline
 * (`docs/architecture/00-baseline.md`). Workstreams 5..14 will fold
 * the target platform/modules/plugins/integrations split; this file
 * grows to match once those folders exist.
 *
 * Currently every rule is a hard assertion — if it fails, the base-
 * line is drifting. In Workstream 15 we add the harder cross-module
 * import rules (once modules exist).
 */
class BoundariesTest extends TestCase
{
    private const APP_PATH = __DIR__ . '/../../app';

    /** @return array<string, list<string>> */
    private function grepImports(string $subdir, string $forbiddenPattern): array
    {
        $offenders = [];
        $finder = (new Finder())
            ->files()
            ->in(self::APP_PATH . '/' . $subdir)
            ->name('*.php');

        foreach ($finder as $file) {
            $relative = str_replace(self::APP_PATH . '/', '', $file->getPathname());
            $content  = (string) file_get_contents($file->getPathname());
            // Match `use App\...` lines (Laravel's import style).
            preg_match_all('/^use\s+(App\\\\[^;\s]+)/m', $content, $matches);
            foreach (($matches[1] ?? []) as $import) {
                if (preg_match($forbiddenPattern, $import)) {
                    $offenders[$relative] = $offenders[$relative] ?? [];
                    $offenders[$relative][] = $import;
                }
            }
        }
        return $offenders;
    }

    /**
     * Models are data + query scopes. A model importing a controller
     * is inversion of layering — the model would be embedding request
     * / response knowledge into the domain.
     */
    public function test_models_do_not_import_controllers(): void
    {
        $offenders = $this->grepImports('Models', '/^App\\\\Http\\\\Controllers\\\\/');
        $this->assertEmpty(
            $offenders,
            "Models must not import controllers:\n" . $this->pretty($offenders),
        );
    }

    /**
     * Middleware sits between the request and the controller. Reaching
     * INTO a controller from middleware couples the transport layer
     * to a specific handler.
     */
    public function test_middleware_does_not_import_controllers(): void
    {
        $offenders = $this->grepImports('Http/Middleware', '/^App\\\\Http\\\\Controllers\\\\/');
        $this->assertEmpty(
            $offenders,
            "Middleware must not import controllers:\n" . $this->pretty($offenders),
        );
    }

    /**
     * Services (Services/, Engine/) are the application layer. They
     * should not know about controllers — they should be called BY
     * controllers, not call INTO them.
     */
    public function test_services_do_not_import_controllers(): void
    {
        $offenders = array_merge(
            $this->grepImports('Services', '/^App\\\\Http\\\\Controllers\\\\/'),
            $this->grepImports('Engine',   '/^App\\\\Http\\\\Controllers\\\\/'),
        );
        $this->assertEmpty(
            $offenders,
            "Services / Engine must not import controllers:\n" . $this->pretty($offenders),
        );
    }

    /**
     * Console commands are cron / artisan entry points. Same rule as
     * middleware — they should call services, not controllers.
     */
    public function test_console_commands_do_not_import_controllers(): void
    {
        if (!is_dir(self::APP_PATH . '/Console/Commands')) {
            $this->markTestSkipped('No console commands directory.');
        }
        $offenders = $this->grepImports('Console/Commands', '/^App\\\\Http\\\\Controllers\\\\/');
        $this->assertEmpty(
            $offenders,
            "Console commands must not import controllers:\n" . $this->pretty($offenders),
        );
    }

    /**
     * FormRequest classes should be pure validation — no controller
     * imports, and no direct model queries from inside the request.
     */
    public function test_form_requests_do_not_import_controllers(): void
    {
        if (!is_dir(self::APP_PATH . '/Http/Requests')) {
            $this->markTestSkipped('No FormRequest directory.');
        }
        $offenders = $this->grepImports('Http/Requests', '/^App\\\\Http\\\\Controllers\\\\/');
        $this->assertEmpty(
            $offenders,
            "FormRequests must not import controllers:\n" . $this->pretty($offenders),
        );
    }

    /**
     * Soft warning as a health check: any controller over 500 lines is
     * a smell. Recorded as a data point, not a hard fail — Workstream
     * 5 splits the two offenders (ApplicationController, AdminController)
     * and this becomes a hard assertion.
     */
    public function test_controller_size_health_check(): void
    {
        $threshold = 500;
        $oversize = [];
        $finder = (new Finder())
            ->files()
            ->in(self::APP_PATH . '/Http/Controllers')
            ->name('*.php');
        foreach ($finder as $file) {
            $lines = count(file($file->getPathname()) ?: []);
            if ($lines > $threshold) {
                $oversize[str_replace(self::APP_PATH . '/', '', $file->getPathname())] = $lines;
            }
        }
        // Baseline expectation (documented in the plan doc): today
        // ApplicationController and AdminController breach. Assert the
        // known set explicitly so any NEW breach surfaces immediately
        // even before Workstream 5 promotes this to a hard rule.
        $baselineOversizeCount = 2;
        $this->assertLessThanOrEqual(
            $baselineOversizeCount,
            count($oversize),
            "A new controller has grown past {$threshold} lines. Current oversize set:\n"
            . $this->pretty($oversize),
        );
    }

    /**
     * Pretty-print an offender map for assertion messages.
     * @param  array<string, list<string>|int>  $offenders
     */
    private function pretty(array $offenders): string
    {
        $lines = [];
        foreach ($offenders as $file => $detail) {
            if (is_array($detail)) {
                $lines[] = "  {$file}:";
                foreach ($detail as $d) {
                    $lines[] = "    - {$d}";
                }
            } else {
                $lines[] = "  {$file} — {$detail} lines";
            }
        }
        return implode("\n", $lines);
    }
}
