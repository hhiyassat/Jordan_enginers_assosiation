<?php

declare(strict_types=1);

namespace Tests\Feature;

use Modules\JeaServices\Models\Application;
use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-11: /admin/dashboard now returns by_status + recent alongside
 * the stat tiles so the admin page can render more than counters.
 */
class AdminDashboardEnrichedTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private ServiceDefinition $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org',
            'slug' => 'org-dash-' . uniqid(),
            'is_active' => true,
        ]);
        $this->admin = User::create([
            'organization_id' => $this->org->id,
            'name' => 'admin', 'email' => 'admin-dash@t.esp',
            'password' => Hash::make('Secret123!'),
            'role' => 'admin', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'    => 'DRW-DASH-1',
            'name_ar' => 'خدمة',
            'name_en' => 'Svc',
            'currency' => 'JOD',
            'status'  => 'active',
            'is_locked' => false,
            'schema'  => ['workflow' => ['stages' => [
                ['id' => 's', 'role' => 'applicant', 'label_ar' => 's', 'sla_hours' => 24, 'actions' => ['submit']],
            ]]],
        ]);
    }

    private function makeApplicant(string $email): User
    {
        return User::create([
            'organization_id' => $this->org->id,
            'name' => 'ap', 'email' => $email,
            'password' => Hash::make('Secret123!'),
            'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
    }

    private function makeApp(User $ap, string $ref, string $status): Application
    {
        return Application::create([
            'reference_number' => $ref,
            'organization_id' => $this->org->id,
            'service_definition_id' => $this->service->id,
            'applicant_id' => $ap->id,
            'status' => $status,
            'current_stage' => 's',
            'data' => [], 'fee_amount' => 0,
            'payment_status' => 'waived',
        ]);
    }

    public function test_dashboard_returns_by_status_breakdown(): void
    {
        $ap = $this->makeApplicant('a-dash@t.esp');
        $this->makeApp($ap, 'A-D-1', 'draft');
        $this->makeApp($ap, 'A-D-2', 'submitted');
        $this->makeApp($ap, 'A-D-3', 'submitted');
        $this->makeApp($ap, 'A-D-4', 'approved');

        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/admin/dashboard');
        $res->assertOk()
            ->assertJsonPath('by_status.draft', 1)
            ->assertJsonPath('by_status.submitted', 2)
            ->assertJsonPath('by_status.approved', 1);
    }

    public function test_dashboard_returns_at_most_five_recent_applications(): void
    {
        $ap = $this->makeApplicant('a-dash2@t.esp');
        for ($i = 0; $i < 7; $i++) {
            $this->makeApp($ap, "A-R-{$i}", 'submitted');
        }

        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/admin/dashboard');
        $res->assertOk()->assertJsonCount(5, 'recent');
        // Newest-first ordering — the last-created row is at index 0.
        $refs = collect($res->json('recent'))->pluck('reference_number')->all();
        $this->assertSame('A-R-6', $refs[0]);
    }

    public function test_dashboard_scopes_to_admins_organization(): void
    {
        $otherOrg = Organization::create([
            'name_ar' => 'other', 'name_en' => 'other',
            'slug' => 'other-dash-' . uniqid(),
            'is_active' => true,
        ]);
        $otherSvc = ServiceDefinition::create([
            'organization_id' => $otherOrg->id,
            'code' => 'X', 'name_ar' => 'x', 'name_en' => 'x',
            'currency' => 'JOD', 'status' => 'active', 'is_locked' => false,
            'schema' => ['workflow' => ['stages' => []]],
        ]);
        $otherAp = User::create([
            'organization_id' => $otherOrg->id,
            'name' => 'x', 'email' => 'x@t.esp',
            'password' => Hash::make('Secret123!'),
            'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        Application::create([
            'reference_number' => 'OTHER-1',
            'organization_id' => $otherOrg->id,
            'service_definition_id' => $otherSvc->id,
            'applicant_id' => $otherAp->id,
            'status' => 'submitted',
            'current_stage' => 's',
            'data' => [], 'fee_amount' => 0,
            'payment_status' => 'waived',
        ]);

        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/admin/dashboard');
        // Other org's application must NOT appear in the recent list.
        $refs = collect($res->json('recent'))->pluck('reference_number')->all();
        $this->assertNotContains('OTHER-1', $refs);
    }
}
