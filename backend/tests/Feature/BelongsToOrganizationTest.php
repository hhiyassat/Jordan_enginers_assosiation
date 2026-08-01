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

    /**
     * H-01: An authenticated user whose organization_id is NULL must
     * NOT silently receive every tenant's rows. Previously the scope
     * returned without filtering in that case; this test pins the
     * fail-closed behavior.
     */
    public function test_null_org_authenticated_user_sees_zero_rows_via_global_scope(): void
    {
        Auth::login($this->userA);
        // Simulate a corrupted / mis-provisioned auth user by nulling
        // the org in-memory. The DB row still has a valid FK but the
        // in-memory Auth::user() no longer exposes it, which is what
        // OrganizationScope reads.
        Auth::user()->organization_id = null;

        $this->assertSame(0, Project::query()->count(),
            'H-01: null-org authenticated user must fail closed and see zero rows.');
    }

    public function test_null_org_authenticated_user_sees_zero_rows_via_for_current_organization(): void
    {
        Auth::login($this->userA);
        Auth::user()->organization_id = null;

        $count = Project::withoutOrgScope()->forCurrentOrganization()->count();
        $this->assertSame(0, $count,
            'H-01: forCurrentOrganization must fail closed for null-org auth user.');
    }

    public function test_null_org_can_still_use_without_org_scope_for_explicit_cross_tenant(): void
    {
        // Documents the escape hatch: if the caller explicitly opts
        // out of the global scope (e.g. an integration job), it is
        // still able to read cross-tenant data. This is intentional —
        // the H-01 fix only closes the SILENT default.
        Auth::login($this->userA);
        Auth::user()->organization_id = null;

        $this->assertSame(3, Project::withoutOrgScope()->count());
    }

    // ── CL-04 · findForOrganizationOrFail helper ─────────────────────────

    public function test_find_for_organization_or_fail_returns_same_org_row(): void
    {
        $target = Project::create([
            'organization_id' => $this->orgA->id,
            'owner_user_id'   => $this->userA->id,
            'name_ar'         => 'A-target', 'type' => 'سكني',
            'area_m2'         => 400, 'status' => 'active',
        ]);

        $found = Project::findForOrganizationOrFail($this->orgA->id, $target->id);

        $this->assertInstanceOf(Project::class, $found);
        $this->assertSame($target->id, $found->id);
    }

    public function test_find_for_organization_or_fail_rejects_cross_org_row_with_404(): void
    {
        $orgATarget = Project::create([
            'organization_id' => $this->orgA->id,
            'owner_user_id'   => $this->userA->id,
            'name_ar'         => 'A-cross', 'type' => 'سكني',
            'area_m2'         => 100, 'status' => 'active',
        ]);

        // A caller resolving orgB's id but reaching for an OrgA row → 404,
        // NOT a permission error, exactly like the inline pattern it replaces.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Project::findForOrganizationOrFail($this->orgB->id, $orgATarget->id);
    }

    public function test_find_for_organization_or_fail_missing_id_throws_404(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Project::findForOrganizationOrFail($this->orgA->id, 999999);
    }

    public function test_find_for_organization_or_fail_null_org_context_still_fails_closed(): void
    {
        // Even though the helper takes an explicit $orgId, the global
        // OrganizationScope still fires when an auth user has null org
        // (H-01). The two filters compose and the query returns nothing
        // — 404 rather than a leaky cross-tenant leak.
        Auth::login($this->userA);
        Auth::user()->organization_id = null;

        $orgATarget = Project::withoutOrgScope()->where('organization_id', $this->orgA->id)->first();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Project::findForOrganizationOrFail($this->orgA->id, $orgATarget->id);
    }

    public function test_find_for_organization_or_fail_does_not_bypass_the_global_scope(): void
    {
        // Regression guard: the helper wraps forOrganization() + findOrFail
        // and does NOT call withoutOrgScope(). If someone ever changes the
        // impl to `withoutOrgScope()->where(...)`, this test fails because
        // an authenticated-as-B user asking for an A row must still 404
        // (double filter: global scope filters to B; helper filters to A;
        // intersection is empty).
        Auth::login($this->userB);
        $orgATarget = Project::withoutOrgScope()->where('organization_id', $this->orgA->id)->first();
        $this->assertNotNull($orgATarget, 'seed data assumption: at least one Org A project');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Project::findForOrganizationOrFail($this->orgA->id, $orgATarget->id);
    }
}
