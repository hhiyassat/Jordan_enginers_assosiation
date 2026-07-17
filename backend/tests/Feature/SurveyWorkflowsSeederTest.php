<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use Database\Seeders\ServicePlan2026Seeder;
use Database\Seeders\SurveyWorkflowsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pins each survey service's workflow to the drawio flowcharts —
 * stage counts, first/last stage ids, roles, and the presence of a
 * modification variant where the flowchart set defined one.
 *
 * If a flowchart changes, update the seeder and this test together.
 */
class SurveyWorkflowsSeederTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->runSilently(new ServicePlan2026Seeder());
        $this->runSilently(new SurveyWorkflowsSeeder());
    }

    public function test_slope_stability_service_is_added_by_the_seeder(): void
    {
        $slope = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'SRV-015')
            ->first();

        $this->assertNotNull($slope);
        $this->assertSame('JEA-SURV', $slope->parent_code);
        $this->assertSame('الحفريات', $slope->subcategory_ar);
        $this->assertSame(1, $slope->phase);
        $this->assertSame('active', $slope->status);
    }

    /**
     * @return list<array{string, int, string, string, bool}>
     * Each row: [service code, expected stage count, first stage id, last stage id, has modification variant]
     */
    public static function serviceWorkflowShapes(): array
    {
        return [
            'SRV-008 materials proposed' => ['SRV-008', 4, 'office_submission', 'issue_documents', true],
            'SRV-009 materials existing' => ['SRV-009', 5, 'office_submission', 'issue_documents', true],
            'SRV-001 soil proposed'      => ['SRV-001', 5, 'office_submission', 'issue_documents', false],
            'SRV-002 soil existing'      => ['SRV-002', 5, 'office_submission', 'issue_documents', false],
            'SRV-007 excavation support' => ['SRV-007', 7, 'office_submission', 'issue_documents', true],
            'SRV-012 excavation super'   => ['SRV-012', 5, 'office_submission', 'issue_documents', false],
            'SRV-014 visual inspection'  => ['SRV-014', 5, 'office_submission', 'additional_inspection_check', false],
            'SRV-015 slope stability'    => ['SRV-015', 4, 'office_submission', 'issue_documents', true],
        ];
    }

    #[DataProvider('serviceWorkflowShapes')]
    public function test_each_service_has_the_expected_workflow_shape(
        string $code,
        int $expectedStages,
        string $firstStageId,
        string $lastStageId,
        bool $hasModificationVariant
    ): void {
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', $code)
            ->first();

        $this->assertNotNull($svc, "{$code} should exist after seeding");

        $stages = $svc->schema['workflow']['stages'] ?? [];
        $this->assertCount($expectedStages, $stages, "{$code} stage count");
        $this->assertSame($firstStageId, $stages[0]['id'] ?? null, "{$code} first stage id");
        $this->assertSame($lastStageId, $stages[array_key_last($stages)]['id'] ?? null, "{$code} last stage id");

        $variants = $svc->schema['workflow']['variants'] ?? [];
        if ($hasModificationVariant) {
            $this->assertArrayHasKey('modification', $variants, "{$code} should have a modification variant");
            $modStages = $variants['modification']['stages'] ?? [];
            $this->assertGreaterThan(0, count($modStages), "{$code} modification variant must have stages");
        } else {
            $this->assertArrayNotHasKey('modification', $variants, "{$code} should not have a modification variant");
        }
    }

    public function test_flowchart_source_annotation_is_persisted_on_every_updated_service(): void
    {
        foreach (array_column(self::serviceWorkflowShapes(), 0) as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)
                ->first();
            $this->assertNotNull($svc);
            $this->assertArrayHasKey('flowchart_source', $svc->schema, "{$code} must record its flowchart_source");
            $this->assertStringStartsWith('flowcahrt/', $svc->schema['flowchart_source']);
        }
    }

    public function test_every_stage_declares_role_sla_and_actions(): void
    {
        foreach (array_column(self::serviceWorkflowShapes(), 0) as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)
                ->first();
            foreach ($svc->schema['workflow']['stages'] as $stage) {
                $this->assertArrayHasKey('id', $stage);
                $this->assertArrayHasKey('label_ar', $stage);
                $this->assertArrayHasKey('label_en', $stage);
                $this->assertArrayHasKey('role', $stage);
                $this->assertArrayHasKey('sla_hours', $stage);
                $this->assertArrayHasKey('actions', $stage);
                $this->assertIsArray($stage['actions']);
                $this->assertGreaterThan(0, count($stage['actions']));
            }
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        // Re-run the workflow seeder; nothing should change or duplicate.
        $before = ServiceDefinition::where('organization_id', $this->org->id)->count();
        $this->runSilently(new SurveyWorkflowsSeeder());
        $after = ServiceDefinition::where('organization_id', $this->org->id)->count();

        $this->assertSame($before, $after);

        $svc = ServiceDefinition::where('organization_id', $this->org->id)->where('code', 'SRV-008')->first();
        $this->assertCount(4, $svc->schema['workflow']['stages']);
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
