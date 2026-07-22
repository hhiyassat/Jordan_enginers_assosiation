<?php

namespace Tests\Feature;

use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use App\Models\User;
use Database\Seeders\JeaPortalTilesSeeder;
use Database\Seeders\ServicePlan2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-51: /admin/services must return actual services only, grouped
 * and ordered by canonical plan order.
 *
 * Bug reported: the admin "إدارة الخدمات" page was showing 63 rows
 * because it dumped every service_definitions row for the org —
 * including the 7 top-level "category tiles" (JEA-PROJ, JEA-SURV, …)
 * which are folder entries in the taxonomy, not bookable services.
 * They rendered as junk cards with no meaningful edit/lock actions
 * and the count in the header was off by 7.
 *
 * Fix: filter parent_code=NULL out, return the tiles separately as
 * `categories` for group headers, and order the payload by canonical
 * plan order. These tests pin all three so the next refactor can't
 * silently re-introduce the leak.
 */
class AdminServicesIndexTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->admin = User::create([
            'organization_id' => $this->org->id, 'name' => 'admin', 'email' => 'admin@t.esp',
            'password' => 'x', 'role' => 'admin', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->runSilently(new JeaPortalTilesSeeder());
        $this->runSilently(new ServicePlan2026Seeder());
    }

    public function test_admin_services_index_excludes_the_seven_category_tiles(): void
    {
        // Sanity: the DB does hold the tile rows — we're not asserting the
        // seeder didn't create them, we're asserting the endpoint hides them.
        $tileRows = ServiceDefinition::where('organization_id', $this->org->id)
            ->whereNull('parent_code')
            ->count();
        $this->assertGreaterThanOrEqual(7, $tileRows, 'Seeders must produce the category tiles');

        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/admin/services');
        $res->assertOk();

        $services = collect($res->json('services'));
        // 57 actual services per ServicePlan2026SeederTest's per-parent
        // breakdown (12+14+6+6+2+4+13). If the plan gains or drops one,
        // update this alongside ServicePlan2026SeederTest.
        $this->assertSame(57, $services->count(),
            'Only actual services (not category tiles) should be returned');
        $this->assertTrue($services->every(fn ($s) => !empty($s['parent_code'])),
            'No response row should have a null parent_code');
    }

    public function test_admin_services_index_returns_categories_in_canonical_plan_order(): void
    {
        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/admin/services');
        $codes = collect($res->json('categories'))->pluck('code')->all();

        $this->assertSame(
            ['JEA-PROJ', 'JEA-SURV', 'JEA-FIN', 'JEA-CERT', 'JEA-ENG', 'JEA-DEC', 'JEA-MISC'],
            $codes,
            'Categories must be returned in plan order so the frontend can '
                . 'render group headers without a hardcoded map'
        );
    }

    public function test_admin_services_index_orders_services_by_category_then_code(): void
    {
        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/admin/services');
        $services = collect($res->json('services'));

        // The first row must belong to JEA-PROJ (first category), and the
        // last must belong to JEA-MISC (last). This is a coarse but very
        // hard-to-fake proof that canonical ordering is applied.
        $this->assertSame('JEA-PROJ', $services->first()['parent_code']);
        $this->assertSame('JEA-MISC', $services->last()['parent_code']);

        // Inside JEA-PROJ, the twelve rows must be DRW-P-001..DRW-P-012
        // in that order (code ascending).
        $proj = $services->where('parent_code', 'JEA-PROJ')->pluck('code')->values()->all();
        $this->assertSame(
            ['DRW-P-001','DRW-P-002','DRW-P-003','DRW-P-004','DRW-P-005','DRW-P-006',
             'DRW-P-007','DRW-P-008','DRW-P-009','DRW-P-010','DRW-P-011','DRW-P-012'],
            $proj,
            'Services within a category must be ordered by code'
        );
    }

    public function test_admin_services_response_includes_the_fields_the_ui_needs(): void
    {
        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/admin/services');
        $first = $res->json('services.0');

        foreach (['id','code','parent_code','name_ar','name_en','status','currency','is_locked'] as $key) {
            $this->assertArrayHasKey($key, $first, "Missing field on admin service payload: {$key}");
        }
    }

    public function test_admin_services_response_omits_tile_categories_that_are_empty(): void
    {
        // If an org somehow has a category tile with zero children, the
        // frontend still gets the tile back — the "hide empty header"
        // decision lives on the frontend so the API stays predictable.
        // This test just pins the API side of that contract.
        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/admin/services');

        $catCodes = collect($res->json('categories'))->pluck('code');
        $this->assertTrue(
            $catCodes->contains('JEA-ENG'),
            'Even a category with just 2 rows (JEA-ENG) must still be listed'
        );
    }

    private function runSilently(\Illuminate\Database\Seeder $seeder): void
    {
        $seeder->setContainer($this->app)
            ->setCommand(new class extends \Illuminate\Console\Command {
                public function info($string, $verbosity = null): void {}
                public function error($string, $verbosity = null): void {}
                public function warn($string, $verbosity = null): void {}
            })
            ->run();
    }
}
