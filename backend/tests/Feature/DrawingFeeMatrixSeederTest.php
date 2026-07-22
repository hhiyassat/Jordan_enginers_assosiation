<?php

namespace Tests\Feature;

use Modules\JeaServices\Engine\FeeCalculator;
use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use Modules\JeaServices\Database\Seeders\CatalogWorkflowsSeeder;
use Modules\JeaServices\Database\Seeders\DrawingFeeMatrixSeeder;
use Modules\JeaServices\Database\Seeders\DrawingsDocumentsSeeder;
use Modules\JeaServices\Database\Seeders\DrawingValiditySeeder;
use Modules\JeaServices\Database\Seeders\JeaPortalTilesSeeder;
use Modules\JeaServices\Database\Seeders\ServicePlan2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JORD-63: pins the JEA 2025 fee matrix (p. 92) seeded on every
 * DRW-P-*. Also proves the seeder integrates cleanly with the rest of
 * the drawings-service pipeline (workflow / documents / validity all
 * survive).
 */
class DrawingFeeMatrixSeederTest extends TestCase
{
    use RefreshDatabase;

    private const DRAWING_CODES = [
        'DRW-P-001', 'DRW-P-002', 'DRW-P-003', 'DRW-P-004', 'DRW-P-005',
        'DRW-P-006', 'DRW-P-007', 'DRW-P-008', 'DRW-P-009', 'DRW-P-010',
        'DRW-P-011', 'DRW-P-012',
    ];

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
    }

    public function test_every_drawing_service_gets_the_matrix_fee_block(): void
    {
        foreach (self::DRAWING_CODES as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)->first();
            $this->assertNotNull($svc);

            $fee = data_get($svc->schema, 'fee', []);
            $this->assertSame('matrix', $fee['type'] ?? null,
                "{$code} must carry type=matrix fee");
            $this->assertSame(['governorate', 'building_class'], $fee['keys'] ?? null);
            $this->assertSame('area_m2', $fee['basis'] ?? null);
            // JSON round-trip flattens 5.0 → 5 (int); compare numerically.
            $this->assertEqualsWithDelta(5.0, $fee['rates']['amman|private'] ?? 0, 0.001,
                'Amman × private must be seeded at 5.0 JOD/m² (manual p. 92)');
        }
    }

    public function test_every_drawing_service_gets_the_three_required_form_fields(): void
    {
        foreach (self::DRAWING_CODES as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)->first();
            $fields = collect(data_get($svc->schema, 'fields', []));
            $ids = $fields->pluck('id')->all();
            foreach (['governorate', 'building_class', 'area_m2'] as $required) {
                $this->assertContains($required, $ids,
                    "{$code} must declare the {$required} form field so the matrix has data to look up");
                $field = $fields->firstWhere('id', $required);
                $this->assertTrue($field['required'] ?? false,
                    "{$code}.{$required} must be required — the matrix collapses to default without it");
            }
        }
    }

    public function test_seeded_matrix_produces_correct_fee_end_to_end(): void
    {
        // Live drive: pull DRW-P-001 straight from the DB, hand it to
        // FeeCalculator, and assert the arithmetic matches the manual.
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-001')->first();
        $calc = new FeeCalculator($svc);

        // Amman × private × 300 m² → 5.0 × 300 = 1500.
        $this->assertSame(1500.00, $calc->calculate([
            'governorate'    => 'amman',
            'building_class' => 'private',
            'area_m2'        => 300,
        ]));

        // Irbid (→ other bucket) × rural_shaabi × 500 → 1.5 × 500 = 750.
        $this->assertSame(750.00, $calc->calculate([
            'governorate'    => 'irbid',
            'building_class' => 'rural_shaabi',
            'area_m2'        => 500,
        ]));
    }

    public function test_re_running_the_seeder_does_not_duplicate_form_fields(): void
    {
        // Re-runs should be no-ops on field lists — otherwise a fresh
        // db:seed --class after JORD-63 shipped once would leave every
        // drawing schema with TWO 'governorate' selects.
        $this->runSilently(new DrawingFeeMatrixSeeder());
        $this->runSilently(new DrawingFeeMatrixSeeder());

        foreach (self::DRAWING_CODES as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)->first();
            $ids = collect(data_get($svc->schema, 'fields', []))->pluck('id')->all();
            $counts = array_count_values($ids);
            $this->assertSame(1, $counts['governorate']    ?? 0);
            $this->assertSame(1, $counts['building_class'] ?? 0);
            $this->assertSame(1, $counts['area_m2']        ?? 0);
        }
    }

    public function test_seeder_preserves_workflow_documents_and_validity(): void
    {
        // Guardrail: this seeder replaces schema.fee wholesale and
        // appends to schema.fields. If it ever starts replacing the
        // whole schema, every DRW-P-* loses (a) the CatalogWorkflows
        // 5-stage workflow, (b) the 15-doc manifest, (c) the 60-mo
        // validity JORD-58 seeded.
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-001')->first();
        $this->assertNotEmpty(data_get($svc->schema, 'workflow.stages'));
        $this->assertCount(15, data_get($svc->schema, 'documents', []));
        $this->assertSame(60, data_get($svc->schema, 'certificate.validity_months'));
    }

    public function test_governorate_field_has_all_12_options(): void
    {
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-001')->first();
        $field = collect(data_get($svc->schema, 'fields', []))
            ->firstWhere('id', 'governorate');
        $this->assertCount(12, $field['options'] ?? [],
            'Governorate options must cover all 12 Jordan governorates');
        // A representative sample — if this test starts flapping the
        // whole option list should be reviewed.
        $codes = collect($field['options'])->pluck('value')->all();
        foreach (['amman', 'irbid', 'aqaba', 'ajloun'] as $expected) {
            $this->assertContains($expected, $codes, "Governorate '{$expected}' must be listed");
        }
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
