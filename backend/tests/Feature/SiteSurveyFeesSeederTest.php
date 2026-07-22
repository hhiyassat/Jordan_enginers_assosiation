<?php

namespace Tests\Feature;

use Modules\JeaServices\Engine\FeeCalculator;
use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use Modules\JeaServices\Database\Seeders\CatalogWorkflowsSeeder;
use Modules\JeaProjects\Database\Seeders\GovernmentSurveyQuotaSeeder;
use Modules\JeaServices\Database\Seeders\JeaPortalTilesSeeder;
use Modules\JeaServices\Database\Seeders\ServicePlan2026Seeder;
use Modules\JeaServices\Database\Seeders\SiteSurveyFeesSeeder;
use Modules\JeaServices\Database\Seeders\SurveyWorkflowsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JORD-78: pins the site-survey per-lm fee (150 fils/lm + 1% syndicate)
 * seeded onto SRV-001..006. Also verifies compatibility with JORD-75
 * (SRV-006's government-survey quota_basis_field + override survive).
 */
class SiteSurveyFeesSeederTest extends TestCase
{
    use RefreshDatabase;

    private const SURVEY_CODES = ['SRV-001', 'SRV-002', 'SRV-003', 'SRV-004', 'SRV-005', 'SRV-006'];

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
        $this->runSilently(new GovernmentSurveyQuotaSeeder());
        $this->runSilently(new SiteSurveyFeesSeeder());
    }

    public function test_every_survey_service_carries_per_unit_length_lm_fee(): void
    {
        foreach (self::SURVEY_CODES as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)->first();
            $fee = data_get($svc->schema, 'fee', []);
            $this->assertSame('per_unit',  $fee['type']  ?? null, "{$code} must be per_unit");
            $this->assertSame('length_lm', $fee['basis'] ?? null, "{$code} must bill on length_lm");
            $this->assertEqualsWithDelta(0.15, $fee['rate'] ?? 0, 0.001,
                "{$code} rate must be 150 fils/lm per manual p.96");
        }
    }

    public function test_every_survey_service_carries_the_1_percent_syndicate_surcharge(): void
    {
        foreach (self::SURVEY_CODES as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)->first();
            $surcharges = data_get($svc->schema, 'fee.surcharges', []);
            $codes = collect($surcharges)->pluck('code')->all();
            $this->assertContains('syndicate_1pct', $codes,
                "{$code} must carry the 1% syndicate surcharge (JORD-65 pattern)");
        }
    }

    public function test_length_lm_field_is_required_on_every_survey_service(): void
    {
        foreach (self::SURVEY_CODES as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)->first();
            $field = collect(data_get($svc->schema, 'fields', []))
                ->firstWhere('id', 'length_lm');
            $this->assertNotNull($field, "{$code} must declare length_lm");
            $this->assertTrue($field['required'] ?? false);
        }
    }

    public function test_end_to_end_arithmetic_at_1000_lm(): void
    {
        // 1,000 lm × 0.15 = 150 base + 1.50 syndicate = 151.50 JOD.
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'SRV-001')->first();
        $breakdown = (new FeeCalculator($svc))->calculateBreakdown(['length_lm' => 1000]);

        $this->assertSame(150.00,  $breakdown['base']);
        $this->assertSame(1.50,    $breakdown['surcharges'][0]['amount']);
        $this->assertSame(151.50,  $breakdown['total']);
    }

    public function test_srv_006_preserves_jord_75_quota_wiring(): void
    {
        // Guardrail: JORD-75 wired SRV-006 with quota_discipline_override=
        // 'government_survey' and quota_basis_field='length_lm'. This
        // fee seeder MUST NOT overwrite them — they live on different
        // schema keys but a wholesale-replace refactor could accidentally
        // clobber them.
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'SRV-006')->first();
        $this->assertSame('government_survey', data_get($svc->schema, 'quota_discipline_override'));
        $this->assertSame('length_lm',          data_get($svc->schema, 'quota_basis_field'));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->runSilently(new SiteSurveyFeesSeeder());
        $this->runSilently(new SiteSurveyFeesSeeder());
        foreach (self::SURVEY_CODES as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)->first();
            $ids = collect(data_get($svc->schema, 'fields', []))->pluck('id')->all();
            $counts = array_count_values($ids);
            $this->assertSame(1, $counts['length_lm'] ?? 0,
                "{$code}: length_lm must appear exactly once after re-runs");
            $this->assertCount(1, data_get($svc->schema, 'fee.surcharges', []),
                "{$code}: syndicate surcharge must not duplicate");
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
