<?php

namespace Tests\Feature;

use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ApplicationReview;
use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-88 (PM): GET /api/v1/review/dashboard aggregates the four
 * numbers a reviewer needs on login plus two lists (recent decisions,
 * my in-progress). Scoping mirrors the queue endpoint — non-admins
 * only see rows their role can act on.
 */
class ReviewDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $applicant;
    private User $auditor;
    private User $staff;
    private User $admin;
    private ServiceDefinition $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org', 'slug' => 'org', 'is_active' => true,
        ]);
        $this->applicant = $this->makeUser('applicant', 'app@t.esp');
        $this->auditor   = $this->makeUser('auditor',   'aud@t.esp');
        $this->staff     = $this->makeUser('staff',     'st@t.esp');
        $this->admin     = $this->makeUser('admin',     'adm@t.esp');

        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code' => 'DRW-P-001', 'name_ar' => 'draw', 'name_en' => 'draw',
            'currency' => 'JOD', 'status' => 'active', 'is_locked' => false,
            'schema' => [
                'workflow' => ['stages' => [
                    ['id' => 'office_submission',    'role' => 'applicant', 'label_ar' => '.', 'sla_hours' => 24, 'actions' => ['submit']],
                    ['id' => 'public_safety_review', 'role' => 'auditor',   'label_ar' => '.', 'sla_hours' => 48, 'actions' => ['approve','reject']],
                    ['id' => 'payment',              'role' => 'staff',     'label_ar' => '.', 'sla_hours' => 24, 'actions' => ['confirm_payment']],
                ]],
            ],
        ]);
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'organization_id' => $this->org->id,
            'name' => $role, 'email' => $email,
            'password' => Hash::make('Secret123!'),
            'role' => $role, 'is_active' => true, 'password_changed_at' => now(),
        ]);
    }

    private function makeApp(array $overrides = []): Application
    {
        return Application::create(array_merge([
            'reference_number'      => 'A-'.random_int(10000, 99999),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $this->service->id,
            'applicant_id'          => $this->applicant->id,
            'status'                => Application::STATUS_SUBMITTED,
            'current_stage'         => 'public_safety_review',
            'data'                  => [],
            'fee_amount'            => 0,
            'payment_status'        => 'waived',
        ], $overrides));
    }

    public function test_403_for_applicants(): void
    {
        Sanctum::actingAs($this->applicant);
        $this->getJson('/api/v1/review/dashboard')->assertForbidden();
    }

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/review/dashboard')->assertUnauthorized();
    }

    public function test_returns_the_four_headline_counts(): void
    {
        // 2 queue-available (submitted + no reviewer + role matches auditor).
        $this->makeApp();
        $this->makeApp();
        // 1 currently claimed by me.
        $this->makeApp([
            'status' => Application::STATUS_UNDER_REVIEW,
            'assigned_reviewer_id' => $this->auditor->id,
            'sla_deadline' => now()->addDay(),
        ]);
        // 1 overdue (past sla_deadline).
        $this->makeApp([
            'status' => Application::STATUS_UNDER_REVIEW,
            'assigned_reviewer_id' => $this->auditor->id,
            'sla_deadline' => now()->subHour(),
        ]);

        Sanctum::actingAs($this->auditor);
        $res = $this->getJson('/api/v1/review/dashboard')->assertOk();

        $this->assertSame(2, $res->json('stats.my_in_progress'));
        $this->assertSame(2, $res->json('stats.queue_available'));
        $this->assertSame(1, $res->json('stats.overdue'));
        $this->assertSame(0, $res->json('stats.decided_this_week'));
    }

    public function test_non_admin_reviewer_only_sees_rows_their_role_can_act_on(): void
    {
        // Two submitted rows: one on the auditor stage, one on the staff stage.
        $this->makeApp(['current_stage' => 'public_safety_review']);
        $this->makeApp(['current_stage' => 'payment']);

        Sanctum::actingAs($this->auditor);
        $res = $this->getJson('/api/v1/review/dashboard')->assertOk();
        $this->assertSame(1, $res->json('stats.queue_available'), 'auditor must not see the payment-stage row');

        Sanctum::actingAs($this->staff);
        $res = $this->getJson('/api/v1/review/dashboard')->assertOk();
        $this->assertSame(1, $res->json('stats.queue_available'), 'staff must not see the auditor-stage row');
    }

    public function test_admin_sees_org_wide_queue_regardless_of_stage_role(): void
    {
        // Same two apps on different-role stages — admin sees both.
        $this->makeApp(['current_stage' => 'public_safety_review']);
        $this->makeApp(['current_stage' => 'payment']);

        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/review/dashboard')->assertOk();
        $this->assertSame(2, $res->json('stats.queue_available'));
    }

    public function test_decided_this_week_and_by_decision_breakdown(): void
    {
        // Seed three reviews I made this month.
        $app1 = $this->makeApp();
        $app2 = $this->makeApp();
        $app3 = $this->makeApp();
        foreach ([
            [$app1, 'approved'],
            [$app2, 'rejected'],
            [$app3, 'modifications_requested'],
        ] as [$app, $decision]) {
            ApplicationReview::create([
                'application_id' => $app->id,
                'reviewer_id'    => $this->auditor->id,
                'stage_id'       => 'public_safety_review',
                'decision'       => $decision,
                'notes'          => $decision === 'approved' ? null : 'x'.str_repeat('a', 20),
                'review_round'   => 1,
            ]);
        }

        Sanctum::actingAs($this->auditor);
        $res = $this->getJson('/api/v1/review/dashboard')->assertOk();
        $this->assertSame(3, $res->json('stats.decided_this_week'));
        $this->assertSame(3, $res->json('stats.decided_this_month'));
        $this->assertSame(1, $res->json('by_decision_this_month.approved'));
        $this->assertSame(1, $res->json('by_decision_this_month.rejected'));
        $this->assertSame(1, $res->json('by_decision_this_month.modifications_requested'));
    }

    public function test_recent_decisions_are_capped_at_five_and_newest_first(): void
    {
        // Eloquent overrides created_at at insert-time when the model
        // has $timestamps=true (default), so create()'s explicit
        // created_at is ignored. Insert directly to bypass that and
        // give each row a distinct backdated timestamp.
        for ($i = 0; $i < 7; $i++) {
            $app = $this->makeApp();
            \DB::table('application_reviews')->insert([
                'application_id' => $app->id, 'reviewer_id' => $this->auditor->id,
                'stage_id' => 'public_safety_review', 'decision' => 'approved',
                'notes' => null, 'review_round' => 1,
                'created_at' => now()->subDays(7 - $i),
                'updated_at' => now()->subDays(7 - $i),
            ]);
        }
        Sanctum::actingAs($this->auditor);
        $res = $this->getJson('/api/v1/review/dashboard')->assertOk();
        $recent = $res->json('recent_decisions');
        $this->assertCount(5, $recent);
        // Newest first — created_at of the first row should be later than the last.
        $this->assertGreaterThan(
            strtotime($recent[count($recent) - 1]['created_at']),
            strtotime($recent[0]['created_at']),
        );
    }

    public function test_my_in_progress_list_is_capped_at_five(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->makeApp([
                'status' => Application::STATUS_UNDER_REVIEW,
                'assigned_reviewer_id' => $this->auditor->id,
                'sla_deadline' => now()->addHours($i + 1),
            ]);
        }
        Sanctum::actingAs($this->auditor);
        $res = $this->getJson('/api/v1/review/dashboard')->assertOk();
        $this->assertCount(5, $res->json('my_in_progress'));
        // But the summary count remains the true total.
        $this->assertSame(7, $res->json('stats.my_in_progress'));
    }
}
