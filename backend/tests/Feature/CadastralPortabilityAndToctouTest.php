<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\JeaServices\Engine\CadastralPriorApplicationLookup;
use Modules\JeaServices\Engine\WorkflowEngine;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

class CadastralPortabilityAndToctouTest extends TestCase
{
    use RefreshDatabase;

    private ServiceDefinition $service;
    private Organization $orgA;
    private Organization $orgB;
    private User $applicantA;
    private User $applicantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['name_ar' => 'A', 'name_en' => 'A', 'slug' => 'org-a', 'is_active' => true]);
        $this->orgB = Organization::create(['name_ar' => 'B', 'name_en' => 'B', 'slug' => 'org-b', 'is_active' => true]);

        $this->applicantA = User::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Applicant A',
            'email' => 'appA@test.com',
            'password' => 'secret123',
            'role' => 'applicant',
            'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $this->applicantB = User::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Applicant B',
            'email' => 'appB@test.com',
            'password' => 'secret123',
            'role' => 'applicant',
            'is_active' => true,
            'password_changed_at' => now(),
        ]);

        $this->service = ServiceDefinition::create([
            'organization_id' => $this->orgA->id,
            'code' => 'TEST-CADASTRA-01',
            'name_ar' => 'فحص أرض',
            'name_en' => 'Land Inspection',
            'is_active' => true,
            'version' => 1,
            'schema' => [
                'fields' => [
                    ['id' => 'basin_number', 'type' => 'text', 'required' => true],
                    ['id' => 'parcel_number', 'type' => 'text', 'required' => true],
                    ['id' => 'basin_or_location_name', 'type' => 'text', 'required' => true],
                    ['id' => 'contract_owner_name', 'type' => 'text', 'required' => true],
                ],
                'workflow' => [
                    'stages' => [
                        ['id' => 'office_submission', 'role' => 'applicant'],
                        ['id' => 'auditor_review', 'role' => 'admin'],
                    ],
                ],
            ],
        ]);
    }

    public function test_cadastral_columns_auto_sync_on_save(): void
    {
        $app = Application::create([
            'reference_number' => 'APP-CAD-001',
            'organization_id' => $this->orgA->id,
            'service_definition_id' => $this->service->id,
            'applicant_id' => $this->applicantA->id,
            'status' => Application::STATUS_DRAFT,
            'data' => [
                'basin_number' => '12',
                'parcel_number' => '345',
                'basin_or_location_name' => 'حي الأمل',
                'contract_owner_name' => 'أحمد علي',
            ],
        ]);

        $this->assertEquals('12', $app->basin_number);
        $this->assertEquals('345', $app->parcel_number);

        // Verify lookup query works portably without json_extract
        $candidates = CadastralPriorApplicationLookup::candidates($app);
        $this->assertCount(0, $candidates);
    }

    public function test_toctou_prevention_on_concurrent_submit(): void
    {
        // App A by Org A
        $appA = Application::create([
            'reference_number' => 'APP-CAD-A',
            'organization_id' => $this->orgA->id,
            'service_definition_id' => $this->service->id,
            'applicant_id' => $this->applicantA->id,
            'status' => Application::STATUS_DRAFT,
            'data' => [
                'basin_number' => '10',
                'parcel_number' => '99',
                'basin_or_location_name' => 'عمان الغربية',
                'contract_owner_name' => 'مالك أول',
            ],
        ]);

        // App B by Org B (different owner)
        $appB = Application::create([
            'reference_number' => 'APP-CAD-B',
            'organization_id' => $this->orgB->id,
            'service_definition_id' => $this->service->id,
            'applicant_id' => $this->applicantB->id,
            'status' => Application::STATUS_DRAFT,
            'data' => [
                'basin_number' => '10',
                'parcel_number' => '99',
                'basin_or_location_name' => 'عمان الغربية',
                'contract_owner_name' => 'مالك ثاني',
            ],
        ]);

        $engine = new WorkflowEngine($this->service);

        // First submit succeeds
        $submittedA = $engine->submit($appA, $this->applicantA);
        $this->assertEquals(Application::STATUS_SUBMITTED, $submittedA->status);

        // Second submit for same parcel from different org fails inside transaction
        $this->expectException(\App\Engine\Exceptions\ConflictException::class);
        $engine->submit($appB, $this->applicantB);
    }
}
