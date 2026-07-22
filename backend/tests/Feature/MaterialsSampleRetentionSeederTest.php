<?php

namespace Tests\Feature;

use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use Modules\JeaServices\Database\Seeders\CatalogWorkflowsSeeder;
use Modules\JeaServices\Database\Seeders\JeaPortalTilesSeeder;
use Modules\JeaServices\Database\Seeders\MaterialsSampleRetentionSeeder;
use Modules\JeaServices\Database\Seeders\ServicePlan2026Seeder;
use Modules\JeaServices\Database\Seeders\SurveyWorkflowsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JORD-60: pins the 10-day materials-samples retention notice
 * attached to SRV-008 (proposed buildings) and SRV-009 (existing
 * buildings) per the JEA 2025 manual (p. 36).
 */
class MaterialsSampleRetentionSeederTest extends TestCase
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
        $this->runSilently(new MaterialsSampleRetentionSeeder());
    }

    public function test_srv_008_and_srv_009_carry_the_s03_notice(): void
    {
        foreach (['SRV-008', 'SRV-009'] as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)->first();
            $this->assertNotNull($svc, "{$code} must exist");

            $notes = data_get($svc->schema, 'compliance_notes', []);
            $s03 = collect($notes)->firstWhere('code', 'S-03');
            $this->assertNotNull($s03, "{$code} must carry the S-03 notice");
            $this->assertSame(36, $s03['page'], 'Manual page reference must survive round-trip');
            $this->assertStringContainsString('عشرة أيام', $s03['body_ar'],
                'Notice body must literally say "10 days"');
            $this->assertSame('warning', $s03['severity']);
        }
    }

    public function test_notice_is_not_attached_to_other_services(): void
    {
        // The retention rule is specifically about sample-preservation on
        // material-testing sites. Bleed into SRV-001 (site survey report)
        // or any DRW-P-* would misattribute the compliance obligation.
        foreach (['SRV-001', 'SRV-002', 'DRW-P-001'] as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)->first();
            $notes = data_get($svc->schema, 'compliance_notes', []);
            $s03 = collect($notes)->firstWhere('code', 'S-03');
            $this->assertNull($s03, "{$code} must NOT carry the material-testing S-03 notice");
        }
    }

    public function test_re_running_does_not_duplicate_the_notice(): void
    {
        $this->runSilently(new MaterialsSampleRetentionSeeder());
        $this->runSilently(new MaterialsSampleRetentionSeeder());

        foreach (['SRV-008', 'SRV-009'] as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)->first();
            $notes = data_get($svc->schema, 'compliance_notes', []);
            $s03Entries = collect($notes)->where('code', 'S-03');
            $this->assertCount(1, $s03Entries,
                "{$code} must carry exactly one S-03 notice after multiple runs");
        }
    }

    public function test_seeder_preserves_workflow_and_description(): void
    {
        // The seeder must not clobber the workflow (from SurveyWorkflowsSeeder)
        // or the description_ar that SurveyWorkflowsSeeder wrote. Only the
        // schema.compliance_notes key is ours to touch.
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'SRV-008')->first();
        $this->assertNotEmpty(
            data_get($svc->schema, 'workflow.stages'),
            'SRV-008 workflow must survive the compliance-notes pass'
        );
        $this->assertNotEmpty($svc->description_ar,
            'SRV-008 Arabic description must not be blanked');
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
