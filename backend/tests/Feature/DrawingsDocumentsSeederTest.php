<?php

namespace Tests\Feature;

use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use Database\Seeders\CatalogWorkflowsSeeder;
use Database\Seeders\DrawingsDocumentsSeeder;
use Database\Seeders\JeaPortalTilesSeeder;
use Database\Seeders\ServicePlan2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JORD-54: pins the shared drawings document manifest.
 *
 * User request: after manually adding a 15-document set to DRW-P-001
 * via the admin assistant, apply the same set to DRW-P-002..010 so
 * every drawings service demands the same paperwork. This test pins:
 *   • The set is present on all 10 services.
 *   • The 12 required + 3 optional split matches the plan
 *     (optionals are conditional-by-nature: commercial_register,
 *      calculation_notes, structural_safety_study).
 *   • Re-running the seeder does not add duplicates.
 *   • CatalogWorkflowsSeeder's workflow is not clobbered.
 */
class DrawingsDocumentsSeederTest extends TestCase
{
    use RefreshDatabase;

    private const DRAWING_CODES = [
        'DRW-P-001', 'DRW-P-002', 'DRW-P-003', 'DRW-P-004', 'DRW-P-005',
        'DRW-P-006', 'DRW-P-007', 'DRW-P-008', 'DRW-P-009', 'DRW-P-010',
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
    }

    public function test_every_drawing_service_carries_the_15_document_manifest(): void
    {
        foreach (self::DRAWING_CODES as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)
                ->first();
            $this->assertNotNull($svc, "{$code} must exist");
            $docs = data_get($svc->schema, 'documents', []);
            $this->assertCount(15, $docs,
                "{$code} must carry the shared 15-document manifest");
        }
    }

    public function test_manifest_has_12_required_and_3_conditional_optional_documents(): void
    {
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-001')
            ->first();
        $docs = data_get($svc->schema, 'documents', []);

        $required = array_filter($docs, fn ($d) => ($d['required'] ?? false) === true);
        $this->assertCount(12, $required, 'Expected 12 required-by-default documents');

        $optional = array_filter($docs, fn ($d) => ($d['required'] ?? false) === false);
        $optionalIds = array_map(fn ($d) => $d['id'], $optional);
        sort($optionalIds);
        $this->assertSame(
            ['calculation_notes', 'commercial_register', 'structural_safety_study'],
            $optionalIds,
            'The three data-conditional documents should be optional-by-default; '
                . 'admin toggles them per-service if needed'
        );
    }

    public function test_re_running_the_seeder_does_not_duplicate_documents(): void
    {
        $this->runSilently(new DrawingsDocumentsSeeder());
        $this->runSilently(new DrawingsDocumentsSeeder());

        foreach (self::DRAWING_CODES as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)
                ->first();
            $docs = data_get($svc->schema, 'documents', []);
            $this->assertCount(15, $docs, "{$code} still 15 after re-runs");
            $ids = array_map(fn ($d) => $d['id'], $docs);
            $this->assertSame(array_unique($ids), $ids,
                "{$code} document ids must be unique");
        }
    }

    public function test_workflow_from_catalogworkflowsseeder_is_preserved(): void
    {
        // Guardrail: the doc seeder writes schema['documents'] and must
        // leave schema['workflow'] alone. If it ever starts replacing the
        // whole schema, every DRW-P-* service loses its 5-stage flow.
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-001')
            ->first();
        $stages = data_get($svc->schema, 'workflow.stages', []);
        $this->assertNotEmpty($stages,
            'CatalogWorkflowsSeeder workflow must survive the docs pass');
        $this->assertSame('catalog:2026',
            data_get($svc->schema, 'workflow_source'),
            'workflow_source annotation must survive the docs pass');
    }

    public function test_every_document_declares_accept_and_max_size(): void
    {
        // The DocumentUploader UI needs both fields on every doc entry —
        // an absent accept[] renders "any file" which defeats the point.
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-001')
            ->first();
        $docs = data_get($svc->schema, 'documents', []);
        foreach ($docs as $doc) {
            $this->assertNotEmpty($doc['accept'] ?? [], "{$doc['id']} must declare accept[]");
            $this->assertIsInt($doc['max_size_mb'] ?? null, "{$doc['id']} must declare max_size_mb");
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
