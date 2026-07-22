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
 * JORD-35 regression: server-side pagination + free-text search on
 * GET /api/v1/admin/applications. Endpoint has to:
 *   • honor per_page (clamped 5..50)
 *   • return the standard Laravel paginator envelope
 *   • match `q` against reference_number, applicant name/email, and
 *     service code/name — case-insensitively
 *
 * These are the shape guarantees the frontend usePaginatedAdminApplications
 * hook relies on. Break any of them and the admin list page silently
 * shows the wrong result set.
 */
class AdminApplicationsListingTest extends TestCase
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
            'slug' => 'org-admin-list-' . uniqid(),
            'is_active' => true,
        ]);
        $this->admin = User::create([
            'organization_id' => $this->org->id,
            'name' => 'الإدارة', 'email' => 'admin-list@t.esp',
            'password' => Hash::make('Secret123!'),
            'role' => 'admin', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $this->service = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'    => 'DRW-P-004',
            'name_ar' => 'مخططات الهدم', 'name_en' => 'Demolition Drawings',
            'currency' => 'JOD',
            'status' => 'active', 'is_locked' => false,
            'schema' => ['workflow' => ['stages' => [
                ['id' => 's', 'role' => 'applicant', 'label_ar' => '.', 'sla_hours' => 24, 'actions' => ['submit']],
            ]]],
        ]);
    }

    private function makeApplicant(string $name, string $email): User
    {
        return User::create([
            'organization_id' => $this->org->id,
            'name' => $name, 'email' => $email,
            'password' => Hash::make('Secret123!'),
            'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
    }

    private function makeApp(User $applicant, string $ref, string $status = 'submitted'): Application
    {
        return Application::create([
            'reference_number' => $ref,
            'organization_id' => $this->org->id,
            'service_definition_id' => $this->service->id,
            'applicant_id' => $applicant->id,
            'status' => $status,
            'current_stage' => 's',
            'data' => [],
            'fee_amount' => 0,
            'payment_status' => 'waived',
        ]);
    }

    public function test_returns_a_laravel_paginator_envelope(): void
    {
        $ap = $this->makeApplicant('حسين', 'h@t.esp');
        for ($i = 0; $i < 3; $i++) {
            $this->makeApp($ap, "A-ENV-{$i}");
        }
        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/v1/admin/applications');
        $res->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page', 'per_page', 'total', 'last_page', 'from', 'to',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_per_page_is_honored_and_clamped(): void
    {
        $ap = $this->makeApplicant('حسين', 'h2@t.esp');
        for ($i = 0; $i < 12; $i++) {
            $this->makeApp($ap, "A-PP-{$i}");
        }
        Sanctum::actingAs($this->admin);

        // Explicit per_page=5 returns exactly 5 rows and total=12.
        $res = $this->getJson('/api/v1/admin/applications?per_page=5');
        $res->assertOk()
            ->assertJsonPath('per_page', 5)
            ->assertJsonPath('total', 12)
            ->assertJsonCount(5, 'data');

        // Malicious per_page=10000 gets clamped down to 50.
        $res = $this->getJson('/api/v1/admin/applications?per_page=10000');
        $res->assertJsonPath('per_page', 50);

        // Below-minimum per_page=1 gets clamped up to 5.
        $res = $this->getJson('/api/v1/admin/applications?per_page=1');
        $res->assertJsonPath('per_page', 5);
    }

    public function test_q_matches_reference_number_case_insensitively(): void
    {
        $ap = $this->makeApplicant('حسين', 'h3@t.esp');
        $this->makeApp($ap, 'REF-ALPHA-001');
        $this->makeApp($ap, 'REF-BETA-002');
        $this->makeApp($ap, 'REF-ALPHA-003');
        Sanctum::actingAs($this->admin);

        // Lowercase query still matches uppercase reference_number.
        $res = $this->getJson('/api/v1/admin/applications?q=alpha');
        $res->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_q_matches_applicant_name(): void
    {
        $hussein = $this->makeApplicant('حسين', 'hussein@t.esp');
        $ali     = $this->makeApplicant('علي',  'ali@t.esp');
        $this->makeApp($hussein, 'A-H-1');
        $this->makeApp($ali,     'A-A-1');
        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/v1/admin/applications?q=' . urlencode('حسين'));
        $res->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference_number', 'A-H-1');
    }

    public function test_q_matches_service_code(): void
    {
        $ap = $this->makeApplicant('حسين', 'h5@t.esp');
        $this->makeApp($ap, 'A-SVC-1');
        Sanctum::actingAs($this->admin);

        // The seeded service code is DRW-P-004 — searching a substring hits it.
        $res = $this->getJson('/api/v1/admin/applications?q=drw-p');
        $res->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_status_filter_still_works(): void
    {
        $ap = $this->makeApplicant('حسين', 'h6@t.esp');
        $this->makeApp($ap, 'A-ST-1', 'draft');
        $this->makeApp($ap, 'A-ST-2', 'submitted');
        $this->makeApp($ap, 'A-ST-3', 'submitted');
        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/v1/admin/applications?status=submitted');
        $res->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_status_and_q_compose(): void
    {
        $ap = $this->makeApplicant('حسين', 'h7@t.esp');
        $this->makeApp($ap, 'A-ST-alpha-1', 'draft');
        $this->makeApp($ap, 'A-ST-alpha-2', 'submitted');
        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/v1/admin/applications?status=submitted&q=alpha');
        $res->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference_number', 'A-ST-alpha-2');
    }

    public function test_non_admin_is_rejected(): void
    {
        $applicant = $this->makeApplicant('حسين', 'h8@t.esp');
        Sanctum::actingAs($applicant);
        $res = $this->getJson('/api/v1/admin/applications');
        $res->assertForbidden();
    }
}
