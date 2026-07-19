<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Engine\WorkflowEngine;
use App\Models\Application;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-9: full end-to-end notification flow — WorkflowEngine hooks +
 * inbox endpoints + per-user scoping + unread counter.
 */
class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $applicant;
    private User $reviewer;
    private ServiceDefinition $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org',
            'slug' => 'org-notify-' . uniqid(),
            'is_active' => true,
        ]);
        $this->applicant = $this->makeUser('applicant', 'ap-notify@t.esp');
        $this->reviewer  = $this->makeUser('staff',     'st-notify@t.esp');
        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'    => 'DRW-NOTIFY-1',
            'name_ar' => 'خدمة', 'name_en' => 'Svc',
            'currency' => 'JOD',
            'status' => 'active', 'is_locked' => false,
            'schema' => ['workflow' => ['stages' => [
                ['id' => 'office_submission', 'role' => 'applicant', 'label_ar' => 'تقديم', 'sla_hours' => 24, 'actions' => ['submit']],
                ['id' => 'review',            'role' => 'staff',     'label_ar' => 'مراجعة', 'sla_hours' => 48, 'actions' => ['approve', 'reject', 'request_modifications']],
            ]]],
        ]);
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'organization_id' => $this->org->id,
            'name' => $role, 'email' => $email,
            'password' => Hash::make('Secret123!'),
            'role' => $role, 'is_active' => true,
            'password_changed_at' => now(),
        ]);
    }

    private function draft(): Application
    {
        return Application::create([
            'reference_number' => 'A-N-' . random_int(1000, 9999),
            'organization_id' => $this->org->id,
            'service_definition_id' => $this->service->id,
            'applicant_id' => $this->applicant->id,
            'status' => Application::STATUS_DRAFT,
            'current_stage' => 'office_submission',
            'data' => [], 'fee_amount' => 0,
            'payment_status' => 'waived',
        ]);
    }

    public function test_workflow_submit_emits_a_notification_to_the_applicant(): void
    {
        $app = $this->draft();
        (new WorkflowEngine($this->service))->submit($app, $this->applicant);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->applicant->id,
            'type'    => 'application.submitted',
        ]);
    }

    public function test_workflow_final_approve_emits_a_notification(): void
    {
        $app = $this->draft();
        $engine = new WorkflowEngine($this->service);
        $engine->submit($app, $this->applicant);

        // Reviewer claims + decides.
        $app = $app->fresh();
        $app->assigned_reviewer_id = $this->reviewer->id;
        $app->status = Application::STATUS_UNDER_REVIEW;
        $app->save();

        // Approving the LAST review stage is a final decision — the fixture
        // above has a single 'review' stage after the applicant stage.
        $engine->decide($app, $this->reviewer, 'approved');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->applicant->id,
            'type'    => 'application.approved',
        ]);
    }

    public function test_index_returns_only_the_callers_notifications(): void
    {
        Notification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->applicant->id,
            'type' => 'application.submitted', 'title' => 'you', 'body' => 'x',
        ]);
        Notification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->reviewer->id,
            'type' => 'application.submitted', 'title' => 'someone else', 'body' => 'x',
        ]);
        Sanctum::actingAs($this->applicant);
        $res = $this->getJson('/api/v1/notifications');
        $res->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'you');
    }

    public function test_unread_count_returns_only_unread_for_caller(): void
    {
        Notification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->applicant->id, 'type' => 't', 'title' => 'a', 'body' => 'x',
            'read_at' => null,
        ]);
        Notification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->applicant->id, 'type' => 't', 'title' => 'b', 'body' => 'x',
            'read_at' => null,
        ]);
        Notification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->applicant->id, 'type' => 't', 'title' => 'c', 'body' => 'x',
            'read_at' => now(),
        ]);
        Sanctum::actingAs($this->applicant);
        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()->assertJsonPath('count', 2);
    }

    public function test_mark_read_sets_read_at_and_ignores_other_users_notifications(): void
    {
        $mine = Notification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->applicant->id, 'type' => 't', 'title' => 'a', 'body' => 'x',
        ]);
        $someone_elses = Notification::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->reviewer->id, 'type' => 't', 'title' => 'b', 'body' => 'x',
        ]);
        Sanctum::actingAs($this->applicant);

        $this->postJson("/api/v1/notifications/{$mine->id}/read")->assertOk();
        $this->assertNotNull($mine->fresh()->read_at);

        // Trying to mark someone else's notification 404s (findOrFail on the
        // forUser-scoped query).
        $this->postJson("/api/v1/notifications/{$someone_elses->id}/read")
             ->assertStatus(404);
        $this->assertNull($someone_elses->fresh()->read_at);
    }

    public function test_mark_all_read_touches_only_callers_unread(): void
    {
        Notification::create(['organization_id' => $this->org->id, 'user_id' => $this->applicant->id, 'type' => 't', 'title' => 'a', 'body' => 'x']);
        Notification::create(['organization_id' => $this->org->id, 'user_id' => $this->applicant->id, 'type' => 't', 'title' => 'b', 'body' => 'x']);
        Notification::create(['organization_id' => $this->org->id, 'user_id' => $this->reviewer->id,  'type' => 't', 'title' => 'c', 'body' => 'x']);

        Sanctum::actingAs($this->applicant);
        $this->postJson('/api/v1/notifications/read-all')
             ->assertOk()->assertJsonPath('updated', 2);

        $this->assertSame(0, Notification::forUser($this->applicant)->unread()->count());
        $this->assertSame(1, Notification::forUser($this->reviewer)->unread()->count());
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/notifications')->assertStatus(401);
        $this->getJson('/api/v1/notifications/unread-count')->assertStatus(401);
        $this->postJson('/api/v1/notifications/1/read')->assertStatus(401);
        $this->postJson('/api/v1/notifications/read-all')->assertStatus(401);
    }
}
