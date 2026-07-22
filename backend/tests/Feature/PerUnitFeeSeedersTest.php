<?php

namespace Tests\Feature;

use Modules\JeaServices\Engine\FeeCalculator;
use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use Modules\JeaServices\Database\Seeders\CatalogWorkflowsSeeder;
use Modules\JeaServices\Database\Seeders\DrawingFeeMatrixSeeder;
use Modules\JeaServices\Database\Seeders\DrawingsDocumentsSeeder;
use Modules\JeaServices\Database\Seeders\DrawingValiditySeeder;
use Modules\JeaServices\Database\Seeders\ExcavationFeeSeeder;
use Modules\JeaServices\Database\Seeders\JeaPortalTilesSeeder;
use Modules\JeaServices\Database\Seeders\ServicePlan2026Seeder;
use Modules\JeaServices\Database\Seeders\SolarFeeSeeder;
use Modules\JeaServices\Database\Seeders\SurveyWorkflowsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JORD-64: end-to-end proof that the two per_unit seeders wire
 * DRW-P-006 (solar) and SRV-007/012 (excavation) with the correct
 * per-manual JOD arithmetic. Bundled here rather than in two files
 * because both seeders share the exercise "seed → pull service →
 * hand to FeeCalculator → assert JOD" pattern.
 */
class PerUnitFeeSeedersTest extends TestCase
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
        $this->runSilently(new SurveyWorkflowsSeeder());
        $this->runSilently(new DrawingsDocumentsSeeder());
        $this->runSilently(new DrawingValiditySeeder());
        $this->runSilently(new DrawingFeeMatrixSeeder());
        $this->runSilently(new SolarFeeSeeder());
        $this->runSilently(new ExcavationFeeSeeder());
    }

    public function test_drw_p_006_carries_solar_per_unit_fee(): void
    {
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-006')->first();
        $this->assertNotNull($svc);

        $fee = data_get($svc->schema, 'fee', []);
        $this->assertSame('per_unit', $fee['type'] ?? null,
            'SolarFeeSeeder must override the drawings matrix on DRW-P-006');
        $this->assertSame('capacity_kw', $fee['basis'] ?? null);
        $this->assertEqualsWithDelta(4.0, $fee['rate'] ?? 0, 0.001,
            'Solar rate must be seeded at 4 JOD/kW (JEA p. 71-72)');
    }

    public function test_drw_p_006_fee_computes_400_JOD_at_100kW(): void
    {
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-006')->first();
        $calc = new FeeCalculator($svc);
        // 100 kW × 4 JOD/kW = 400.00.
        $this->assertSame(400.00, $calc->calculate(['capacity_kw' => 100]));
    }

    public function test_drw_p_006_has_capacity_kw_field(): void
    {
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-006')->first();
        $ids = collect(data_get($svc->schema, 'fields', []))->pluck('id')->all();
        $this->assertContains('capacity_kw', $ids,
            'Solar service must expose capacity_kw so the applicant can enter kW');
    }

    public function test_other_drawing_services_still_carry_the_matrix(): void
    {
        // Guardrail: SolarFeeSeeder must not touch anything other than
        // DRW-P-006. If it bled onto DRW-P-001..005/007..012, those
        // services would silently switch to capacity_kw pricing and
        // bill zero (no such field on their schemas).
        foreach (['DRW-P-001', 'DRW-P-002', 'DRW-P-005', 'DRW-P-012'] as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)->first();
            $this->assertSame('matrix',
                data_get($svc->schema, 'fee.type'),
                "{$code} must remain on the drawings matrix (governorate × building_class)");
        }
    }

    public function test_srv_007_and_srv_012_carry_excavation_per_unit_fee(): void
    {
        foreach (['SRV-007', 'SRV-012'] as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)->first();
            $this->assertNotNull($svc);
            $fee = data_get($svc->schema, 'fee', []);
            $this->assertSame('per_unit', $fee['type'] ?? null);
            $this->assertSame('area_m2', $fee['basis'] ?? null);
            $this->assertEqualsWithDelta(3.5, $fee['rate'] ?? 0, 0.001,
                "{$code} excavation design fee must be 3.5 JOD/m² (JEA p. 40)");
        }
    }

    public function test_srv_007_fee_computes_3500_JOD_at_1000m2(): void
    {
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'SRV-007')->first();
        $calc = new FeeCalculator($svc);
        $this->assertSame(3500.00, $calc->calculate(['area_m2' => 1000]));
    }

    public function test_solar_seeder_is_idempotent(): void
    {
        // Re-run twice more — capacity_kw field must not duplicate,
        // fee shape must stay identical.
        $this->runSilently(new SolarFeeSeeder());
        $this->runSilently(new SolarFeeSeeder());

        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-006')->first();
        $ids = collect(data_get($svc->schema, 'fields', []))->pluck('id')->all();
        $counts = array_count_values($ids);
        $this->assertSame(1, $counts['capacity_kw'] ?? 0,
            'capacity_kw must appear exactly once after multiple runs');
        $this->assertSame('per_unit', data_get($svc->schema, 'fee.type'));
    }

    public function test_seeders_preserve_workflow_documents_and_validity(): void
    {
        // Guardrail: the two per_unit seeders write only schema.fields
        // (append) + schema.fee (replace). Everything else must survive.
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-006')->first();
        $this->assertNotEmpty(data_get($svc->schema, 'workflow.stages'));
        $this->assertCount(15, data_get($svc->schema, 'documents', []),
            'DrawingsDocumentsSeeder 15-doc manifest must survive');
        $this->assertSame(60, data_get($svc->schema, 'certificate.validity_months'),
            'DrawingValiditySeeder 60-mo window must survive');
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
