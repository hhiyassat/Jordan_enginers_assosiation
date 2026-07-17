<?php

namespace Tests\Feature;

use App\Models\Engineer;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * HTTP-level tests for the per-engineer m² quota flow:
 *   • GET /api/v1/engineers                    — list office's engineers
 *   • POST /api/v1/engineers                   — register + uniqueness
 *   • GET /api/v1/engineers/{id}/quota         — single engineer status
 *   • GET /api/v1/projects/quota               — office aggregate + breakdown
 *   • POST /api/v1/projects                    — enforcement on create
 */
class EngineerQuotaTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $office;
    private Engineer $eng;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create(['name_ar' => 'O', 'name_en' => 'O', 'slug' => 'o', 'is_active' => true]);
        $this->office = User::create([
            'organization_id'     => $this->org->id,
            'name'                => 'Office',
            'email'               => 'office@test.dev',
            'password'            => 'x',
            'role'                => 'applicant',
            'is_active'           => true,
            'password_changed_at' => now(),
        ]);
        $this->eng = Engineer::create([
            'organization_id'   => $this->org->id,
            'office_user_id'    => $this->office->id,
            'name_ar'           => 'م. Test',
            'membership_number' => '1000',
            'annual_quota_m2'   => 1000,
            'is_active'         => true,
        ]);
    }

    public function test_index_returns_only_office_own_engineers(): void
    {
        // Another office in the same org with its own engineer.
        $otherOffice = User::create([
            'organization_id' => $this->org->id, 'name' => 'Other', 'email' => 'other@test.dev',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        Engineer::create([
            'organization_id' => $this->org->id, 'office_user_id' => $otherOffice->id,
            'name_ar' => 'م. Other', 'membership_number' => '9999', 'is_active' => true,
        ]);

        Sanctum::actingAs($this->office);
        $res = $this->getJson('/api/v1/engineers');

        $res->assertOk()->assertJsonCount(1, 'engineers');
        $this->assertSame('1000', $res->json('engineers.0.membership_number'));
    }

    public function test_store_creates_engineer_with_owner_and_org_backfill(): void
    {
        Sanctum::actingAs($this->office);
        $res = $this->postJson('/api/v1/engineers', [
            'name_ar'           => 'م. New',
            'membership_number' => '2222',
            'specialization'    => 'mechanical',
            'annual_quota_m2'   => 500,
        ]);

        $res->assertCreated();
        $this->assertSame($this->office->id, $res->json('engineer.office_user_id'));
        $this->assertSame($this->org->id,    $res->json('engineer.organization_id'));
    }

    public function test_store_rejects_duplicate_membership_within_office(): void
    {
        Sanctum::actingAs($this->office);
        $res = $this->postJson('/api/v1/engineers', [
            'name_ar'           => 'م. Dup',
            'membership_number' => '1000', // already exists in setUp
        ]);
        $res->assertStatus(422)
            ->assertJsonPath('errors.membership_number.0', 'هذا الرقم مسجل مسبقاً.');
    }

    public function test_engineer_quota_endpoint_reports_used_and_remaining(): void
    {
        // Create a project counting 300 m² against the engineer's 1000 cap.
        Project::create([
            'organization_id' => $this->org->id,
            'owner_user_id'   => $this->office->id,
            'engineer_id'     => $this->eng->id,
            'name_ar'         => 'p1',
            'area_m2'         => 300,
            'status'          => 'pending',
        ]);

        Sanctum::actingAs($this->office);
        $res = $this->getJson("/api/v1/engineers/{$this->eng->id}/quota");

        $res->assertOk()
            ->assertJson([
                'quota_m2'     => 1000,
                'used_m2'      => 300,
                'remaining_m2' => 700,
                'percent_used' => 30,
                'unlimited'    => false,
            ]);
    }

    public function test_projects_quota_returns_office_totals_and_engineer_breakdown(): void
    {
        Project::create([
            'organization_id' => $this->org->id,
            'owner_user_id'   => $this->office->id,
            'engineer_id'     => $this->eng->id,
            'name_ar'         => 'p1', 'area_m2' => 200, 'status' => 'pending',
        ]);

        Sanctum::actingAs($this->office);
        $res = $this->getJson('/api/v1/projects/quota');

        $res->assertOk()
            ->assertJsonPath('totals.quota_m2', 1000)
            ->assertJsonPath('totals.used_m2', 200)
            ->assertJsonPath('totals.engineers_count', 1)
            ->assertJsonCount(1, 'engineers')
            ->assertJsonPath('engineers.0.engineer_id', $this->eng->id);
    }

    public function test_project_create_allows_area_under_quota(): void
    {
        Sanctum::actingAs($this->office);
        $res = $this->postJson('/api/v1/projects', [
            'engineer_id' => $this->eng->id,
            'name_ar'     => 'ok project',
            'area_m2'     => 500, // under 1000 cap
        ]);
        $res->assertCreated();
    }

    public function test_project_create_rejects_over_quota_with_specific_error(): void
    {
        // Pre-consume 800 of the engineer's 1000 quota.
        Project::create([
            'organization_id' => $this->org->id, 'owner_user_id' => $this->office->id,
            'engineer_id' => $this->eng->id, 'name_ar' => 'seed', 'area_m2' => 800, 'status' => 'pending',
        ]);

        Sanctum::actingAs($this->office);
        $res = $this->postJson('/api/v1/projects', [
            'engineer_id' => $this->eng->id,
            'name_ar'     => 'too big',
            'area_m2'     => 300, // 800 + 300 > 1000
        ]);

        $res->assertStatus(422)
            ->assertJson([
                'quota_exceeded' => true,
                'quota'          => 1000,
                'used'           => 800,
                'remaining'      => 200,
                'attempted'      => 300,
            ]);
    }

    public function test_project_create_rejects_engineer_from_another_office(): void
    {
        $otherOffice = User::create([
            'organization_id' => $this->org->id, 'name' => 'Other', 'email' => 'other@test.dev',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $foreignEng = Engineer::create([
            'organization_id' => $this->org->id, 'office_user_id' => $otherOffice->id,
            'name_ar' => 'م. Foreign', 'membership_number' => '8888', 'is_active' => true,
        ]);

        Sanctum::actingAs($this->office);
        $res = $this->postJson('/api/v1/projects', [
            'engineer_id' => $foreignEng->id,
            'name_ar'     => 'x',
            'area_m2'     => 10,
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('errors.engineer_id.0', 'المهندس غير موجود ضمن مكتبك.');
    }

    public function test_null_engineer_quota_means_unlimited(): void
    {
        $this->eng->update(['annual_quota_m2' => null]);

        Sanctum::actingAs($this->office);
        $res = $this->getJson("/api/v1/engineers/{$this->eng->id}/quota");
        $res->assertJson(['unlimited' => true, 'quota_m2' => null]);

        // And a large project is allowed.
        $create = $this->postJson('/api/v1/projects', [
            'engineer_id' => $this->eng->id,
            'name_ar'     => 'unlimited',
            'area_m2'     => 99999,
        ]);
        $create->assertCreated();
    }

    public function test_project_create_requires_engineer_id(): void
    {
        Sanctum::actingAs($this->office);
        $res = $this->postJson('/api/v1/projects', [
            'name_ar' => 'no engineer',
            'area_m2' => 100,
        ]);
        $res->assertStatus(422)->assertJsonValidationErrors(['engineer_id']);
    }
}
