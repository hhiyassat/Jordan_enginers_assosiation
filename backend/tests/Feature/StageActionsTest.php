<?php

namespace Tests\Feature;

use Modules\JeaServices\Engine\StageActions;
use Modules\JeaServices\Models\Application;
use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * StageActions is the single source of truth for what each schema action
 * id means (label, notes requirement, role, resulting Application status).
 * These tests lock the invariants so a future edit can't accidentally
 * change the semantics of a well-known action.
 */
class StageActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_describe_returns_null_for_unknown_action(): void
    {
        $this->assertNull(StageActions::describe('nonexistent_action'));
    }

    public function test_approve_action_maps_to_approved_status(): void
    {
        $desc = StageActions::describe('approve');
        $this->assertSame(Application::STATUS_APPROVED, $desc['decision']);
        $this->assertFalse($desc['requires_notes']);
        $this->assertSame('success', $desc['variant']);
    }

    public function test_reject_action_requires_notes(): void
    {
        $desc = StageActions::describe('reject');
        $this->assertSame(Application::STATUS_REJECTED, $desc['decision']);
        $this->assertTrue($desc['requires_notes']);
        $this->assertSame('danger', $desc['variant']);
    }

    public function test_override_first_auditor_carries_annotation_and_maps_to_approve(): void
    {
        $desc = StageActions::describe('override_first_auditor');
        $this->assertSame(Application::STATUS_APPROVED, $desc['decision']);
        $this->assertTrue($desc['requires_notes']);
        $this->assertArrayHasKey('override_first_auditor', $desc['annotation']);
        $this->assertTrue($desc['annotation']['override_first_auditor']);
        // Auditor / admin only — staff should be filtered out.
        $this->assertNotContains('staff', $desc['allowed_roles']);
    }

    public function test_applicant_actions_are_hidden_from_reviewers(): void
    {
        // 'submit' is applicant-only. availableFor filters by role.
        $forStaff = StageActions::availableFor(['submit', 'approve', 'reject'], 'staff');
        $ids = array_column($forStaff, 'id');
        $this->assertNotContains('submit', $ids);
        $this->assertContains('approve', $ids);
        $this->assertContains('reject', $ids);
    }

    public function test_unknown_action_ids_are_silently_skipped(): void
    {
        // A drifted schema shouldn't crash the reviewer console.
        $result = StageActions::availableFor(['approve', 'made_up_action', 'reject'], 'staff');
        $this->assertCount(2, $result);
    }

    public function test_role_filter_null_returns_everything(): void
    {
        $result = StageActions::availableFor(['submit', 'approve', 'reject'], null);
        $this->assertCount(3, $result);
    }

    public function test_for_application_pulls_actions_from_current_stage(): void
    {
        $org = Organization::create(['name_ar' => 'x', 'name_en' => 'x', 'slug' => 'x', 'is_active' => true]);
        $user = User::create([
            'organization_id' => $org->id, 'name' => 'A', 'email' => 'a@t.dev', 'password' => 'x',
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);

        $service = ServiceDefinition::create([
            'organization_id' => $org->id,
            'code' => 'TEST-001',
            'name_ar' => 'x', 'name_en' => 'x',
            'currency' => 'JOD',
            'status' => 'active',
            'schema' => [
                'workflow' => [
                    'stages' => [
                        ['id' => 'review', 'label_ar' => 'r', 'label_en' => 'r', 'role' => 'auditor', 'sla_hours' => 24,
                         'actions' => ['approve', 'reject', 'override_first_auditor']],
                    ],
                ],
                'fee'         => ['type' => 'fixed', 'amount' => 0, 'currency' => 'JOD'],
                'fields'      => [], 'documents' => [],
                'certificate' => ['validity_months' => 0, 'title_ar' => 'x', 'title_en' => 'x', 'fields_on_cert' => []],
            ],
        ]);

        $app = Application::create([
            'reference_number'      => Application::generateReference($service),
            'organization_id'       => $org->id,
            'service_definition_id' => $service->id,
            'applicant_id'          => $user->id,
            'status'                => Application::STATUS_UNDER_REVIEW,
            'current_stage'         => 'review',
            'data'                  => [],
            'fee_amount'            => 0,
            'payment_status'        => 'waived',
        ]);

        $forAuditor = StageActions::forApplication($app, $service, 'auditor');
        $ids = array_column($forAuditor, 'id');
        $this->assertContains('approve', $ids);
        $this->assertContains('reject', $ids);
        $this->assertContains('override_first_auditor', $ids);

        $forStaff = StageActions::forApplication($app, $service, 'staff');
        $staffIds = array_column($forStaff, 'id');
        // override_first_auditor is auditor/admin only.
        $this->assertNotContains('override_first_auditor', $staffIds);
        $this->assertContains('approve', $staffIds);
    }
}
