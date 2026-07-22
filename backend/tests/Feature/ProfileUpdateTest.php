<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-10: PATCH /api/v1/auth/me — user updates their own profile.
 *
 * Scope is name + phone only; email lives on the credential-change
 * flow because rotating email is also a login-identity change.
 * Role / organization_id / is_active / must_change_password stay
 * strictly admin-only.
 */
class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org',
            'slug' => 'org-profile-' . uniqid(),
            'is_active' => true,
        ]);
    }

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'organization_id' => $this->org->id,
            'name' => 'الاسم القديم',
            'email' => 'user-' . uniqid() . '@t.esp',
            'password' => Hash::make('Secret123!'),
            'role' => 'applicant',
            'is_active' => true,
            'password_changed_at' => now(),
        ], $overrides));
    }

    public function test_authenticated_user_can_update_own_name(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $res = $this->patchJson('/api/v1/auth/me', ['name' => 'الاسم الجديد']);
        $res->assertOk()->assertJsonPath('user.name', 'الاسم الجديد');
        $this->assertSame('الاسم الجديد', $user->fresh()->name);
    }

    public function test_authenticated_user_can_update_own_phone(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $res = $this->patchJson('/api/v1/auth/me', ['phone' => '+962-79-1234567']);
        $res->assertOk()->assertJsonPath('user.phone', '+962-79-1234567');
    }

    public function test_authenticated_user_can_clear_phone_by_sending_null(): void
    {
        $user = $this->makeUser(['phone' => '0790000000']);
        Sanctum::actingAs($user);

        $res = $this->patchJson('/api/v1/auth/me', ['phone' => null]);
        $res->assertOk();
        $this->assertNull($user->fresh()->phone);
    }

    public function test_email_is_not_updatable_via_profile_endpoint(): void
    {
        // Trying to change email through this endpoint must silently be
        // ignored — Laravel validate() strips unknown keys by default.
        $user = $this->makeUser(['email' => 'original@t.esp']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/auth/me', ['email' => 'stolen@evil.example'])->assertOk();
        $this->assertSame('original@t.esp', $user->fresh()->email);
    }

    public function test_role_and_org_id_are_never_updatable_via_profile_endpoint(): void
    {
        // Belt-and-braces: even if a caller sneaks role/org into the
        // request body, the validator throws them away.
        $user = $this->makeUser(['role' => 'applicant']);
        $originalOrg = $user->organization_id;
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/auth/me', [
            'name' => 'x',
            'role' => 'superuser',
            'organization_id' => 999999,
            'is_active' => false,
            'must_change_password' => true,
        ])->assertOk();
        $fresh = $user->fresh();
        $this->assertSame('applicant', $fresh->role);
        $this->assertSame($originalOrg, $fresh->organization_id);
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertFalse((bool) $fresh->must_change_password);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->patchJson('/api/v1/auth/me', ['name' => 'anything'])->assertStatus(401);
    }

    public function test_name_length_is_capped_at_120(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $this->patchJson('/api/v1/auth/me', ['name' => str_repeat('ا', 121)])
             ->assertStatus(422);
    }
}
