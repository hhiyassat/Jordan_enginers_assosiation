<?php

namespace Tests\Feature;

use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use Modules\JeaServices\Database\Seeders\CatalogWorkflowsSeeder;
use Modules\JeaServices\Database\Seeders\DrawingEngineerPickerSeeder;
use Modules\JeaServices\Database\Seeders\DrawingFeeMatrixSeeder;
use Modules\JeaServices\Database\Seeders\DrawingsDocumentsSeeder;
use Modules\JeaServices\Database\Seeders\DrawingValiditySeeder;
use Modules\JeaServices\Database\Seeders\JeaPortalTilesSeeder;
use Modules\JeaServices\Database\Seeders\ServicePlan2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JORD-69: pins the engineer_id picker field seeded onto every DRW-P-*.
 */
class DrawingEngineerPickerSeederTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->runSilently(new JeaPortalTilesSeeder());
        $this->runSilently(new ServicePlan2026Seeder());
        $this->runSilently(new CatalogWorkflowsSeeder());
        $this->runSilently(new DrawingsDocumentsSeeder());
        $this->runSilently(new DrawingValiditySeeder());
        $this->runSilently(new DrawingFeeMatrixSeeder());
        $this->runSilently(new DrawingEngineerPickerSeeder());
    }

    public function test_every_drawing_service_gets_the_engineer_picker(): void
    {
        foreach (['DRW-P-001', 'DRW-P-005', 'DRW-P-012'] as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)->first();
            $field = collect(data_get($svc->schema, 'fields', []))
                ->firstWhere('id', 'engineer_id');
            $this->assertNotNull($field, "{$code} must carry the engineer_id picker");
            $this->assertTrue($field['required'] ?? false);
            $this->assertSame('/engineers', $field['options_endpoint'] ?? null,
                'Frontend fetches engineer list at render time');
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->runSilently(new DrawingEngineerPickerSeeder());
        $this->runSilently(new DrawingEngineerPickerSeeder());

        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-001')->first();
        $ids = collect(data_get($svc->schema, 'fields', []))->pluck('id')->all();
        $counts = array_count_values($ids);
        $this->assertSame(1, $counts['engineer_id'] ?? 0,
            'engineer_id must appear exactly once after multiple runs');
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
