<?php

namespace Tests\Feature;

use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ApplicationReview;
use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use App\Models\User;
use Modules\JeaServices\Database\Seeders\CatalogWorkflowsSeeder;
use Modules\JeaServices\Database\Seeders\DrawingsDocumentsSeeder;
use Modules\JeaServices\Database\Seeders\JeaPortalTilesSeeder;
use Modules\JeaServices\Database\Seeders\ServicePlan2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * JORD-59: pins the supervision-contract 6-month window from the JEA
 * 2025 manual p. 27. The accessor Application::supervisionExpiry only
 * fires when (a) the service is under JEA-PROJ, (b) its schema
 * declares supervision_services_agreement (post-JORD-54 every DRW-P-*
 * does), and (c) at least one approved-decision review exists.
 */
class SupervisionExpiryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $applicant;
    private User $reviewer;

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
        $this->applicant = User::create([
            'organization_id' => $this->org->id, 'name' => 'a', 'email' => 'a@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->reviewer = User::create([
            'organization_id' => $this->org->id, 'name' => 'r', 'email' => 'r@t.esp',
            'password' => 'x', 'role' => 'staff', 'is_active' => true, 'password_changed_at' => now(),
        ]);
    }

    public function test_accessor_returns_six_months_after_the_approval_review(): void
    {
        $app = $this->makeApp('DRW-P-001');
        $approvedAt = Carbon::create(2026, 1, 15, 10, 0);
        $this->approve($app, $approvedAt);

        $expiry = $app->refresh()->supervision_expiry;

        $this->assertNotNull($expiry);
        $this->assertTrue($expiry->equalTo($approvedAt->copy()->addMonths(6)),
            'Supervision expiry must be exactly approvedAt + 6 months');
    }

    public function test_accessor_uses_the_latest_approved_review_when_multiple_exist(): void
    {
        // Multi-stage drawings flow: first_review approves, second_review
        // approves, payment_stage approves. Anchor MUST be the final one
        // — otherwise re-review at stage 2 would silently shorten the
        // supervision window.
        $app = $this->makeApp('DRW-P-002');
        $this->approve($app, Carbon::create(2026, 1, 1, 9, 0));
        $this->approve($app, Carbon::create(2026, 2, 1, 9, 0)); // final
        $this->approve($app, Carbon::create(2025, 12, 1, 9, 0)); // earlier — must not win

        $this->assertEquals(
            Carbon::create(2026, 8, 1, 9, 0),
            $app->refresh()->supervision_expiry,
            'Latest approved-decision review wins'
        );
    }

    public function test_accessor_returns_null_when_no_review_is_approved_yet(): void
    {
        $app = $this->makeApp('DRW-P-003');
        // A pending-review row exists but the decision isn't 'approved'.
        ApplicationReview::create([
            'application_id' => $app->id,
            'reviewer_id'    => $this->reviewer->id,
            'stage_id'       => 'first_review',
            'decision'       => 'modifications_requested',
            'notes'          => 'needs work',
            'review_round'   => 1,
        ]);
        $this->assertNull($app->refresh()->supervision_expiry,
            'No approved review → no supervision clock');
    }

    public function test_accessor_returns_null_for_non_JEA_PROJ_services(): void
    {
        // Certificate services don't produce drawings; supervision
        // window doesn't apply even if a review approved the request.
        $app = $this->makeApp('CERT-001');
        $this->approve($app, now()->subDays(30));
        $this->assertNull($app->refresh()->supervision_expiry);
    }

    public function test_env_override_moves_the_window(): void
    {
        // Ops needs a 12-month window per a specific tender's terms.
        config(['esp.supervision_window_months' => 12]);

        $app = $this->makeApp('DRW-P-004');
        $approvedAt = Carbon::create(2026, 3, 10, 12, 0);
        $this->approve($app, $approvedAt);

        $this->assertEquals(
            $approvedAt->copy()->addMonths(12),
            $app->refresh()->supervision_expiry,
            'Override in config must reach the accessor'
        );
    }

    public function test_accessor_returns_null_when_schema_omits_supervision_doc(): void
    {
        // A bespoke DRW-P-* variant that removes supervision_services_agreement
        // from its document list should NOT get an auto-expiry — the
        // manual's 6-month rule is tied to that specific contract.
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', 'DRW-P-005')->first();
        $schema = $svc->schema;
        $schema['documents'] = collect($schema['documents'])
            ->reject(fn ($d) => ($d['id'] ?? '') === 'supervision_services_agreement')
            ->values()->all();
        $svc->update(['schema' => $schema]);

        $app = $this->makeApp('DRW-P-005');
        $this->approve($app, now()->subDays(10));

        $this->assertNull($app->refresh()->supervision_expiry);
    }

    private function makeApp(string $serviceCode): Application
    {
        $svc = ServiceDefinition::where('organization_id', $this->org->id)
            ->where('code', $serviceCode)->first();
        return Application::create([
            'reference_number'      => strtoupper(bin2hex(random_bytes(4))),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $svc->id,
            'applicant_id'          => $this->applicant->id,
            'status'                => Application::STATUS_SUBMITTED,
            'data'                  => [],
            'fee_amount'            => 0,
        ]);
    }

    private function approve(Application $app, Carbon $at): void
    {
        $review = ApplicationReview::create([
            'application_id' => $app->id,
            'reviewer_id'    => $this->reviewer->id,
            'stage_id'       => 'final_review',
            'decision'       => Application::STATUS_APPROVED,
            'notes'          => 'ok',
            'review_round'   => 1,
        ]);
        // Backdate created_at so the "latest by created_at" logic can
        // be tested with deterministic timestamps.
        ApplicationReview::where('id', $review->id)->update([
            'created_at' => $at,
            'updated_at' => $at,
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
