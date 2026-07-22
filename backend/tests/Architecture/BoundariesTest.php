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
     * Post-W5 health: no platform controller should exceed 500 lines.
     * Workstream 5 split ApplicationController + AdminController; no
     * new controller should breach without justification.
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
        $this->assertEmpty(
            $oversize,
            "Platform controllers must stay under {$threshold} lines. Offenders:\n"
            . $this->pretty($oversize),
        );
    }

    /**
     * Workstream 15 — dep-direction enforcement (backend).
     *
     * Platform code (app/) must not import from Modules\, Plugins\,
     * or Integrations\. If a platform file needs a domain capability,
     * invert: expose a contract from the platform, have the module /
     * plugin / integration implement it.
     *
     * Known exceptions live in the allowlist below with a documented
     * reason. Each entry has a clear path to its retirement — either
     * the file gets split (AdminDashboardController's RED status) or
     * the PC→SM read gets replaced with a contract read.
     *
     * @var array<string, string>
     */
    private const PC_ALLOWLIST = [
        'Http/Controllers/Api/AdminDashboardController.php' =>
            'RED: reads Modules\JeaServices for org-wide app list + '
            . 'certificate count. Splits into a platform admin shell + '
            . 'a jea-services "recent apps" widget in a future WS.',
        'Providers/AppServiceProvider.php' =>
            'Composition root binds Integrations\Gsb\* into the container. '
            . 'The wiring belongs at the composition boundary; a future WS '
            . 'can move the bindings into GsbServiceProvider itself.',
        'Models/User.php' =>
            'User has JEA relations (OfficeCoalition, OfficeCoalitionMember) '
            . 'from JORD-77. Needs a User contract that jea-projects can '
            . 'extend without the platform User importing it.',
        'Http/Concerns/RespondsWithLockedService.php' =>
            'The 423 locked-service response reads Modules\JeaServices\Models\ServiceDefinition. '
            . 'Trait should move to modules/JeaServices/Http/Concerns/ since '
            . 'the "locked" concept IS jea-services.',
        'Services/Payment/MockPaymentGateway.php' =>
            'Payment abstraction takes Modules\JeaServices\Models\Application directly. '
            . 'Should invert: Application implements a PaymentTarget contract; '
            . 'gateway takes the contract.',
        'Services/Payment/PaymentGateway.php' =>
            'Same as MockPaymentGateway — takes Application concrete instead of '
            . 'a PaymentTarget contract. Follow-up contract-inversion refactor.',
        'Services/Notifications/NotificationService.php' =>
            'Notification service has Application knowledge baked in. Should '
            . 'accept a domain-neutral Notifiable + template payload; each '
            . 'module builds its own payload.',
    ];

    public function test_platform_does_not_import_service_modules(): void
    {
        $violations = [];
        $finder = (new Finder())
            ->files()
            ->in(self::APP_PATH)
            ->name('*.php');

        foreach ($finder as $file) {
            $relative = str_replace(self::APP_PATH . '/', '', $file->getPathname());
            if (isset(self::PC_ALLOWLIST[$relative])) {
                continue;
            }
            $content = (string) file_get_contents($file->getPathname());
            preg_match_all(
                '/^use\s+((?:Modules|Plugins|Integrations)\\\\[^;\s]+)/m',
                $content,
                $m,
            );
            foreach (($m[1] ?? []) as $import) {
                $violations[$relative] = $violations[$relative] ?? [];
                $violations[$relative][] = $import;
            }
        }

        $this->assertEmpty(
            $violations,
            "Platform code (app/) must not import from Modules\\, "
            . "Plugins\\, or Integrations\\. Invert the dependency "
            . "(contract in platform, impl in module) or add the file "
            . "to PC_ALLOWLIST with a documented retirement path.\n"
            . $this->pretty($violations),
        );
    }

    /**
     * Sanity check the allowlist: an allowlisted file that no longer
     * exists is a stale entry — remove it.
     */
    public function test_pc_allowlist_files_still_exist(): void
    {
        $missing = [];
        foreach (array_keys(self::PC_ALLOWLIST) as $relative) {
            if (!file_exists(self::APP_PATH . '/' . $relative)) {
                $missing[] = $relative;
            }
        }
        $this->assertEmpty(
            $missing,
            "PC_ALLOWLIST references files that no longer exist. "
            . "Remove them from the allowlist:\n  "
            . implode("\n  ", $missing),
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
