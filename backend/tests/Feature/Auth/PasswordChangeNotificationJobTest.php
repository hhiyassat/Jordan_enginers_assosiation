<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Jobs\ProcessNotificationJob;
use App\Models\Organization;
use App\Models\User;
use App\Support\PasswordHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CS-02: verify that AuthController::changePassword dispatches
 * ProcessNotificationJob so that the security notice reaches the user's
 * inbox off the request thread.
 */
class PasswordChangeNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_dispatches_security_notification(): void
    {
        Bus::fake();
        $org  = Organization::create(['name_ar' => 'Org', 'name_en' => 'Org', 'slug' => 'org-cs02']);
        $user = User::create([
            'organization_id'      => $org->id,
            'name'                 => 'Test User',
            'email'                => 'change.pw@example.test',
            'password'             => Hash::make('OldPassword-1234!'),
            'role'                 => 'applicant',
            'is_active'            => true,
            'must_change_password' => false,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/password/change', [
            'current_password'      => 'OldPassword-1234!',
            'password'              => 'BrandNewPass!!789',
            'password_confirmation' => 'BrandNewPass!!789',
        ]);

        $response->assertStatus(200);

        Bus::assertDispatched(ProcessNotificationJob::class, function ($job) use ($user) {
            return $job->userId === $user->id
                && $job->type === 'security.password_changed';
        });
    }
}
