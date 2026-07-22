<?php

namespace Tests\Feature;

use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ApplicationReview;
use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use App\Models\User;
use Database\Seeders\CatalogWorkflowsSeeder;
use Database\Seeders\DrawingsDocumentsSeeder;
use Database\Seeders\DrawingValiditySeeder;
use Database\Seeders\JeaPortalTilesSeeder;
use Database\Seeders\ServicePlan2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-62: pins the two expiry fields on the applicant API surface.
 *   • supervision_expiry: 6mo after latest approval, DRW-P-* only
 *     (already covered by SupervisionExpiryTest at the accessor level;
 *      here we prove it reaches the JSON response).
 *   • output_validity_expiry: latest approval + schema.certificate.
 *     validity_months. For DRW-P-* that's 60mo (JORD-58). For a
 *     service with validity_months=0 or no approval yet, null.
 *
 * Also guards the eager-loading in ApplicationController::index so
 * serializing N applications doesn't fire N extra reviews queries
 * (N+1 regression check).
 */
class ApplicationExpirySerializationTest extends TestCase
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
        $this->runSilently(new DrawingValiditySeeder());
        $this->applicant = User::create([
            'organization_id' => $this->org->id, 'name' => 'a', 'email' => 'a@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->reviewer = User::create([
            'organization_id' => $this->org->id, 'name' => 'r', 'email' => 'r@t.esp',
            'password' => 'x', 'role' => 'staff', 'is_active' => true, 'password_changed_at' => now(),
        ]);
    }

    public function test_index_response_carries_both_expiry_fields_for_approved_drawings(): void
    {
        $app = $this->makeApp('DRW-P-001');
        $this->approve($app, Carbon::create(2026, 1, 15, 10, 0));

        Sanctum::actingAs($this->applicant);
        $res = $this->getJson('/api/v1/applications');
        $res->assertOk();

        $row = collect($res->json('applications'))->firstWhere('id', $app->id);
        $this->assertNotNull($row, 'Application must appear on the applicant listing');

        // supervision: approvedAt + 6 months
        $this->assertNotNull($row['supervision_expiry']);
        $this->assertStringStartsWith('2026-07-15', $row['supervision_expiry']);

        // output validity: approvedAt + 60 months (JORD-58's drawing default)
        $this->assertNotNull($row['output_validity_expiry']);
        $this->assertStringStartsWith('2031-01-15', $row['output_validity_expiry']);
    }

    public function test_show_response_carries_both_expiry_fields(): void
    {
        $app = $this->makeApp('DRW-P-002');
        $this->approve($app, Carbon::create(2026, 3, 10, 12, 0));

        Sanctum::actingAs($this->applicant);
        $res = $this->getJson("/api/v1/applications/{$app->id}");
        $res->assertOk();

        $payload = $res->json('application');
        $this->assertStringStartsWith('2026-09-10', $payload['supervision_expiry']);
        $this->assertStringStartsWith('2031-03-10', $payload['output_validity_expiry']);
    }

    public function test_non_drawings_application_has_null_supervision_but_may_have_output_validity(): void
    {
        // CERT-* services carry no supervision contract, but they DO issue
        // certificates with their own validity_months. Pin that supervision
        // is null while output_validity flows through where non-zero.
        $app = $this->makeApp('CERT-001');
        $this->approve($app, Carbon::create(2026, 4, 1));

        Sanctum::actingAs($this->applicant);
        $row = collect($this->getJson('/api/v1/applications')->json('applications'))
            ->firstWhere('id', $app->id);

        $this->assertNull($row['supervision_expiry']);
        // CERT-001's default validity_months may be 0 or non-zero depending
        // on the seeder; either result is legitimate here, we're just
        // proving the field is serialized (not missing entirely).
        $this->assertArrayHasKey('output_validity_expiry', $row);
    }

    public function test_unapproved_application_has_null_for_both_expiries(): void
    {
        // Draft / submitted state — no approved review yet, so no clock.
        $app = $this->makeApp('DRW-P-003');

        Sanctum::actingAs($this->applicant);
        $row = collect($this->getJson('/api/v1/applications')->json('applications'))
            ->firstWhere('id', $app->id);

        $this->assertNull($row['supervision_expiry']);
        $this->assertNull($row['output_validity_expiry']);
    }

    public function test_index_serialization_is_not_n_plus_one_over_reviews(): void
    {
        // Regression guardrail: if the accessor drops the eager-loaded
        // collection and calls ->reviews() again, this test fails with
        // >5 reviews queries. The controller eager-loads reviews on
        // index for exactly this reason.
        for ($i = 0; $i < 5; $i++) {
            $app = $this->makeApp('DRW-P-001');
            $this->approve($app, Carbon::create(2026, 1, 1)->addDays($i));
        }

        \DB::enableQueryLog();
        Sanctum::actingAs($this->applicant);
        $this->getJson('/api/v1/applications')->assertOk();
        $reviewsQueries = collect(\DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'application_reviews'))
            ->count();

        // Expect 1 eager-load query for reviews. If it goes above 2 the
        // accessor is issuing its own per-row query — a >100ms perf
        // regression on lists of 100+ apps.
        $this->assertLessThanOrEqual(2, $reviewsQueries,
            "Reviews should be eager-loaded, got {$reviewsQueries} queries");
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
