<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\JeaServices\Database\Seeders\CatalogWorkflowsSeeder;
use Modules\JeaServices\Database\Seeders\JeaPortalTilesSeeder;
use Modules\JeaServices\Database\Seeders\ServicePlan2026Seeder;
use Modules\JeaServices\Database\Seeders\SiteSurveyFeesSeeder;
use Modules\JeaServices\Database\Seeders\Srv001PilotSeeder;
use Modules\JeaServices\Database\Seeders\SurveyWorkflowsSeeder;
use Modules\JeaServices\Engine\ExplorationRequirementMatrix;
use Modules\JeaServices\Engine\Srv001Guard;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * Server-side gate pins for SRV-001 (directive §3, §8).
 *
 * The guard is intentionally not-controller-aware — it takes an
 * Application and returns [] on pass or an errors array on fail —
 * so these tests instantiate applications directly.
 *
 * Coverage:
 *   • project_sector=حكومي rejected + points to SRV-006
 *   • actual < minimum rejected with the exact minimum in the message
 *   • actual >= minimum accepted, minimums persisted
 *   • floor_area > 1200 flips SPECIAL_STUDY_REQUIRED + technical_review_required
 *   • floor_count >= 9 flips SPECIAL_STUDY_REQUIRED
 *   • Guard is a no-op for services other than SRV-001 (safety)
 */
