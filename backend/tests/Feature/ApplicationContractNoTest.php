<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Organization;
use Modules\JeaProjects\Models\Project;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-14: applications inherit contract_no from their linked project
 * at create time so applicants see the contract identifier on the
 * application detail without cross-referencing.
 */
class ApplicationContractNoTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $applicant;
    private ServiceDefinition $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org',
            'slug' => 'org-contract-' . uniqid(),
            'is_active' => true,
        ]);
        $this->applicant = User::create([
            'organization_id'    => $this->org->id,
            'name'               => 'applicant',
            'email'              => 'ap-contract@t.esp',
            'password'           => Hash::make('Secret123!'),
            'role'               => 'applicant',
            'is_active'          => true,
            'password_changed_at' => now(),
        ]);
        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'    => 'DRW-CONTRACT-1',
            'name_ar' => 'خدمة', 'name_en' => 'Svc',
            'currency' => 'JOD',
            'status'  => 'active',
            'is_locked' => false,
            'schema'  => ['workflow' => ['stages' => [
                ['id' => 's', 'role' => 'applicant', 'label_ar' => 's', 'sla_hours' => 24, 'actions' => ['submit']],
            ]]],
        ]);
    }

    public function test_application_inherits_contract_no_from_its_project(): void
    {
        $project = Project::create([
            'organization_id' => $this->org->id,
            'owner_user_id'   => $this->applicant->id,
            'name_ar'         => 'مشروع',
            'contract_no'     => 'C-2026-42',
            'status'          => 'active',
        ]);
        Sanctum::actingAs($this->applicant);

        $res = $this->postJson('/api/v1/applications', [
            'service_code' => 'DRW-CONTRACT-1',
            'project_id'   => $project->id,
            'data'         => [],
        ]);
        $res->assertCreated()
            ->assertJsonPath('application.contract_no', 'C-2026-42');
    }

    public function test_application_without_project_leaves_contract_no_null(): void
    {
        Sanctum::actingAs($this->applicant);
        $res = $this->postJson('/api/v1/applications', [
            'service_code' => 'DRW-CONTRACT-1',
            'data'         => [],
        ]);
        $res->assertCreated()
            ->assertJsonPath('application.contract_no', null);
    }

    public function test_two_applications_on_the_same_project_share_contract_no(): void
    {
        $project = Project::create([
            'organization_id' => $this->org->id,
            'owner_user_id'   => $this->applicant->id,
            'name_ar'         => 'مشروع',
            'contract_no'     => 'C-SHARED-99',
            'status'          => 'active',
        ]);
        Sanctum::actingAs($this->applicant);

        $a = $this->postJson('/api/v1/applications', [
            'service_code' => 'DRW-CONTRACT-1',
            'project_id'   => $project->id,
            'data'         => [],
        ]);
        $b = $this->postJson('/api/v1/applications', [
            'service_code' => 'DRW-CONTRACT-1',
            'project_id'   => $project->id,
            'data'         => [],
        ]);
        $a->assertCreated();
        $b->assertCreated();
        $this->assertSame('C-SHARED-99', $a->json('application.contract_no'));
        $this->assertSame('C-SHARED-99', $b->json('application.contract_no'));
        // Reference numbers stay unique — contract_no is orthogonal.
        $this->assertNotSame($a->json('application.reference_number'), $b->json('application.reference_number'));
    }
}
