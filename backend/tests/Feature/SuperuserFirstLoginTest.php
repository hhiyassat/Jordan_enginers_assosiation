<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pins the first-login flow: a superuser landing in the bootstrap state
 * (must_change_password=true) can rotate email + password once via the
 * change-password endpoint. After that the endpoint refuses further
 * changes — only `php artisan user:credentials` can rotate. Same endpoint
 * still works for other roles (their normal password-change flow).
 */
class SuperuserFirstLoginTest extends TestCase
{
    use RefreshDatabase;

    private User $superuser;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create([
            'name_ar' => 'org','name_en' => 'org','slug' => 'org','is_active' => true,
        ]);
        $this->superuser = User::create([
            'organization_id'      => $org->id,
            'name'                 => 'super',
            'email'                => 'super@t.esp',
            'password'             => Hash::make('Bootstrap1!'),
            'role'                 => 'superuser',
            'is_active'            => true,
            'must_change_password' => true,
        ]);
    }

    public function test_superuser_can_change_email_and_password_during_first_login(): void
    {
        Sanctum::actingAs($this->superuser);
        $this->postJson('/api/v1/auth/password/change', [
            'current_password'      => 'Bootstrap1!',
            'password'              => 'MyPermanent1!',
            'password_confirmation' => 'MyPermanent1!',
            'email'                 => 'new-super@t.esp',
        ])->assertOk();

        $this->superuser->refresh();
        $this->assertSame('new-super@t.esp', $this->superuser->email);
        $this->assertFalse($this->superuser->must_change_password);
        $this->assertTrue(Hash::check('MyPermanent1!', $this->superuser->password));
    }

    public function test_superuser_cannot_change_creds_via_api_after_init(): void
    {
        $this->superuser->update(['must_change_password' => false]);
        Sanctum::actingAs($this->superuser);
        $this->postJson('/api/v1/auth/password/change', [
            'current_password'      => 'Bootstrap1!',
            'password'              => 'AnotherPass1!',
            'password_confirmation' => 'AnotherPass1!',
        ])->assertStatus(403)
          ->assertJsonPath('message', fn($m) => str_contains($m, 'user:credentials'));
    }

    public function test_non_superuser_password_change_flow_unaffected(): void
    {
        $org  = Organization::first();
        $user = User::create([
            'organization_id'      => $org->id,
            'name'                 => 'a', 'email' => 'a@t.esp',
            'password'             => Hash::make('OldPass1!'),
            'role'                 => 'staff',
            'is_active'            => true,
            'password_changed_at'  => now(),
        ]);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/auth/password/change', [
            'current_password'      => 'OldPass1!',
            'password'              => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ])->assertOk();
    }

    public function test_userpayload_surfaces_must_change_password_flag(): void
    {
        Sanctum::actingAs($this->superuser);
        $res = $this->getJson('/api/v1/auth/me');
        $res->assertOk();
        $this->assertTrue($res->json('user.must_change_password'));
        $this->assertTrue($res->json('user.can_manage_users'));
    }
}