class Srv001GuardTest extends TestCase
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
        $this->applicant = User::create([
            'organization_id' => $this->org->id,
            'name'  => 'office',
            'email' => 'srv001-guard@t.esp',
            'password' => 'x',
            'role' => 'applicant',
            'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $this->runSilently(new JeaPortalTilesSeeder());
        $this->runSilently(new ServicePlan2026Seeder());
        $this->runSilently(new CatalogWorkflowsSeeder());
        $this->runSilently(new SurveyWorkflowsSeeder());
        $this->runSilently(new SiteSurveyFeesSeeder());
        $this->runSilently(new Srv001PilotSeeder());
    }

    public function test_government_sector_is_blocked_and_message_names_srv006(): void
    {
        $app = $this->draft(['project_sector' => 'حكومي']);
        $errors = (new Srv001Guard())->validate($app);

        $this->assertArrayHasKey('project_sector', $errors);
        $this->assertStringContainsString('SRV-006', $errors['project_sector']);
    }

    public function test_actual_less_than_minimum_is_rejected(): void
    {
        // floors=5, area=900 → minimum 5 points per manual pp.230
        $app = $this->draft([
            'project_sector' => 'خاص',
            'floor_count'    => 5,
            'floor_area'     => 900,
            'actual_exploration_point_count' => 4,
        ]);
        $errors = (new Srv001Guard())->validate($app);
        $this->assertArrayHasKey('actual_exploration_point_count', $errors);
        $msg = $errors['actual_exploration_point_count'];
        $this->assertStringContainsString('5', $msg,
            'Error message must expose the manual-derived minimum');
    }

    public function test_actual_meets_minimum_passes_and_persists_matrix_outputs(): void
    {
        // floors=8, area=1200 → 6 points, 64 lm
        $app = $this->draft([
            'project_sector' => 'خاص',
            'floor_count'    => 8,
            'floor_area'     => 1200,
            'actual_exploration_point_count' => 6,
        ]);
        $errors = (new Srv001Guard())->validate($app);
        $this->assertSame([], $errors);

        $app->refresh();
        $this->assertSame(
            ExplorationRequirementMatrix::STATUS_CALCULATED,
            $app->data['exploration_requirement_status'],
        );
        $this->assertSame(6,  $app->data['minimum_exploration_point_count']);
        $this->assertSame(64, $app->data['minimum_total_depth_lm']);
        $this->assertFalse($app->data['technical_review_required']);
    }

    public function test_area_above_1200_triggers_special_study_flag(): void
    {
        $app = $this->draft([
            'project_sector' => 'خاص',
            'floor_count'    => 3,
            'floor_area'     => 1500,
            'actual_exploration_point_count' => 20, // any value — guard does not enforce min in special-study
        ]);
        $errors = (new Srv001Guard())->validate($app);
        $this->assertSame([], $errors,
            'SPECIAL_STUDY_REQUIRED must not raise a submission error');

        $app->refresh();
        $this->assertSame(
            ExplorationRequirementMatrix::STATUS_SPECIAL_STUDY_REQUIRED,
            $app->data['exploration_requirement_status'],
        );
        $this->assertTrue($app->data['technical_review_required'],
            'special-study apps must route to technical review');
        $this->assertNull($app->data['minimum_exploration_point_count'],
            'special-study apps must NEVER carry an invented minimum');
    }

    public function test_floor_count_9_or_more_triggers_special_study(): void
    {
        $app = $this->draft([
            'project_sector' => 'خاص',
            'floor_count'    => 12,
            'floor_area'     => 500,
            'actual_exploration_point_count' => 10,
        ]);
        $errors = (new Srv001Guard())->validate($app);
        $this->assertSame([], $errors);

        $app->refresh();
        $this->assertSame(
            ExplorationRequirementMatrix::STATUS_SPECIAL_STUDY_REQUIRED,
            $app->data['exploration_requirement_status'],
        );
        $this->assertTrue($app->data['technical_review_required']);
    }

    // ─── Meeting 2026-07-26 §X + §XI enrichment ─────────────────────

    public function test_meeting_wells_and_depth_are_persisted_on_calculated_path(): void
    {
        // floors=5, area=800 → matrix: 4 points, 32 lm.
        // §X: 800 m² → 4 wells (band a_601_800). §XI: 5 floors → total=12.
        $app = $this->draft([
            'project_sector' => 'خاص',
            'floor_count'    => 5,
            'floor_area'     => 800,
            'actual_exploration_point_count' => 4,
        ]);
        (new Srv001Guard())->validate($app);
        $app->refresh();

        $this->assertSame(4,           $app->data['meeting_wells_count']);
        $this->assertSame('a_601_800', $app->data['meeting_wells_band']);
        $this->assertSame(5,           $app->data['meeting_net_depth_third_m']);
        $this->assertSame(8,           $app->data['meeting_net_depth_two_thirds_m']);
        $this->assertSame(12,          $app->data['meeting_net_depth_total_m']);
    }

    public function test_meeting_wells_persisted_even_when_matrix_defers_to_special_study(): void
    {
        // area=1500 m² is out of the matrix (>1200) → SPECIAL_STUDY,
        // but §X extends: 1500 → 6 + ceil(300/300) = 7 wells.
        // The meeting engine gives a computed value the matrix can't.
        $app = $this->draft([
            'project_sector' => 'خاص',
            'floor_count'    => 3,
            'floor_area'     => 1500,
            'actual_exploration_point_count' => 20,
        ]);
        (new Srv001Guard())->validate($app);
        $app->refresh();

        $this->assertSame(
            ExplorationRequirementMatrix::STATUS_SPECIAL_STUDY_REQUIRED,
            $app->data['exploration_requirement_status'],
        );
        $this->assertSame(7,             $app->data['meeting_wells_count']);
        $this->assertSame('a_1201_3000', $app->data['meeting_wells_band']);
        $this->assertSame(9,             $app->data['meeting_net_depth_total_m']);
    }

    public function test_meeting_depth_absent_when_floors_below_three(): void
    {
        // floors=2 → NetDepthTable returns INELIGIBLE, so the meeting
        // depth keys should not be added to app.data.
        // (floors=2 also causes matrix to give real values for the 3-row
        // since matrix maps 1..3 to the "3 أو أقل" row.)
        $app = $this->draft([
            'project_sector' => 'خاص',
            'floor_count'    => 2,
            'floor_area'     => 500,
            'actual_exploration_point_count' => 3,
        ]);
        (new Srv001Guard())->validate($app);
        $app->refresh();

        // Wells still computed (§X is floor-independent).
        $this->assertSame(3, $app->data['meeting_wells_count']);
        // Depth intentionally missing when floors < 3.
        $this->assertArrayNotHasKey('meeting_net_depth_total_m', $app->data);
    }

    public function test_guard_is_no_op_for_non_srv001_services(): void
    {
        $drw = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-002')
            ->firstOrFail();

        $app = Application::create([
            'reference_number'      => Application::generateReference($drw),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $drw->id,
            'applicant_id'          => $this->applicant->id,
            'status'                => Application::STATUS_DRAFT,
            'data'                  => ['project_sector' => 'حكومي'],
            'fee_amount'            => 0,
            'payment_status'        => 'waived',
        ]);

        // Even though data has project_sector=حكومي, the guard must not
        // intervene on DRW-P-002 — routing is SRV-001-specific.
        $this->assertSame([], (new Srv001Guard())->validate($app));
    }

    /** @param  array<string,mixed>  $data */
    private function draft(array $data): Application
    {
        $srv001 = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'SRV-001')
            ->firstOrFail();

        return Application::create([
            'reference_number'      => Application::generateReference($srv001),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $srv001->id,
            'applicant_id'          => $this->applicant->id,
            'status'                => Application::STATUS_DRAFT,
            'data'                  => $data,
            'fee_amount'            => 0,
            'payment_status'        => 'waived',
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
