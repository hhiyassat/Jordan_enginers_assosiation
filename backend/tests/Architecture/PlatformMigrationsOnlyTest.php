<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Workstream 12 — data-ownership enforcement.
 *
 * Migrations under database/migrations/ are the PLATFORM migration
 * lane. They should touch only platform-neutral tables (users,
 * organizations, notifications, audit_logs, integration_cycles,
 * gsb_call_logs) — NOT domain-owned tables like applications,
 * service_definitions, projects, engineers, complaints, dues.
 *
 * Domain tables live under the owning module's Database/Migrations/
 * folder (moved in Workstreams 7 + 8A/B/C). If a new platform
 * migration references a JEA table this test fails, catching the
 * pattern that produced the orphan-duplicate migrations 8A/8B/8C
 * each had to clean up.
 *
 * Known allowlist: three legacy migrations add JEA-specific COLUMNS
 * to PLATFORM TABLES (users.annual_quota_m2, users.office_classification,
 * organizations.boost_flags). The correct architectural fix is to
 * move those columns onto module-owned tables (Engineer, OfficeCeiling)
 * — deferred to a future data-model refactor. Until then they're
 * documented allowlist entries so this test protects against NEW
 * violations without failing on known ones.
 */
class PlatformMigrationsOnlyTest extends TestCase
{
    private const MIGRATIONS_PATH = __DIR__ . '/../../database/migrations';

    /**
     * Tables owned by the platform. A platform migration may create,
     * alter, or drop any of these. Everything else = domain table.
     *
     * @var list<string>
     */
    private const PLATFORM_TABLES = [
        'users',
        'organizations',
        'notifications',
        'audit_logs',
        'integration_cycles',
        'gsb_call_logs',
        'personal_access_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
    ];

    /**
     * Known-issue exceptions. Each entry names a legacy migration that
     * writes a JEA-specific column onto a platform table. The correct
     * architectural fix (move the column to a module-owned table) is
     * deferred; the entry documents why the exception exists.
     *
     * @var array<string, string>
     */
    private const KNOWN_EXCEPTIONS = [
        '2026_07_17_152802_add_annual_quota_m2_to_users.php' =>
            'JEA per-engineer annual quota — should move to Engineer table (jea-projects).',
        '2026_07_21_000004_add_boost_flags_to_organizations_table.php' =>
            'JEA office quota-boost flags — should move to OfficeCeiling (jea-projects).',
        '2026_07_21_000011_add_office_classification_to_users_table.php' =>
            'JEA office classification A/B/C — should move to Engineer table (jea-projects).',
    ];

    /** @return array<string, list<string>> */
    private function scanPlatformMigrations(): array
    {
        $violations = [];
        $finder = (new Finder())
            ->files()
            ->in(self::MIGRATIONS_PATH)
            ->depth(0)
            ->name('*.php');

        foreach ($finder as $file) {
            $filename = $file->getFilename();
            if (isset(self::KNOWN_EXCEPTIONS[$filename])) {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());

            // Grep for table references: Schema::create/table/drop('...')
            // or Blueprint->table('...').
            preg_match_all(
                "/Schema::(?:create|table|drop|dropIfExists|rename)\(\s*['\"]([a-z_]+)['\"]/",
                $content,
                $m,
            );

            foreach ($m[1] ?? [] as $tableName) {
                if (!in_array($tableName, self::PLATFORM_TABLES, true)) {
                    $violations[$filename] = $violations[$filename] ?? [];
                    if (!in_array($tableName, $violations[$filename], true)) {
                        $violations[$filename][] = $tableName;
                    }
                }
            }
        }

        return $violations;
    }

    public function test_platform_migrations_touch_only_platform_tables(): void
    {
        $violations = $this->scanPlatformMigrations();

        $lines = [];
        foreach ($violations as $file => $tables) {
            $lines[] = "  {$file} → " . implode(', ', $tables);
        }
        $this->assertEmpty(
            $violations,
            "Platform migrations may only touch platform-owned tables. "
            . "Domain tables belong under the owning module's "
            . "Database/Migrations/ folder. Offenders:\n"
            . implode("\n", $lines),
        );
    }

    /**
     * Sanity check the allowlist itself so a fixed migration that gets
     * moved into a module doesn't leave a dead exception entry.
     */
    public function test_known_exception_files_still_exist(): void
    {
        $missing = [];
        foreach (array_keys(self::KNOWN_EXCEPTIONS) as $filename) {
            if (!file_exists(self::MIGRATIONS_PATH . '/' . $filename)) {
                $missing[] = $filename;
            }
        }
        $this->assertEmpty(
            $missing,
            "KNOWN_EXCEPTIONS references files that no longer exist under "
            . "database/migrations/. Remove them from the allowlist:\n  "
            . implode("\n  ", $missing),
        );
    }
}
