<?php

namespace Tests\Feature;

use App\Engine\CapacityGuard;
use App\Engine\Disciplines;
use App\Engine\QuotaLedger;
use App\Models\Application;
use App\Models\ApplicationReview;
use App\Models\Engineer;
use App\Models\EngineerDisciplineQuota;
use App\Models\OfficeCeiling;
use App\Models\Organization;
use App\Models\QuotaConsumption;
use App\Models\ServiceDefinition;
use App\Models\User;
use Database\Seeders\CatalogWorkflowsSeeder;
use Database\Seeders\JeaPortalTilesSeeder;
use Database\Seeders\MaterialsTestingQuotaSeeder;
use Database\Seeders\QuotasAndCeilingsSeeder;
use Database\Seeders\ServicePlan2026Seeder;
use Database\Seeders\SurveyWorkflowsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JORD-74: pins the materials-testing (SRV-008/009) quota routing:
 *   • Ceiling seeded at 1200 m² for the materials_testing bucket.
 *   • SRV-008/009 schemas carry quota_discipline_override='materials_testing'.
 *   • Consumption on approval lands on the materials ceiling,
 *     NOT on the picked engineer's own-discipline ceiling.
 *   • CapacityGuard checks the materials ceiling, not the engineer's.
 */
class MaterialsTestingQuotaTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $officeUser;
    private User $reviewer;
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
        $this->reviewer = User::create([
            'organization_id' => $this->org->id, 'name' => 'r', 'email' => 'r@t.esp',
            'password' => 'x', 'role' => 'staff', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->engineer = Engineer::create([
            'organization_id' => $this->org->id, 'office_user_id' => $this->officeUser->id,
            'name_ar' => 'م. مهندسة تربة', 'membership_number' => 'EN-SOIL',
            'specialization' => Disciplines::MECHANICAL,
        ]);
        // The engineer's OWN discipline (mechanical) has a LARGE ceiling
        // so if the guard/ledger incorrectly used it, tests would pass
        // for the wrong reason. Making the materials ceiling the tight
        // constraint proves the redirect happened.
        EngineerDisciplineQuota::create([
            'engineer_id' => $this->engineer->id,
            'discipline'  => Disciplines::MECHANICAL,
            'year'        => (int) now()->year, 'm2_allowed' => 999999,
        ]);
        OfficeCeiling::create([
            'organization_id' => $this->org->id,
            'office_user_id'  => $this->officeUser->id,
            'discipline'      => Disciplines::MECHANICAL,
            'year'            => (int) now()->year,
            'm2_allowed'      => 999999,
        ]);
        $this->runSilently(new JeaPortalTilesSeeder());
        $this->runSilently(new ServicePlan2026Seeder());
        $this->runSilently(new CatalogWorkflowsSeeder());
        $this->runSilently(new SurveyWorkflowsSeeder());
        $this->runSilently(new MaterialsTestingQuotaSeeder());
    }

    public function test_srv_008_and_009_carry_the_quota_override_and_new_fields(): void
    {
        foreach (['SRV-008', 'SRV-009'] as $code) {
            $svc = ServiceDefinition::where('organization_id', $this->org->id)
                ->where('code', $code)->first();
            $this->assertSame('materials_testing',
                data_get($svc->schema, 'quota_discipline_override'));
            $ids = collect(data_get($svc->schema, 'fields', []))->pluck('id')->all();
            $this->assertContains('area_m2', $ids);
            $this->assertContains('engineer_id', $ids);
        }
    }

    public function test_office_ceiling_for_materials_testing_seeded_at_1200(): void
    {
        $ceiling = OfficeCeiling::where('organization_id', $this->org->id)
            ->where('discipline', 'materials_testing')
            ->where('year', (int) now()->year)
            ->first();
        $this->assertNotNull($ceiling);
        $this->assertSame(1200, $ceiling->m2_allowed);
    }

    public function test_capacity_guard_uses_materials_ceiling_not_engineer_own_discipline(): void
    {
        // Attribute 100 m² of prior consumption against materials_testing.
        // Ceiling is 1200 → 1100 remaining. Submitting 1200 → over by 100.
        $priorApp = $this->makeApp('SRV-008', ['engineer_id' => $this->engineer->id, 'area_m2' => 100]);
        QuotaConsumption::create([
            'application_id'  => $priorApp->id,
            'engineer_id'     => $this->engineer->id,
            'organization_id' => $this->org->id,
            'office_user_id'  => $this->officeUser->id,
            'discipline'      => 'materials_testing',
            'year'            => (int) now()->year,
            'm2'              => 100,
        ]);

        $overApp = $this->makeApp('SRV-008', [
            'engineer_id' => $this->engineer->id, 'area_m2' => 1200,
        ]);
        $errors = app(CapacityGuard::class)->validate($overApp);
        $this->assertArrayHasKey('office_ceiling', $errors,
            'CapacityGuard must reject on the materials ceiling, not the mechanical one');
        $this->assertStringContainsString('1100', $errors['office_ceiling']);
    }

    public function test_approval_records_consumption_under_materials_discipline_not_mechanical(): void
    {
        // End-to-end: approve → consumption row appears with
        // discipline='materials_testing' (not 'mechanical').
        $app = $this->makeApp('SRV-008', [
            'engineer_id' => $this->engineer->id, 'area_m2' => 500,
        ]);
        $app->update(['status' => Application::STATUS_APPROVED]);
        // Approval review must exist for QuotaLedger to work off it,
        // but recordApproval reads data.engineer_id + data.area_m2
        // directly so we can call it here without a review.
        app(QuotaLedger::class)->recordApproval($app);

        $row = QuotaConsumption::where('application_id', $app->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('materials_testing', $row->discipline,
            'Consumption discipline must be the override, not the engineer specialization');
    }

    public function test_drawings_service_still_uses_engineer_discipline_no_override(): void
    {
        // Guardrail: the override is opt-in per service. DRW-P-* has no
        // quota_discipline_override, so its consumption must land under
        // the engineer's own discipline, not materials_testing.
        $architect = Engineer::create([
            'organization_id' => $this->org->id, 'office_user_id' => $this->officeUser->id,
            'name_ar' => 'م. معمارية', 'membership_number' => 'EN-ARCH',
            'specialization' => Disciplines::ARCHITECTURAL,
        ]);
        EngineerDisciplineQuota::create([
            'engineer_id' => $architect->id, 'discipline' => Disciplines::ARCHITECTURAL,
            'year' => (int) now()->year, 'm2_allowed' => 10000,
        ]);
        OfficeCeiling::create([
            'organization_id' => $this->org->id, 'office_user_id' => $this->officeUser->id,
            'discipline' => Disciplines::ARCHITECTURAL,
            'year' => (int) now()->year, 'm2_allowed' => 30000,
        ]);
        $drw = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-001')->first();
        $app = Application::create([
            'reference_number'      => strtoupper(bin2hex(random_bytes(4))),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $drw->id,
            'applicant_id'          => $this->officeUser->id,
            'status'                => Application::STATUS_APPROVED,
            'data' => ['engineer_id' => $architect->id, 'area_m2' => 300],
            'fee_amount' => 0,
        ]);
        app(QuotaLedger::class)->recordApproval($app);

        $row = QuotaConsumption::where('application_id', $app->id)->first();
        $this->assertSame(Disciplines::ARCHITECTURAL, $row->discipline);
    }

    private function makeApp(string $serviceCode, array $data): Application
    {
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', $serviceCode)->first();
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
