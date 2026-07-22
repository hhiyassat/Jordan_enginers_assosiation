<?php

namespace Tests\Feature;

use App\Models\Organization;
use Modules\JeaProjects\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * NFR-002: BelongsToOrganization trait enforces tenant isolation.
 * Uses Project as the sample host model (has the trait applied).
 */
class BelongsToOrganizationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['name_ar' => 'Org A', 'name_en' => 'Org A', 'slug' => 'org-a', 'is_active' => true]);
        $this->orgB = Organization::create(['name_ar' => 'Org B', 'name_en' => 'Org B', 'slug' => 'org-b', 'is_active' => true]);

        $this->userA = User::create([
            'organization_id' => $this->orgA->id,
            'name' => 'A', 'email' => 'a@t.dev', 'password' => 'x',
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->userB = User::create([
            'organization_id' => $this->orgB->id,
            'name' => 'B', 'email' => 'b@t.dev', 'password' => 'x',
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);

        // Create 2 projects in Org A and 1 in Org B (via withoutOrgScope
        // + explicit org_id to bypass the global scope guard).
        Project::withoutOrgScope(); // no-op, but demonstrates the API.
        Project::create([
            'organization_id' => $this->orgA->id, 'owner_user_id' => $this->userA->id,
            'name_ar' => 'A1', 'type' => 'سكني', 'area_m2' => 100, 'status' => 'active',
        ]);
        Project::create([
            'organization_id' => $this->orgA->id, 'owner_user_id' => $this->userA->id,
            'name_ar' => 'A2', 'type' => 'تجاري', 'area_m2' => 200, 'status' => 'active',
        ]);
        Project::create([
            'organization_id' => $this->orgB->id, 'owner_user_id' => $this->userB->id,
            'name_ar' => 'B1', 'type' => 'سكني', 'area_m2' => 300, 'status' => 'active',
        ]);
    }

    public function test_unauthenticated_query_sees_all_orgs(): void
    {
        Auth::logout();
        $this->assertSame(3, Project::query()->count());
    }

    public function test_authenticated_query_is_scoped_to_users_org(): void
    {
        Auth::login($this->userA);
        $this->assertSame(2, Project::query()->count(), 'User A only sees Org A projects');

        Auth::login($this->userB);
        $this->assertSame(1, Project::query()->count(), 'User B only sees Org B projects');
    }

    public function test_without_org_scope_escapes_the_filter(): void
    {
        Auth::login($this->userA);
        $this->assertSame(3, Project::withoutOrgScope()->count());
    }

    public function test_for_organization_scope_filters_explicitly(): void
    {
        Auth::logout();
        $this->assertSame(2, Project::forOrganization($this->orgA->id)->count());
        $this->assertSame(1, Project::forOrganization($this->orgB->id)->count());
    }

    public function test_for_current_organization_uses_auth_user(): void
    {
        Auth::login($this->userA);
        // Escape the global scope explicitly, then re-scope by current org.
        $count = Project::withoutOrgScope()->forCurrentOrganization()->count();
        $this->assertSame(2, $count);
    }

    public function test_creating_a_model_backfills_organization_id_from_auth(): void
    {
        Auth::login($this->userA);
        $p = Project::create([
            'owner_user_id' => $this->userA->id,
            'name_ar'       => 'AutoOrg',
            'type'          => 'سكني',
            'area_m2'       => 50,
            'status'        => 'pending',
        ]);
        $this->assertSame($this->orgA->id, $p->organization_id);
    }

    public function test_explicit_organization_id_wins_over_auth_backfill(): void
    {
        Auth::login($this->userA);
        $p = Project::withoutOrgScope()->create([
            'organization_id' => $this->orgB->id,
            'owner_user_id'   => $this->userB->id,
            'name_ar'         => 'Explicit',
            'type'            => 'سكني',
            'area_m2'         => 40,
            'status'          => 'pending',
        ]);
        $this->assertSame($this->orgB->id, $p->organization_id);
    }
}
