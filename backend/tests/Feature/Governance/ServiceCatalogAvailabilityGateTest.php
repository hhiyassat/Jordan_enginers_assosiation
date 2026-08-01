<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\JeaServices\Models\ServiceDefinition;
use Tests\TestCase;

/**
 * SG-02 · integration proof that ServiceAvailabilityPolicy governs the
 * public catalog endpoint. The policy is exhaustively unit-tested in
 * ServiceAvailabilityPolicyTest; this file confirms the controller
 * consults it correctly.
 */
class ServiceCatalogAvailabilityGateTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
    }

    public function test_retired_service_is_hidden_from_applicant_and_visible_to_admin(): void
    {
        $this->makeService('RETIRED-SVC', [
            'publication_status' => 'RETIRED',
            'retired_at'         => now(),
            'retirement_reason'  => 'Board decision 2026-06-01',
        ]);

        Sanctum::actingAs($this->makeUser('applicant', 'app1@t.test'));
        $applicantCodes = array_column(
            $this->getJson('/api/v1/services')->assertOk()->json('services'),
            'code',
        );
        $this->assertNotContains('RETIRED-SVC', $applicantCodes);

        Sanctum::actingAs($this->makeUser('admin', 'admin@t.test'));
        $adminCodes = array_column(
            $this->getJson('/api/v1/services')->assertOk()->json('services'),
            'code',
        );
        $this->assertContains('RETIRED-SVC', $adminCodes);
    }

    public function test_suspended_service_is_hidden_from_applicant(): void
    {
        $this->makeService('SUSPENDED-SVC', [
            'publication_status' => 'SUSPENDED',
            'suspended_at'       => now(),
            'suspension_reason'  => 'Awaiting fee re-approval',
        ]);

        Sanctum::actingAs($this->makeUser('applicant', 'app2@t.test'));
        $codes = array_column(
            $this->getJson('/api/v1/services')->assertOk()->json('services'),
            'code',
        );
        $this->assertNotContains('SUSPENDED-SVC', $codes);
    }

    public function test_show_returns_404_for_hidden_service(): void
    {
        $this->makeService('RETIRED-SHOW', [
            'publication_status' => 'RETIRED',
            'retired_at'         => now(),
        ]);

        Sanctum::actingAs($this->makeUser('applicant', 'app3@t.test'));
        $this->getJson('/api/v1/services/RETIRED-SHOW')->assertNotFound();
    }

    public function test_legacy_active_service_visible_under_lenient_default(): void
    {
        // No publication_status set → defaults to NOT_PUBLISHED via migration
        $this->makeService('LEGACY-1', []);

        Sanctum::actingAs($this->makeUser('applicant', 'app4@t.test'));
        $codes = array_column(
            $this->getJson('/api/v1/services')->assertOk()->json('services'),
            'code',
        );
        $this->assertContains('LEGACY-1', $codes);
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'organization_id'     => $this->org->id,
            'name'                => "u-{$role}",
            'email'               => $email,
            'password'            => 'x',
            'role'                => $role,
            'is_active'           => true,
            'password_changed_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function makeService(string $code, array $overrides): ServiceDefinition
    {
        return ServiceDefinition::create(array_merge([
            'organization_id' => $this->org->id,
            'code'            => $code,
            'name_ar'         => 'خدمة اختبار',
            'name_en'         => 'Test Service',
            'currency'        => 'JOD',
            'schema'          => [
                'workflow'  => ['stages' => [['id' => 'review', 'role' => 'staff']]],
                'fields'    => [],
                'documents' => [],
                'fee'       => ['type' => 'fixed', 'amount' => 25],
            ],
            'status'          => 'active',
        ], $overrides));
    }
}
