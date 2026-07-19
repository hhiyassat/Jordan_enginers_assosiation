<?php

namespace Tests\Feature;

use App\Engine\WorkflowEngine;
use App\Models\Application;
use App\Models\Organization;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pins the "submit → first reviewer stage" contract that makes staff
 * claim work end-to-end. Regression: submit() used to set
 * current_stage = firstStage.id which was the applicant-owned
 * 'office_submission', so claim() then 403'd every reviewer with
 * "Stage 'office_submission' requires role 'applicant'."
 */
class WorkflowClaimTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $applicant;
    private User $auditor;
    private User $staff;
    private ServiceDefinition $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'org','name_en' => 'org','slug' => 'org','is_active' => true,
        ]);
        $this->applicant = $this->makeUser('applicant', 'app@t.esp');
        $this->auditor   = $this->makeUser('auditor',   'aud@t.esp');
        $this->staff     = $this->makeUser('staff',     'st@t.esp');

        // Mirrors the shape of DRW-P-004 مخططات الهدم — an applicant-owned
        // office_submission followed by reviewer stages. Original bug
        // happened because submit() left current_stage on office_submission.
        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'    => 'DRW-P-004',
            'name_ar' => 'مخططات الهدم',
            'name_en' => 'Demolition',
            'currency'=> 'JOD',
            'status'  => 'active',
            'is_locked' => false,
            'schema' => [
                'workflow' => ['stages' => [
                    ['id' => 'office_submission',    'role' => 'applicant', 'label_ar' => 'تقديم الطلب',  'sla_hours' => 24, 'actions' => ['submit']],
                    ['id' => 'public_safety_review', 'role' => 'auditor',   'label_ar' => 'مراجعة السلامة', 'sla_hours' => 48, 'actions' => ['approve', 'reject']],
                    ['id' => 'payment',              'role' => 'staff',     'label_ar' => 'الدفع',         'sla_hours' => 24, 'actions' => ['confirm_payment']],
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
            'role' => $role, 'is_active' => true,
            'password_changed_at' => now(),
        ]);
    }

    private function draft(): Application
    {
        return Application::create([
            'reference_number'      => 'A-CLAIM-'.random_int(1000, 9999),
            'organization_id'       => $this->org->id,
            'service_definition_id' => $this->service->id,
            'applicant_id'          => $this->applicant->id,
            'status'                => Application::STATUS_DRAFT,
            'current_stage'         => 'office_submission',
            'data'                  => [],
            'fee_amount'            => 0,
            'payment_status'        => 'waived',
        ]);
    }

    public function test_getFirstReviewerStage_skips_applicant_owned_stages(): void
    {
        $stage = $this->service->getFirstReviewerStage();
        $this->assertSame('public_safety_review', $stage['id']);
    }

    public function test_getFirstReviewerStage_falls_back_when_workflow_is_all_applicant(): void
    {
        // Edge case: a workflow entirely owned by the applicant returns
        // the first stage so we don't hand callers null (which claim()
        // would then trip over).
        $this->service->update(['schema' => [
            'workflow' => ['stages' => [
                ['id' => 'only_stage', 'role' => 'applicant', 'label_ar' => '.', 'sla_hours' => 1, 'actions' => ['submit']],
            ]],
        ]]);
        $this->assertSame('only_stage', $this->service->fresh()->getFirstReviewerStage()['id']);
    }

    public function test_submit_advances_current_stage_past_office_submission(): void
    {
        $app = $this->draft();
        (new WorkflowEngine($this->service))->submit($app, $this->applicant);
        $app->refresh();

        $this->assertSame('submitted', $app->status);
        $this->assertSame('public_safety_review', $app->current_stage,
            'submit() must advance current_stage to the first REVIEWER stage — otherwise claim() 403s reviewers');
    }

    public function test_auditor_can_claim_after_submit_end_to_end(): void
    {
        $app = $this->draft();
        (new WorkflowEngine($this->service))->submit($app, $this->applicant);

        // The auditor whose role matches the first reviewer stage claims it.
        Sanctum::actingAs($this->auditor);
        $this->postJson("/api/v1/applications/{$app->id}/claim")->assertOk();

        $app->refresh();
        $this->assertSame('under_review', $app->status);
        $this->assertSame($this->auditor->id, $app->assigned_reviewer_id);
    }

    public function test_staff_cannot_claim_the_auditor_stage(): void
    {
        // Belt-and-braces: the fix moves claim past office_submission, but
        // it MUST still refuse a staff user trying to claim an auditor
        // stage — the role match on the target stage is still enforced.
        $app = $this->draft();
        (new WorkflowEngine($this->service))->submit($app, $this->applicant);

        Sanctum::actingAs($this->staff);
        $this->postJson("/api/v1/applications/{$app->id}/claim")->assertStatus(403);
    }
}
