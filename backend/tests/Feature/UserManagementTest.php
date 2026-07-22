<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * User-management CRUD + all the safeguards that keep the org from getting
 * locked out or silently privilege-escalated.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $superuser;
    private User $admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org', 'slug' => 'org', 'is_active' => true,
        ]);
        $this->superuser = $this->makeUser('superuser', 'super@t.esp');
        $this->admin     = $this->makeUser('admin',     'admin@t.esp');
        $this->staff     = $this->makeUser('staff',     'staff@t.esp');
    }

    private function makeUser(string $role, string $email, array $overrides = []): User
    {
        return User::create(array_merge([
            'organization_id'     => $this->org->id,
            'name'                => $role,
            'email'               => $email,
            'password'            => Hash::make('Secret123!'),
            'role'                => $role,
            'is_active'           => true,
            'password_changed_at' => now(),
        ], $overrides));
    }

    public function test_admin_can_reach_user_management_but_sees_only_manageable_tiers(): void
    {
        // Admin CAN list, but should not see admin/superuser rows they
        // can't act on — the list is filtered server-side.
        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/admin/users');
        $res->assertOk();

        $roles = array_column($res->json('users'), 'role');
        $this->assertNotContains('admin',     $roles, 'admin row must be hidden from admin actor');
        $this->assertNotContains('superuser', $roles, 'superuser row must be hidden from admin actor');
        $this->assertContains('staff', $roles);
    }

    public function test_staff_cannot_reach_user_management(): void
    {
        Sanctum::actingAs($this->staff);
        $this->getJson('/api/v1/admin/users')->assertStatus(403);
    }

    public function test_admin_can_create_a_lower_tier_user(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/admin/users', [
            'name' => 'موظف جديد', 'email' => 'new-staff@t.esp',
            'password' => 'Aa123456', 'role' => 'staff',
        ])->assertCreated();
    }

    public function test_admin_cannot_create_another_admin(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/admin/users', [
            'name' => 'مدير آخر', 'email' => 'other-admin@t.esp',
            'password' => 'Aa123456', 'role' => 'admin',
        ])->assertStatus(403);
    }

    public function test_admin_cannot_create_a_superuser(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/admin/users', [
            'name' => 'مستخدم أعلى', 'email' => 'other-super@t.esp',
            'password' => 'Aa123456', 'role' => 'superuser',
        ])->assertStatus(403);
    }

    public function test_admin_cannot_edit_another_admin(): void
    {
        $peer = $this->makeUser('admin', 'peer-admin@t.esp');
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/v1/admin/users/{$peer->id}", ['name' => 'Renamed'])
            ->assertStatus(403);
    }

    public function test_admin_cannot_promote_a_user_to_admin(): void
    {
        $target = $this->makeUser('staff', 'promote-me@t.esp');
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/v1/admin/users/{$target->id}", ['role' => 'admin'])
            ->assertStatus(403);
    }

    public function test_admin_cannot_delete_another_admin(): void
    {
        $peer = $this->makeUser('admin', 'peer-admin@t.esp');
        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/v1/admin/users/{$peer->id}")->assertStatus(403);
    }

    public function test_admin_can_delete_a_staff_user(): void
    {
        $victim = $this->makeUser('staff', 'staff-victim@t.esp');
        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/v1/admin/users/{$victim->id}")->assertOk();
    }

    public function test_superuser_lists_users_in_own_org_only(): void
    {
        $otherOrg  = Organization::create(['name_ar' => 'o2','name_en' => 'o2','slug' => 'o2','is_active' => true]);
        User::create([
            'organization_id' => $otherOrg->id, 'name' => 'stranger', 'email' => 'x@other.esp',
            'password' => Hash::make('Secret123!'), 'role' => 'admin', 'is_active' => true, 'password_changed_at' => now(),
        ]);

        Sanctum::actingAs($this->superuser);
        $res = $this->getJson('/api/v1/admin/users');
        $res->assertOk();
        $emails = array_column($res->json('users'), 'email');
        $this->assertContains('admin@t.esp', $emails);
        $this->assertNotContains('x@other.esp', $emails);
    }

    public function test_superuser_creates_user_with_must_change_password(): void
    {
        Sanctum::actingAs($this->superuser);
        $this->postJson('/api/v1/admin/users', [
            'name' => 'مستخدم جديد', 'email' => 'new@t.esp', 'password' => 'Aa123456',
            'role' => 'staff',
        ])->assertCreated();
        $this->assertTrue(User::where('email', 'new@t.esp')->first()->must_change_password);
    }

    public function test_delete_target_is_soft_deleted(): void
    {
        $victim = $this->makeUser('applicant', 'victim@t.esp');
        Sanctum::actingAs($this->superuser);
        $this->deleteJson("/api/v1/admin/users/{$victim->id}")->assertOk();

        $this->assertNotNull(User::withTrashed()->find($victim->id)->deleted_at);
    }

    public function test_superuser_cannot_delete_themselves(): void
    {
        Sanctum::actingAs($this->superuser);
        $this->deleteJson("/api/v1/admin/users/{$this->superuser->id}")->assertStatus(422);
    }

    public function test_cannot_delete_last_active_superuser(): void
    {
        $second = $this->makeUser('superuser', 'super2@t.esp');
        $second->update(['is_active' => false]);

        Sanctum::actingAs($second);
        $this->deleteJson("/api/v1/admin/users/{$this->superuser->id}")->assertStatus(422);
    }

    public function test_cannot_demote_last_active_superuser(): void
    {
        $second = $this->makeUser('superuser', 'super2@t.esp');
        $second->update(['is_active' => false]);

        Sanctum::actingAs($second);
        $this->putJson("/api/v1/admin/users/{$this->superuser->id}", ['role' => 'admin'])->assertStatus(422);
    }

    public function test_cannot_deactivate_last_active_superuser(): void
    {
        $second = $this->makeUser('superuser', 'super2@t.esp');
        $second->update(['is_active' => false]);

        Sanctum::actingAs($second);
        $this->putJson("/api/v1/admin/users/{$this->superuser->id}", ['is_active' => false])->assertStatus(422);
    }

    public function test_superuser_credentials_cannot_be_changed_via_api_after_init(): void
    {
        // Target superuser has must_change_password=false → CLI-only rotation.
        $target = $this->makeUser('superuser', 'target-super@t.esp');
        Sanctum::actingAs($this->superuser);
        $this->putJson("/api/v1/admin/users/{$target->id}", ['password' => 'Newpass123'])
            ->assertStatus(403)
            ->assertJsonPath('message', fn($m) => str_contains($m, 'user:credentials'));
    }

    public function test_superuser_still_initializable_via_api_during_first_login_window(): void
    {
        // While must_change_password is true (bootstrap state), a superuser
        // record CAN be edited by another superuser — this is how the org
        // initializes a fresh superuser account.
        $bootstrap = $this->makeUser('superuser', 'boot-super@t.esp', ['must_change_password' => true]);
        Sanctum::actingAs($this->superuser);
        $this->putJson("/api/v1/admin/users/{$bootstrap->id}", ['name' => 'Renamed'])->assertOk();
    }
}
