<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use Database\Seeders\CatalogWorkflowsSeeder;
use Database\Seeders\JeaPortalTilesSeeder;
use Database\Seeders\ServicePlan2026Seeder;
use Database\Seeders\SurveyWorkflowsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pins the workflows CatalogWorkflowsSeeder assigns to every non-survey
 * service. Together with SurveyWorkflowsSeederTest this covers every
 * submittable service in the catalog — placeholder_review stubs are
 * banned across the DB after the pipeline runs.
 */
class CatalogWorkflowsSeederTest extends TestCase
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
    }

    public function test_no_submittable_service_is_still_on_placeholder_review(): void
    {
        // Tiles (parent_code IS NULL) don't need real workflows since users
        // don't apply to a tile — they navigate through it.
        $submittable = ServiceDefinition::where('organization_id', $this->org->id)
            ->whereNotNull('parent_code')
            ->get();

        $stubs = $submittable->filter(function ($svc) {
            $stages = data_get($svc->schema, 'workflow.stages', []);
            return count($stages) === 1
                && ($stages[0]['id'] ?? null) === 'placeholder_review';
        })->pluck('code')->all();

        $this->assertSame([], $stubs,
            'Every submittable service must have a real workflow (no placeholder_review stub)');
    }

    public function test_catalog_seeder_stamps_a_workflow_source(): void
    {
        // Every catalog-driven service gets a workflow_source annotation so
        // future edits can trace where the workflow came from.
        $catalogServices = ['DRW-P-001', 'FIN-001', 'CERT-001', 'ENG-001', 'DEC-001', 'MSC-001'];
        foreach ($catalogServices as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)
                ->first();
            $this->assertNotNull($svc);
            $this->assertSame('catalog:2026', data_get($svc->schema, 'workflow_source'),
                "{$code} should carry workflow_source='catalog:2026'");
        }
    }

    public function test_survey_flowchart_workflows_are_preserved_after_catalog_seed(): void
    {
        // SurveyWorkflowsSeeder runs after CatalogWorkflowsSeeder in this
        // test's setUp — the 8 flowchart-backed services must keep their
        // flowchart_source annotations and not fall back to catalog:2026.
        $srvWithFlowchart = ['SRV-001', 'SRV-002', 'SRV-007', 'SRV-008', 'SRV-009', 'SRV-012', 'SRV-014'];
        foreach ($srvWithFlowchart as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)
                ->first();
            $this->assertNotNull($svc, "{$code} should exist");
            $this->assertStringStartsWith('flowcahrt/', (string) data_get($svc->schema, 'flowchart_source'),
                "{$code} must retain its flowchart_source (survey seeder wins)");
        }
    }

    /**
     * @return list<array{string, int, string, string, bool}>
     * Each row: [code, expected stage count, first stage id, last stage id, has modification variant]
     */
    public static function catalogWorkflowShapes(): array
    {
        return [
            // Drawings
            'DRW-P-001 proposed'   => ['DRW-P-001', 5, 'office_submission', 'issue_documents', false],
            'DRW-P-004 demolition' => ['DRW-P-004', 5, 'office_submission', 'issue_documents', false],
            'DRW-P-005 large'      => ['DRW-P-005', 5, 'office_submission', 'issue_documents', false],
            'DRW-P-008 mods'       => ['DRW-P-008', 5, 'office_submission', 'issue_documents', true],
            'DRW-P-011 re-approval' => ['DRW-P-011', 4, 'office_submission', 'issue_documents', false],
            // Financial
            'FIN-001 salary'       => ['FIN-001', 4, 'office_submission', 'disbursement', false],
            // Certificates
            'CERT-001 conformity'  => ['CERT-001', 6, 'office_submission', 'issue_certificate', false],
            'CERT-003 office cls'  => ['CERT-003', 5, 'office_submission', 'issue_certificate', false],
            // Engineers
            'ENG-001 office staff' => ['ENG-001', 4, 'office_submission', 'registration_update', false],
            // Board
            'DEC-001 office req'   => ['DEC-001', 5, 'office_submission', 'notification', false],
            // Misc
            'MSC-001 quota'        => ['MSC-001', 2, 'office_request', 'serve_response', false],
            'MSC-011 meeting'      => ['MSC-011', 3, 'office_submission', 'confirm_appointment', false],
            'MSC-014 recruitment'  => ['MSC-014', 3, 'office_posting', 'publish_and_match', false],
            // Extended-survey
            'SRV-010 re-approval-adds' => ['SRV-010', 5, 'office_submission', 'issue_documents', true],
        ];
    }

    #[DataProvider('catalogWorkflowShapes')]
    public function test_catalog_workflow_shape(
        string $code,
        int $expectedStages,
        string $firstStageId,
        string $lastStageId,
        bool $hasModificationVariant
    ): void {
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', $code)
            ->first();
        $this->assertNotNull($svc);

        $stages = data_get($svc->schema, 'workflow.stages', []);
        $this->assertCount($expectedStages, $stages, "{$code} stage count");
        $this->assertSame($firstStageId, $stages[0]['id'] ?? null, "{$code} first stage id");
        $this->assertSame($lastStageId, $stages[array_key_last($stages)]['id'] ?? null, "{$code} last stage id");

        $variants = data_get($svc->schema, 'workflow.variants', []);
        if ($hasModificationVariant) {
            $this->assertArrayHasKey('modification', $variants);
        } else {
            $this->assertArrayNotHasKey('modification', $variants);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $before = ServiceDefinition::where('organization_id', $this->org->id)->count();
        $this->runSilently(new CatalogWorkflowsSeeder());
        $after = ServiceDefinition::where('organization_id', $this->org->id)->count();

        $this->assertSame($before, $after);
    }

    public function test_catalog_and_survey_variants_summed_across_the_catalog(): void
    {
        $withMod = ServiceDefinition::where('organization_id', $this->org->id)
            ->get()
            ->filter(fn($s) => isset($s->schema['workflow']['variants']['modification']))
            ->pluck('code')
            ->all();

        // Survey (from SurveyWorkflowsSeeder): SRV-007, 008, 009 (3 — SRV-015 dropped from plan)
        // Catalog (from this seeder): DRW-P-008, 009, 010, SRV-010 (4)
        // Total = 7
        $this->assertCount(7, $withMod,
            'Expected 7 services to expose a modification variant, got: ' . implode(', ', $withMod));
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
