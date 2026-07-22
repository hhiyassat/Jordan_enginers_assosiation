<?php

namespace Tests\Feature;

use Modules\JeaServices\Models\Application;
use App\Models\Organization;
use Modules\JeaProjects\Models\Project;
use Modules\JeaServices\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pins the project ↔ application link — the Apply page uses this to
 * render the project's read-only header instead of asking the applicant
 * to re-type project fields. Also locks the cross-user / cross-org
 * checks: the FormRequest's `exists:` rule only proves a project row
 * exists globally, so the controller adds an ownership + org check that
 * closes the escalation vector.
 */
class ApplicationProjectLinkTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $applicant;
    private ServiceDefinition $service;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org', 'slug' => 'org', 'is_active' => true,
        ]);
        $this->applicant = User::create([
            'organization_id' => $this->org->id,
            'name'  => 'ahmed', 'email' => 'ahmed@t.esp',
            'password' => Hash::make('Secret123!'),
            'role'  => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code' => 'DRW-P-001', 'name_ar' => 'مخططات الأبنية المقترحة', 'name_en' => 'Proposed Buildings',
            'currency' => 'JOD', 'status' => 'active', 'is_locked' => false,
            'schema' => [
                'workflow' => ['stages' => [[
                    'id' => 'review', 'label_ar' => 'مراجعة',
                    'role' => 'staff', 'sla_hours' => 24,
                    'actions' => ['approve', 'reject'],
                ]]],
            ],
        ]);
        $this->project = Project::create([
            'organization_id' => $this->org->id,
            'owner_user_id'  => $this->applicant->id,
            'name_ar' => 'إسكان حسين', 'name_en' => 'Hussein Housing',
            'contract_no' => 'C-100', 'request_no' => 'R-200',
            'city' => 'عمّان', 'area_m2' => 350, 'type' => 'سكني',
        ]);
    }

    public function test_store_persists_project_id_when_owned_by_applicant(): void
    {
        Sanctum::actingAs($this->applicant);
        $this->postJson('/api/v1/applications', [
            'service_code' => $this->service->code,
            'data'         => ['project_name' => 'x'],
            'project_id'   => $this->project->id,
        ])->assertCreated();

        $app = Application::latest('id')->first();
        $this->assertSame($this->project->id, $app->project_id);
    }

    public function test_store_accepts_empty_data_at_draft_stage(): void
    {
        // Regression: applicants clicking "Next → Documents" on a service
        // whose schema has no applicant fields (e.g. drawing services where
        // everything comes from the linked project + uploads) used to hit
        // "بيانات الطلب مطلوبة." because the FormRequest treated `{}` as
        // missing. `present` allows the key to be empty at draft save;
        // SchemaValidator handles per-field enforcement on submit.
        Sanctum::actingAs($this->applicant);
        $this->postJson('/api/v1/applications', [
            'service_code' => $this->service->code,
            'data'         => [],
            'project_id'   => $this->project->id,
        ])->assertCreated();
    }

    public function test_store_still_rejects_a_missing_data_key(): void
    {
        // `present` requires the key to be posted — even if empty. Sending
        // no `data` at all is still a client bug.
        Sanctum::actingAs($this->applicant);
        $this->postJson('/api/v1/applications', [
            'service_code' => $this->service->code,
            'project_id'   => $this->project->id,
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['data']);
    }

    public function test_update_accepts_empty_data_on_a_draft_with_arabic_message_on_missing(): void
    {
        // Sister regression for the update path — previously PUT
        // /applications/{id} used inline `required, array` which flashed
        // Laravel's default English "The data field is required" through
        // the Apply banner. Empty {} should succeed; a truly missing data
        // key should 422 with the Arabic message set explicitly.
        $app = Application::create([
            'reference_number'      => 'A-TEST-02',
            'organization_id'       => $this->org->id,
            'service_definition_id' => $this->service->id,
            'project_id'            => $this->project->id,
            'applicant_id'          => $this->applicant->id,
            'status'                => Application::STATUS_DRAFT,
            'data'                  => [],
            'fee_amount'            => 0,
            'payment_status'        => 'waived',
        ]);
        Sanctum::actingAs($this->applicant);

        // Empty object — should pass.
        $this->putJson("/api/v1/applications/{$app->id}", ['data' => (object) []])->assertOk();

        // Truly missing key — 422 with Arabic message.
        $res = $this->putJson("/api/v1/applications/{$app->id}", []);
        $res->assertStatus(422)
            ->assertJsonValidationErrors(['data']);
        $this->assertStringNotContainsString('The data field is required', json_encode($res->json()),
            'The Apply banner reads this message directly — must never be raw English.');
    }

    public function test_store_omitting_project_id_leaves_it_null(): void
    {
        Sanctum::actingAs($this->applicant);
        $this->postJson('/api/v1/applications', [
            'service_code' => $this->service->code,
            'data'         => ['project_name' => 'x'],
        ])->assertCreated();

        $this->assertNull(Application::latest('id')->first()->project_id);
    }

    public function test_store_rejects_project_belonging_to_a_different_user(): void
    {
        // A different applicant in the SAME org owns the project.
        $other = User::create([
            'organization_id' => $this->org->id,
            'name' => 'other', 'email' => 'other@t.esp',
            'password' => Hash::make('Secret123!'),
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $otherProject = Project::create([
            'organization_id' => $this->org->id,
            'owner_user_id'  => $other->id,
            'name_ar' => 'مشروع الغير', 'name_en' => 'Other',
        ]);

        Sanctum::actingAs($this->applicant);
        $this->postJson('/api/v1/applications', [
            'service_code' => $this->service->code,
            'data'         => ['project_name' => 'x'],
            'project_id'   => $otherProject->id,
        ])->assertStatus(422)
          ->assertJsonPath('errors.project_id.0', fn($m) => str_contains($m, 'المشروع'));

        $this->assertSame(0, Application::count(), 'No application should have been created');
    }

    public function test_store_rejects_project_from_a_different_organization(): void
    {
        $otherOrg = Organization::create([
            'name_ar' => 'other','name_en' => 'other','slug' => 'other','is_active' => true,
        ]);
        $otherProject = Project::create([
            'organization_id' => $otherOrg->id,
            'owner_user_id'  => $this->applicant->id,
            'name_ar' => 'مشروع خارجي', 'name_en' => 'External',
        ]);

        Sanctum::actingAs($this->applicant);
        $this->postJson('/api/v1/applications', [
            'service_code' => $this->service->code,
            'data'         => ['project_name' => 'x'],
            'project_id'   => $otherProject->id,
        ])->assertStatus(422);
    }

    public function test_store_rejects_non_existent_project(): void
    {
        Sanctum::actingAs($this->applicant);
        $this->postJson('/api/v1/applications', [
            'service_code' => $this->service->code,
            'data'         => ['project_name' => 'x'],
            'project_id'   => 99999,
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['project_id']);
    }

    public function test_show_includes_the_linked_project(): void
    {
        // Reference number sequence depends on ServiceDefinition::generateReference;
        // create the app directly here so the test doesn't couple to that logic.
        $app = Application::create([
            'reference_number'      => 'A-TEST-01',
            'organization_id'       => $this->org->id,
            'service_definition_id' => $this->service->id,
            'project_id'            => $this->project->id,
            'applicant_id'          => $this->applicant->id,
            'status'                => Application::STATUS_DRAFT,
            'data'                  => [],
            'fee_amount'            => 0,
            'payment_status'        => 'waived',
        ]);

        Sanctum::actingAs($this->applicant);
        $r = $this->getJson("/api/v1/applications/{$app->id}");
        $r->assertOk();
        $this->assertSame($this->project->id, $r->json('application.project.id'),
            'GET /applications/{id} must eager-load the linked project');
        $this->assertSame('إسكان حسين', $r->json('application.project.name_ar'));
    }
}
