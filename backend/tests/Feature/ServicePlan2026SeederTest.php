<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use App\Models\User;
use Database\Seeders\JeaPortalTilesSeeder;
use Database\Seeders\ServicePlan2026Seeder;
use Database\Seeders\SurveyWorkflowsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The 2026 services plan is the single source of truth for the JEA
 * portal catalogue. These tests pin the phase-count invariants (which
 * the plan footer commits to publicly: 20/13/12/4/9) and the shape of
 * the API response so a future edit to the seeder can't silently drift.
 */
class ServicePlan2026SeederTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
    }

    public function test_seeder_produces_phase_counts_matching_the_plan_footer(): void
    {
        $this->runSeeder();

        $counts = ServiceDefinition::where('organization_id', $this->org->id)
            ->selectRaw('phase, COUNT(*) as c')
            ->groupBy('phase')
            ->pluck('c', 'phase')
            ->toArray();

        // Footer of service-plan-payment.pdf: 20 / 13 / 12 / 4 / 9 → total 58.
        $this->assertSame(20, $counts[1] ?? 0, 'Phase 1 must match plan');
        $this->assertSame(13, $counts[2] ?? 0, 'Phase 2 must match plan');
        $this->assertSame(12, $counts[3] ?? 0, 'Phase 3 must match plan');
        $this->assertSame(4,  $counts[4] ?? 0, 'Phase 4 must match plan');
        $this->assertSame(9,  $counts[5] ?? 0, 'Phase 5 must match plan');
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->runSeeder();
        $countAfterFirst = ServiceDefinition::where('organization_id', $this->org->id)->count();

        $this->runSeeder();
        $countAfterSecond = ServiceDefinition::where('organization_id', $this->org->id)->count();

        $this->assertSame($countAfterFirst, $countAfterSecond,
            'Re-running the seeder must not duplicate rows');
    }

    public function test_seeder_adds_the_new_survey_top_level_tile(): void
    {
        $this->runSeeder();

        $survTile = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'JEA-SURV')
            ->first();

        $this->assertNotNull($survTile);
        $this->assertNull($survTile->parent_code);
        $this->assertSame('استطلاع الموقع', $survTile->name_ar);
    }

    public function test_services_are_attached_to_the_expected_parent_tiles(): void
    {
        $this->runSeeder();

        $expected = [
            'JEA-PROJ' => 12,
            'JEA-SURV' => 14, // 13 numbered + شهادة الكشف الحسي which sits under survey per the plan
            'JEA-FIN'  => 6,
            'JEA-CERT' => 6,
            'JEA-ENG'  => 2,
            'JEA-DEC'  => 4,
            'JEA-MISC' => 14,
        ];

        foreach ($expected as $parent => $count) {
            $actual = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('parent_code', $parent)
                ->count();
            $this->assertSame($count, $actual,
                "Expected {$count} services under {$parent}, got {$actual}");
        }
    }

    public function test_every_seeded_service_has_bilingual_descriptions(): void
    {
        // Full seeding pipeline: tiles → plan → survey workflows. This is the
        // production combination — SurveyWorkflowsSeeder writes descriptions
        // for the 8 flowchart-backed SRV services on top of ServicePlan2026.
        $this->runSilently(new JeaPortalTilesSeeder());
        $this->runSeeder();
        $this->runSilently(new SurveyWorkflowsSeeder());

        $missing = ServiceDefinition::where('organization_id', $this->org->id)
            ->where(function ($q) {
                $q->whereNull('description_ar')->orWhere('description_ar', '');
                $q->orWhereNull('description_en')->orWhere('description_en', '');
            })
            ->pluck('code')
            ->all();

        $this->assertSame([], $missing, 'Every seeded service must have a bilingual description');
    }

    public function test_non_survey_service_descriptions_are_paragraph_length(): void
    {
        $this->runSilently(new JeaPortalTilesSeeder());
        $this->runSeeder();

        // 50 chars is a fair floor for non-survey services (survey services
        // get flowchart-derived paragraphs > 200 chars from a separate seeder).
        $tooShort = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'not like', 'SRV-%')
            ->get(['code', 'description_ar', 'description_en'])
            ->filter(fn($s) =>
                mb_strlen((string) $s->description_ar) < 50 ||
                mb_strlen((string) $s->description_en) < 50
            )
            ->pluck('code')
            ->all();

        $this->assertSame([], $tooShort, 'Every non-survey service should have a paragraph description');
    }

    public function test_tile_descriptions_are_updated_by_this_seeder(): void
    {
        // The tile-level services (JEA-CERT, JEA-FIN, ...) are created by
        // JeaPortalTilesSeeder. ServicePlan2026Seeder must overwrite their
        // (short) tile-blurb with the paragraph description from its map.
        $this->runSilently(new JeaPortalTilesSeeder());
        $this->runSeeder();

        $cert = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'JEA-CERT')
            ->first();

        $this->assertNotNull($cert);
        $this->assertGreaterThan(100, mb_strlen((string) $cert->description_ar));
        $this->assertGreaterThan(100, mb_strlen((string) $cert->description_en));
        // Vocabulary check — the tile description names the specific
        // certificate types it groups.
        $this->assertStringContainsString('المطابقة', $cert->description_ar);
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

    public function test_catalog_api_returns_phase_field(): void
    {
        $this->runSeeder();
        $user = User::create([
            'organization_id' => $this->org->id, 'name' => 'x', 'email' => 'x@t.dev',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/v1/services');
        $res->assertOk();

        $svc = collect($res->json('services'))->firstWhere('code', 'DRW-P-001');
        $this->assertNotNull($svc, 'DRW-P-001 must be in the catalog');
        $this->assertArrayHasKey('phase', $svc);
        $this->assertSame(1, $svc['phase']);
    }

    public function test_survey_services_are_grouped_into_three_subcategories(): void
    {
        $this->runSeeder();

        $counts = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('parent_code', 'JEA-SURV')
            ->whereNotNull('subcategory_ar')
            ->selectRaw('subcategory_ar, count(*) as c')
            ->groupBy('subcategory_ar')
            ->pluck('c', 'subcategory_ar')
            ->toArray();

        // User-requested grouping: 10 site survey / 2 material testing / 2 excavation.
        $this->assertSame(10, $counts['استطلاع الموقع']       ?? 0, 'Site Survey subgroup count');
        $this->assertSame(2,  $counts['فحص المواد للأبنية']    ?? 0, 'Material Testing subgroup count');
        $this->assertSame(2,  $counts['الحفريات']              ?? 0, 'Excavations subgroup count');
    }

    public function test_catalog_api_returns_subcategory_fields(): void
    {
        $this->runSeeder();
        $user = User::create([
            'organization_id' => $this->org->id, 'name' => 'x', 'email' => 'sub@t.dev',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/v1/services');
        $svc = collect($res->json('services'))->firstWhere('code', 'SRV-008');
        $this->assertSame('فحص المواد للأبنية', $svc['subcategory_ar']);
        $this->assertSame('Material Testing',     $svc['subcategory_en']);
    }

    private function runSeeder(): void
    {
        // Silence the seeder's info() output during tests.
        (new ServicePlan2026Seeder())->setContainer($this->app)
            ->setCommand(new class extends \Illuminate\Console\Command {
                public function info($string, $verbosity = null): void {}
                public function error($string, $verbosity = null): void {}
                public function warn($string, $verbosity = null): void {}
            })
            ->run();
    }
}
