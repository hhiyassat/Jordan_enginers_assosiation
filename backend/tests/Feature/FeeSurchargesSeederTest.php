<?php

namespace Tests\Feature;

use Modules\JeaServices\Engine\FeeCalculator;
use Modules\JeaServices\Models\Application;
use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use App\Models\User;
use Database\Seeders\CatalogWorkflowsSeeder;
use Database\Seeders\DrawingFeeMatrixSeeder;
use Database\Seeders\DrawingsDocumentsSeeder;
use Database\Seeders\DrawingValiditySeeder;
use Database\Seeders\ExcavationFeeSeeder;
use Database\Seeders\FeeSurchargesSeeder;
use Database\Seeders\JeaPortalTilesSeeder;
use Database\Seeders\ServicePlan2026Seeder;
use Database\Seeders\SolarFeeSeeder;
use Database\Seeders\SurveyWorkflowsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-65: pins the JEA p.96 surcharges seeded onto every fee-bearing
 * service AND proves the show() endpoint surfaces the breakdown so
 * the applicant UI can render itemized totals.
 */
class FeeSurchargesSeederTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $applicant;

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
        $this->runSilently(new FeeSurchargesSeeder());
        $this->applicant = User::create([
            'organization_id' => $this->org->id, 'name' => 'a', 'email' => 'a@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
    }

    public function test_every_drawing_service_carries_both_surcharges(): void
    {
        foreach (['DRW-P-001', 'DRW-P-002', 'DRW-P-006', 'DRW-P-012'] as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)->first();
            $surcharges = data_get($svc->schema, 'fee.surcharges', []);
            $codes = collect($surcharges)->pluck('code')->all();
            $this->assertContains('syndicate_1pct', $codes,
                "{$code} must carry the 1% syndicate surcharge");
            $this->assertContains('drawing_review_40fils', $codes,
                "{$code} must carry the 40 fils/m² drawing-review surcharge");
        }
    }

    public function test_excavation_services_carry_only_the_1_percent_surcharge(): void
    {
        // Manual p.96 specifically ties the 40 fils/m² to drawing review,
        // not shoring. Bleed onto SRV-007/012 would over-bill the
        // applicant, so isolate.
        foreach (['SRV-007', 'SRV-012'] as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)->first();
            $codes = collect(data_get($svc->schema, 'fee.surcharges', []))->pluck('code')->all();
            $this->assertContains('syndicate_1pct', $codes);
            $this->assertNotContains('drawing_review_40fils', $codes,
                "{$code} must NOT carry the drawing-review surcharge");
        }
    }

    public function test_end_to_end_calculation_for_amman_private_200m2(): void
    {
        // Realistic drawings shape:
        //   base = matrix Amman|private × 200 m² = 5.0 × 200 = 1000
        //   syndicate = 1000 × 0.01 = 10
        //   review = 200 × 0.04 = 8
        //   total = 1018
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-001')->first();
        $breakdown = (new FeeCalculator($svc))->calculateBreakdown([
            'governorate' => 'amman', 'building_class' => 'private', 'area_m2' => 200,
        ]);
        $this->assertSame(1000.00, $breakdown['base']);
        $this->assertSame(1018.00, $breakdown['total']);
        $this->assertCount(2, $breakdown['surcharges']);
    }

    public function test_solar_service_gets_1_percent_on_capacity_kw_base(): void
    {
        // DRW-P-006 base is per_unit(capacity_kw, 4.0). Syndicate applies
        // as a percentage of that base — proves surcharges compose with
        // ALL base fee types, not just matrix.
        //   base = 250 kW × 4 = 1000
        //   syndicate = 10
        //   review = 200 m² × 0.04 = 8 (uses the still-present area_m2 field)
        //   total = 1018
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-006')->first();
        $breakdown = (new FeeCalculator($svc))->calculateBreakdown([
            'capacity_kw' => 250, 'area_m2' => 200,
        ]);
        $this->assertSame(1000.00, $breakdown['base']);
        $this->assertSame(1018.00, $breakdown['total']);
    }

    public function test_show_endpoint_returns_fee_breakdown(): void
    {
        // The whole point of JORD-65: the frontend can render itemized
        // fees on the review screen without duplicating the calculation.
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-001')->first();
        $app = Application::create([
            'reference_number'      => strtoupper(bin2hex(random_bytes(4))),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $svc->id,
            'applicant_id'          => $this->applicant->id,
            'status'                => Application::STATUS_DRAFT,
            'data' => [
                'governorate' => 'amman', 'building_class' => 'private', 'area_m2' => 200,
            ],
            'fee_amount' => 0,
        ]);

        Sanctum::actingAs($this->applicant);
        $res = $this->getJson("/api/v1/applications/{$app->id}");
        $res->assertOk();

        $breakdown = $res->json('fee_breakdown');
        $this->assertNotNull($breakdown, 'show() must include fee_breakdown');
        // JSON round-trip flattens 1000.00 → 1000 (int); compare numerically.
        $this->assertEqualsWithDelta(1000.00, $breakdown['base'], 0.001);
        $this->assertEqualsWithDelta(1018.00, $breakdown['total'], 0.001);
        $this->assertCount(2, $breakdown['surcharges']);
        $this->assertSame('JOD', $breakdown['currency']);
        // Labels reach the frontend so the UI doesn't need to hardcode.
        $syndicate = collect($breakdown['surcharges'])->firstWhere('code', 'syndicate_1pct');
        $this->assertNotNull($syndicate);
        $this->assertStringContainsString('النقابة', $syndicate['label_ar']);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->runSilently(new FeeSurchargesSeeder());
        $this->runSilently(new FeeSurchargesSeeder());

        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-001')->first();
        $surcharges = data_get($svc->schema, 'fee.surcharges', []);
        // Exactly 2 surcharges (1% + 40 fils/m²); no duplicates after
        // multiple runs.
        $this->assertCount(2, $surcharges);
        $codes = collect($surcharges)->pluck('code')->all();
        $this->assertSame(array_unique($codes), $codes);
    }

    public function test_seeder_skips_services_without_a_base_fee(): void
    {
        // Bespoke service with fee=null or empty — attaching a
        // percent_of_base surcharge to a base=0 fee would produce a
        // zero-line item and a surcharge-only total, which is
        // nonsensical. Better to skip.
        $service = ServiceDefinition::create([
            'organization_id' => $this->org->id, 'code' => 'CUSTOM-999',
            'name_ar' => 'x', 'name_en' => 'x', 'currency' => 'JOD',
            'schema'  => ['workflow' => ['stages' => []]],
        ]);
        // Run again; the custom service must not gain surcharges.
        $this->runSilently(new FeeSurchargesSeeder());

        $fresh = $service->fresh();
        $this->assertNull(data_get($fresh->schema, 'fee.surcharges'));
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
