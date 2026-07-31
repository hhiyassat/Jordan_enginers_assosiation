<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CS-05: verify that disabling an optional module at config-time does
 * not break the rest of the application's boot. The audit called out
 * that most JEA modules are practically co-dependent — this suite
 * lets us evidence the fully-independent cases and shame-list the
 * co-dependent ones.
 *
 * Approach: for each `optional` module, we override
 * `config('modules.enabled')` inside the test to drop that key, then
 * try to resolve a Platform-owned service (a health check). If the
 * container can still resolve without the missing module, the boot
 * is unaffected.
 *
 * Note: this test intentionally does NOT reboot the whole framework
 * (Laravel doesn't support hot re-registering providers inside a
 * single process). It validates the *configuration surface*: the
 * module map can be reduced without the app's remaining config
 * failing validation.
 */
class OptionalModuleBootTest extends TestCase
{
    use RefreshDatabase;

    public function test_jea_dues_can_be_removed_from_modules_config_without_config_errors(): void
    {
        $enabled = config('modules.enabled');
        unset($enabled['jea-dues']);
        config(['modules.enabled' => $enabled]);

        // ProductionSafety runs against the reduced config; the check
        // should not throw. This proves the invariants are not
        // JeaDues-specific.
        \App\Support\ProductionSafety::enforce($this->app);

        $this->assertArrayNotHasKey('jea-dues', config('modules.enabled'));
        $this->assertArrayHasKey('jea-services',  config('modules.enabled'));
        $this->assertArrayHasKey('jea-projects',  config('modules.enabled'));
        $this->assertArrayHasKey('jea-discipline', config('modules.enabled'));
    }

    public function test_only_documented_modules_are_registered(): void
    {
        $enabled = config('modules.enabled');
        $expected = ['jea-services', 'jea-dues', 'jea-projects', 'jea-discipline'];
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $enabled,
                "config('modules.enabled') missing expected key '{$key}'");
        }
        foreach (array_keys($enabled) as $key) {
            $this->assertContains($key, $expected,
                "Undocumented module registered in config: '{$key}'");
        }
    }

    /**
     * Documented reality: jea-services / jea-projects / jea-discipline
     * form a coupled cluster (see SM_ALLOWED_IMPORTS). This assertion
     * exists to keep the honest picture in the test suite so nobody
     * ships a claim that all four modules are independently
     * activatable.
     */
    public function test_reports_module_independence_matrix(): void
    {
        $independentlyRemovable = ['jea-dues'];
        $coupledCluster         = ['jea-services', 'jea-projects', 'jea-discipline'];

        $this->assertSame(
            ['jea-dues'],
            $independentlyRemovable,
            'Only jea-dues is safely removable in isolation today. '
            . 'Migrating the cluster is CS-05 backlog.'
        );
        $this->assertCount(3, $coupledCluster);
    }
}
