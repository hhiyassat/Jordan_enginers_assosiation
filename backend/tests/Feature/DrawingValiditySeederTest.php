<?php

namespace Tests\Feature;

use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use Modules\JeaServices\Database\Seeders\CatalogWorkflowsSeeder;
use Modules\JeaServices\Database\Seeders\DrawingsDocumentsSeeder;
use Modules\JeaServices\Database\Seeders\DrawingValiditySeeder;
use Modules\JeaServices\Database\Seeders\JeaPortalTilesSeeder;
use Modules\JeaServices\Database\Seeders\ServicePlan2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JORD-58: pins the 5-year (60-month) approval validity on every
 * DRW-P-* drawing service (JEA 2025 manual p. 26).
 *
 * Also asserts:
 *   • Only the 12 DRW-P-* services are touched (no accidental writes
 *     onto CERT-* which really are certificates and have their own
 *     validity policy).
 *   • The seeder is idempotent and preserves the docs + workflow
 *     that earlier seeders wrote.
 */
class DrawingValiditySeederTest extends TestCase
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
    }

    public function test_every_drawing_service_gets_60_month_validity(): void
    {
        foreach (self::DRAWING_CODES as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)
                ->first();
            $this->assertNotNull($svc, "{$code} must exist");
            $this->assertSame(60,
                data_get($svc->schema, 'certificate.validity_months'),
                "{$code} must carry validity_months=60 (JEA manual p. 26 — 5 years)");
        }
    }

    public function test_non_drawing_services_are_not_touched(): void
    {
        // CERT-* services have their own validity policy (varies per
        // certificate type — some perpetual, some 12mo). Bleed from the
        // drawing seeder into a CERT-* row would silently change what
        // certificates the platform issues, so pin the isolation.
        foreach (['CERT-001', 'CERT-002', 'CERT-003'] as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)
                ->first();
            $this->assertNotNull($svc);
            $this->assertNotSame(60,
                data_get($svc->schema, 'certificate.validity_months'),
                "{$code} must NOT be affected by the drawings validity seeder");
        }
    }

    public function test_re_running_the_seeder_is_idempotent(): void
    {
        $this->runSilently(new DrawingValiditySeeder());
        $this->runSilently(new DrawingValiditySeeder());

        foreach (self::DRAWING_CODES as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)
                ->first();
            $this->assertSame(60, data_get($svc->schema, 'certificate.validity_months'));
        }
    }

    public function test_seeder_preserves_workflow_and_documents(): void
    {
        // Guardrail: if the seeder ever starts replacing schema wholesale
        // instead of merging, every DRW-P-* loses its 5-stage workflow
        // and 15-doc manifest. Pinning both here.
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-001')
            ->first();
        $this->assertNotEmpty(
            data_get($svc->schema, 'workflow.stages'),
            'CatalogWorkflowsSeeder workflow must survive the validity pass'
        );
        $this->assertCount(
            15,
            data_get($svc->schema, 'documents', []),
            'DrawingsDocumentsSeeder 15-doc manifest must survive the validity pass'
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
