<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserCredentialsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'superuser', string $email = 'super@t.esp'): User
    {
        $org = Organization::firstOrCreate(
            ['slug' => 'org'],
            ['name_ar' => 'org', 'name_en' => 'org', 'is_active' => true],
        );
        return User::create([
            'organization_id'      => $org->id,
            'name'                 => 'super',
            'email'                => $email,
            'password'             => Hash::make('OldPass1!'),
            'role'                 => $role,
            'is_active'            => true,
            'must_change_password' => false,
        ]);
    }

    public function test_rotates_password_and_leaves_user_on_must_change_flag(): void
    {
        $u = $this->makeUser();
        $this->artisan('user:credentials', [
            'email'      => $u->email,
            '--password' => 'RotatedPass1!',
        ])->assertSuccessful();

        $u->refresh();
        $this->assertTrue(Hash::check('RotatedPass1!', $u->password));
        $this->assertTrue($u->must_change_password,
            'CLI rotations are bootstrap credentials — user must pick their final password on next login');
    }

    public function test_optional_new_email_takes_effect(): void
    {
        $u = $this->makeUser();
        $this->artisan('user:credentials', [
            'email'       => $u->email,
            '--new-email' => 'rotated@t.esp',
            '--password'  => 'RotatedPass1!',
        ])->assertSuccessful();

        $this->assertSame('rotated@t.esp', $u->refresh()->email);
    }

    public function test_existing_tokens_are_revoked_on_rotation(): void
    {
        $u = $this->makeUser();
        $u->createToken('old')->plainTextToken;
        $this->assertSame(1, $u->tokens()->count());

        $this->artisan('user:credentials', [
            'email'      => $u->email,
            '--password' => 'RotatedPass1!',
        ])->assertSuccessful();

        $this->assertSame(0, $u->refresh()->tokens()->count());
    }

    public function test_weak_password_is_rejected(): void
    {
        $u = $this->makeUser();
        $this->artisan('user:credentials', [
            'email'      => $u->email,
            '--password' => 'weak',
        ])->assertFailed();

        // Password unchanged.
        $this->assertTrue(Hash::check('OldPass1!', $u->refresh()->password));
    }

    public function test_missing_user_returns_failure(): void
    {
        $this->artisan('user:credentials', [
            'email'      => 'nobody@t.esp',
            '--password' => 'RotatedPass1!',
        ])->assertFailed();
    }
}
