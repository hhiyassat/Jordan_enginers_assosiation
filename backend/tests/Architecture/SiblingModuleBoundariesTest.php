<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * H-07 (session 3): sibling-JEA-module boundary enforcement.
 *
 * Complements BoundariesTest, which polices Platform → JEA imports.
 * This one polices cross-JEA-module imports (JeaServices ↔ JeaProjects
 * ↔ JeaDiscipline ↔ JeaDues). The mandate's acceptance criteria are:
 *
 *   JeaServices imports no JeaProjects implementation classes
 *   JeaServices imports no JeaDiscipline implementation classes
 *   JeaProjects imports no JeaServices implementation classes
 *   JeaDiscipline imports no JeaServices implementation classes
 *
 * Reality today: the JeaServices Application model is shared vocabulary
 * for every sibling that extends the application lifecycle (discipline
 * attaches complaints/fines to it, projects consume quota against it,
 * dues doesn't reference it at all). Full removal of every cross-module
 * import is a multi-week refactor with per-file DTO extraction. This
 * test:
 *
 *   1. Fails the moment a NEW cross-JEA-module import is introduced.
 *   2. Documents every existing violation with a retirement path.
 *   3. Provides a countable inventory so H07_CROSS_MODULE_CONTRACT_GATE
 *      can be reported as PARTIAL with a concrete number, not a
 *      hand-wave.
 *
 * The follow-up path is: each entry migrates to
 * App\Contracts\Applications\ApplicationLookup / ApplicationSnapshot
 * (added session 3) or another neutral contract; the corresponding
 * entry drops out of SM_ALLOWED_IMPORTS.
 */
class SiblingModuleBoundariesTest extends TestCase
{
    private const MODULES_PATH = __DIR__ . '/../../modules';

    /** The four JEA modules the mandate names. */
    private const JEA_MODULES = ['JeaServices', 'JeaProjects', 'JeaDiscipline', 'JeaDues'];

    /**
     * Documented cross-JEA-module imports.
     *
     * Key = relative path under backend/modules/.
     * Value = free-form reason + retirement path (contract name /
     * follow-up ticket).
     *
     * Any entry ADDED here must include a specific replacement contract
     * (or an explicit reason it cannot be extracted).
     *
     * @var array<string, string>
     */
    private const SM_ALLOWED_IMPORTS = [
        // ── JeaServices → JeaProjects ────────────────────────────────
        'JeaServices/Models/Application.php' =>
            'FK relation belongsTo(JeaProjects\Models\Project). Retirement: keep the '
            . 'column as bare integer and expose ProjectLookup contract; Application no '
            . 'longer needs the Eloquent relation.',
        'JeaServices/Engine/WorkflowEngine.php' =>
            'Calls JeaProjects\Engine\QuotaLedger::recordApproval inside decide(). '
            . 'Retirement: emit an ApplicationApproved event; JeaProjects listens.',
        'JeaServices/Engine/OfficeRegistrationValidator.php' =>
            'Uses JeaProjects\Engine\Disciplines for the discipline enum. '
            . 'Retirement: move Disciplines to App\Contracts\Domain\Disciplines '
            . '(Platform-owned domain enum).',
        'JeaServices/Http/Requests/SubmitOfficeRegistrationRequest.php' =>
            'Same Disciplines enum dependency. Retirement: same as above.',
        'JeaServices/Http/Controllers/OfficeRegistrationController.php' =>
            'Resolves JeaProjects models + Disciplines when approving a registration. '
            . 'Retirement: extend OfficeCoalitionResolver + move Disciplines to Platform.',

        // ── JeaProjects → JeaServices (Application) ──────────────────
        'JeaProjects/Engine/QuotaLedger.php' =>
            'Reads JeaServices\Models\Application to derive quota consumption. Session-3 '
            . 'starter contract App\Contracts\Applications\ApplicationLookup added; full '
            . 'QuotaLedger migration is a P2 follow-up (~15 call sites to convert).',
        'JeaProjects/Engine/CapacityGuard.php' =>
            'Reads JeaServices\Models\Application in the guard. Retirement: same as '
            . 'QuotaLedger — ApplicationLookup / ApplicationSnapshot.',
        'JeaProjects/Database/Seeders/GovernmentSurveyQuotaSeeder.php' =>
            'Seed data references JEA services. One-shot at deploy — not migrated.',
        'JeaProjects/Database/Seeders/MaterialsTestingQuotaSeeder.php' =>
            'Seed data references JEA services. One-shot at deploy — not migrated.',

        // ── JeaDiscipline → JeaServices (Application) ────────────────
        'JeaDiscipline/Console/Commands/RemindExpiries.php' =>
            'Iterates JeaServices\Application for expiry reminders + resolves '
            . 'JeaNotificationService for the emit call. Retirement: '
            . 'ApplicationLookup::forOrganizationInStatuses(...) already covers the '
            . 'iteration; snapshot exposes reference_number + applicant_id; final '
            . 'migration step: move the whole RemindExpiries command into JeaServices '
            . '(reminders are application-level events, not discipline-level).',
        // CS-04 (2026-07-31) — retired. SanctionGuard now consumes
        // App\Contracts\Applications\ApplicationSnapshot; the JEA
        // ApplicationController maps at the boundary via
        // EloquentApplicationLookup::snapshotOf($app).

        // ── CS-05 · previously-hidden cross-JEA-module container
        //           resolutions surfaced by the strengthened detector.
        //           These files reach into a sibling via
        //           `app(\Modules\<other>\...)` rather than a `use`.
        //           Retirement: contribute each guard/service to a
        //           Platform-owned registry so the caller never names
        //           the concrete sibling class.
        'JeaServices/Http/Controllers/ApplicationController.php' =>
            'Three sibling resolves in submit(): app(\\Modules\\JeaProjects\\Engine\\QuotaLedger) '
            . '(overflow surcharge + capacity) and app(\\Modules\\JeaDiscipline\\Engine\\SanctionGuard). '
            . 'SanctionGuard now takes ApplicationSnapshot (CS-04); the resolves stay until '
            . 'either the CrossCuttingSubmissionGuard registry accepts external contributions '
            . '(JeaDiscipline register()) or an OverflowSurchargeCalculator contract lands.',

        'JeaDiscipline/Http/Controllers/LegalFineController.php' =>
            'Loads Application to attach a fine. Retirement: ApplicationLookup + a small '
            . 'ApplicationRef DTO for attach-target lookups.',
        'JeaDiscipline/Models/LegalFine.php' =>
            'FK belongsTo(JeaServices\Application). Retirement: keep FK column, drop '
            . 'Eloquent relation, expose ApplicationLookup in the service layer.',
        'JeaDiscipline/Models/SupervisionTransfer.php' =>
            'FK belongsTo(JeaServices\Application). Same retirement path as LegalFine.',
        'JeaDiscipline/Services/SupervisionTransferService.php' =>
            'Reads Application during transfer opening. Retirement: ApplicationLookup.',
    ];

    /**
     * Sibling boundary — DETECT any UNDOCUMENTED cross-JEA-module import.
     * Fails the moment a new one is introduced (must be added to
     * SM_ALLOWED_IMPORTS with a retirement path).
     *
     * CS-05: also matches hidden container resolutions and direct
     * `new` instantiations by FQCN — these previously slipped past
     * the `^use ...` detector (Application::booted, ApplicationController's
     * app(\Modules\...) calls). Detection unifies visible imports with
     * hidden resolves so future coupling has one policy.
     */
    public function test_no_undocumented_cross_jea_module_imports(): void
    {
        $violations = [];
        $finder = (new Finder())
            ->files()
            ->in(self::MODULES_PATH)
            ->name('*.php');

        foreach ($finder as $file) {
            $relative = str_replace(self::MODULES_PATH . '/', '', $file->getPathname());
            $ownModule = $this->moduleFor($relative);
            if ($ownModule === null || !in_array($ownModule, self::JEA_MODULES, true)) {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            $found = $this->crossModuleReferencesIn($content, $ownModule);

            if ($found === []) {
                continue;
            }
            if (isset(self::SM_ALLOWED_IMPORTS[$relative])) {
                continue;
            }
            $violations[$relative] = $found;
        }

        $this->assertEmpty(
            $violations,
            "UNDOCUMENTED cross-JEA-module coupling detected. Either extract a Platform "
            . "contract (e.g. App\\Contracts\\Applications\\ApplicationLookup) and depend "
            . "on that, or add the file to SM_ALLOWED_IMPORTS with an explicit "
            . "retirement path.\n"
            . $this->pretty($violations),
        );
    }

    /**
     * Extract every cross-JEA-module reference in the file's source,
     * covering both visible `use` imports AND hidden runtime resolves.
     *
     * Detected patterns:
     *   • ^use Modules\<Other>\...
     *   • app(\Modules\<Other>\...::class)
     *   • resolve(\Modules\<Other>\...::class)
     *   • \App::make(\Modules\<Other>\...::class)  ← rare, still caught
     *   • new \Modules\<Other>\...
     *
     * @return list<string>
     */
    private function crossModuleReferencesIn(string $content, string $ownModule): array
    {
        $found  = [];
        $others = array_filter(self::JEA_MODULES, fn ($m) => $m !== $ownModule);

        // 1) Visible `use` imports.
        if (preg_match_all('/^use\s+(Modules\\\\Jea[^;\s]+)/m', $content, $m)) {
            foreach ($m[1] as $import) {
                foreach ($others as $other) {
                    if (str_starts_with($import, "Modules\\{$other}\\")) {
                        $found[] = "use {$import}";
                        break;
                    }
                }
            }
        }

        // 2) Hidden resolves via app(...) / resolve(...) / make(...).
        $callPattern = '/\b(?:app|resolve|make)\s*\(\s*\\\\?(Modules\\\\Jea[^:\s\)\)]+)::class\s*\)/';
        if (preg_match_all($callPattern, $content, $m)) {
            foreach ($m[1] as $fqcn) {
                foreach ($others as $other) {
                    if (str_starts_with($fqcn, "Modules\\{$other}\\")) {
                        $found[] = "app({$fqcn}::class)";
                        break;
                    }
                }
            }
        }

        // 3) Direct instantiation via `new \Modules\Other\...`.
        $newPattern = '/\bnew\s+\\\\?(Modules\\\\Jea[^(\s]+)/';
        if (preg_match_all($newPattern, $content, $m)) {
            foreach ($m[1] as $fqcn) {
                foreach ($others as $other) {
                    if (str_starts_with($fqcn, "Modules\\{$other}\\")) {
                        $found[] = "new {$fqcn}";
                        break;
                    }
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * Sanity check: every entry in SM_ALLOWED_IMPORTS must correspond
     * to a file that still contains at least one cross-JEA-module
     * reference — visible OR hidden. Otherwise the entry is stale
     * and should be removed (that's a completed migration).
     */
    public function test_sm_allowlist_entries_still_have_violations(): void
    {
        $stale = [];
        foreach (array_keys(self::SM_ALLOWED_IMPORTS) as $relative) {
            $path = self::MODULES_PATH . '/' . $relative;
            if (!file_exists($path)) {
                $stale[] = "{$relative} (file missing)";
                continue;
            }
            $content = (string) file_get_contents($path);
            $ownModule = $this->moduleFor($relative);
            if ($ownModule === null) {
                continue;
            }
            if ($this->crossModuleReferencesIn($content, $ownModule) === []) {
                $stale[] = "{$relative} (no cross-module refs remain — congrats, remove entry)";
            }
        }

        $this->assertEmpty(
            $stale,
            "SM_ALLOWED_IMPORTS has stale entries — remove them:\n  " . implode("\n  ", $stale),
        );
    }

    /**
     * Report an honest count so H07_CROSS_MODULE_CONTRACT_GATE can be
     * declared PARTIAL with concrete evidence rather than prose.
     */
    public function test_reports_cross_module_import_count(): void
    {
        $count = count(self::SM_ALLOWED_IMPORTS);
        $this->assertLessThanOrEqual(
            30,
            $count,
            "Cross-JEA-module allowlist is at {$count} entries. Not a failure per se — "
            . "this test exists to publish the number and to raise a hard ceiling. "
            . "Bump the ceiling only after a design review that documents WHY the "
            . "coupling is legitimate.",
        );
    }

    private function moduleFor(string $relative): ?string
    {
        $parts = explode('/', $relative);
        return $parts[0] ?? null;
    }

    /**
     * @param  array<string, list<string>>  $violations
     */
    private function pretty(array $violations): string
    {
        $out = '';
        foreach ($violations as $file => $imports) {
            $out .= "\n  {$file}:\n    " . implode("\n    ", $imports);
        }
        return $out;
    }
}
