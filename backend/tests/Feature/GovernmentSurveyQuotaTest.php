<?php

namespace Tests\Feature;

use Modules\JeaProjects\Engine\CapacityGuard;
use Modules\JeaProjects\Engine\Disciplines;
use Modules\JeaProjects\Engine\QuotaLedger;
use Modules\JeaServices\Models\Application;
use Modules\JeaProjects\Models\Engineer;
use Modules\JeaProjects\Models\EngineerDisciplineQuota;
use Modules\JeaProjects\Models\OfficeCeiling;
use App\Models\Organization;
use Modules\JeaProjects\Models\QuotaConsumption;
use Modules\JeaServices\Models\ServiceDefinition;
use App\Models\User;
use Modules\JeaServices\Database\Seeders\CatalogWorkflowsSeeder;
use Modules\JeaProjects\Database\Seeders\GovernmentSurveyQuotaSeeder;
use Modules\JeaServices\Database\Seeders\JeaPortalTilesSeeder;
use Modules\JeaServices\Database\Seeders\ServicePlan2026Seeder;
use Modules\JeaServices\Database\Seeders\SurveyWorkflowsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JORD-75: pins the government-bidder site-survey quota on SRV-006.
 * Also exercises the schema.quota_basis_field extension that lets any
 * future service declare a non-area basis without touching the engine.
 */
class GovernmentSurveyQuotaTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $officeUser;
    private Engineer $engineer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->officeUser = User::create([
            'organization_id' => $this->org->id, 'name' => 'o', 'email' => 'o@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->engineer = Engineer::create([
            'organization_id' => $this->org->id, 'office_user_id' => $this->officeUser->id,
            'name_ar' => 'م. الاستطلاع', 'membership_number' => 'EN-SURV',
            'specialization' => Disciplines::MECHANICAL,
        ]);
        EngineerDisciplineQuota::create([
            'engineer_id' => $this->engineer->id, 'discipline' => Disciplines::MECHANICAL,
            'year' => (int) now()->year, 'm2_allowed' => 999999,
        ]);
        $this->runSilently(new JeaPortalTilesSeeder());
        $this->runSilently(new ServicePlan2026Seeder());
        $this->runSilently(new CatalogWorkflowsSeeder());
        $this->runSilently(new SurveyWorkflowsSeeder());
        $this->runSilently(new GovernmentSurveyQuotaSeeder());
    }

    public function test_srv_006_carries_length_lm_field_and_override(): void
    {
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'SRV-006')->first();
        $this->assertSame('government_survey',
            data_get($svc->schema, 'quota_discipline_override'));
        $this->assertSame('length_lm',
            data_get($svc->schema, 'quota_basis_field'));
        $ids = collect(data_get($svc->schema, 'fields', []))->pluck('id')->all();
        $this->assertContains('length_lm', $ids);
        $this->assertContains('engineer_id', $ids);
    }

    public function test_office_ceiling_for_govt_survey_seeded_at_2500_lm(): void
    {
        $ceiling = OfficeCeiling::where('organization_id', $this->org->id)
            ->where('discipline', 'government_survey')
            ->where('year', (int) now()->year)
            ->first();
        $this->assertNotNull($ceiling);
        $this->assertSame(2500, $ceiling->m2_allowed);
    }

    public function test_capacity_guard_reads_length_lm_from_data(): void
    {
        // Applicant submits length_lm=3000 on a 2500-lm ceiling → 500 lm short.
        $app = $this->makeApp('SRV-006', [
            'engineer_id' => $this->engineer->id, 'length_lm' => 3000,
        ]);
        $errors = app(CapacityGuard::class)->validate($app);
        $this->assertArrayHasKey('office_ceiling', $errors);
        // Error message uses م.ط unit (not م²) — proves the label
        // adapted to the basis field.
        $this->assertStringContainsString('م.ط', $errors['office_ceiling']);
        $this->assertStringContainsString('2500', $errors['office_ceiling']);
        $this->assertStringContainsString('3000', $errors['office_ceiling']);
    }

    public function test_missing_length_lm_flags_field_specifically(): void
    {
        // The error key must be the basis field name (length_lm), not
        // the hardcoded 'area_m2' from pre-JORD-75.
        $app = $this->makeApp('SRV-006', ['engineer_id' => $this->engineer->id]);
        $errors = app(CapacityGuard::class)->validate($app);
        $this->assertArrayHasKey('length_lm', $errors);
        $this->assertArrayNotHasKey('area_m2', $errors);
    }

    public function test_consumption_lands_on_govt_survey_bucket(): void
    {
        $app = $this->makeApp('SRV-006', [
            'engineer_id' => $this->engineer->id, 'length_lm' => 800,
        ]);
        $app->update(['status' => Application::STATUS_APPROVED]);
        app(QuotaLedger::class)->recordApproval($app);

        $row = QuotaConsumption::where('application_id', $app->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('government_survey', $row->discipline);
        // m2 column stores the length_lm value (units defined by schema).
        $this->assertSame(800, $row->m2);
    }

    public function test_within_ceiling_passes(): void
    {
        $app = $this->makeApp('SRV-006', [
            'engineer_id' => $this->engineer->id, 'length_lm' => 500,
        ]);
        $this->assertSame([], app(CapacityGuard::class)->validate($app));
    }

    private function makeApp(string $code, array $data): Application
    {
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', $code)->first();
        return Application::create([
            'reference_number'      => strtoupper(bin2hex(random_bytes(4))),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $svc->id,
            'applicant_id'          => $this->officeUser->id,
            'status'                => Application::STATUS_DRAFT,
            'data'                  => $data,
            'fee_amount'            => 0,
        ]);
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
