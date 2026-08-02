<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * fix/frontend-vite-proxy-port · covers the CAPTCHA-off / CAPTCHA-on
 * local-development contract.
 *
 * The reporter's exact scenario:
 *   • frontend has VITE_CAPTCHA_ENABLED=false (widget hidden)
 *   • docker-compose ships CAPTCHA_ENABLED=true (backend requires captcha)
 *   • login returns 422 with errors.captcha_answer and the browser
 *     shows an unexplained rejection.
 *
 * These tests pin down BOTH behaviours so a future config drift is
 * caught by the suite.
 */
class LoginCaptchaModeTest extends TestCase
{
    use RefreshDatabase;

    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create([
            'name_ar'   => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->applicant = User::create([
            'organization_id'     => $org->id,
            'name'                => 'أحمد المقدم',
            'email'               => 'ahmed@demo.esp',
            'password'            => Hash::make('Demo1234!'),
            'role'                => 'applicant',
            'is_active'           => true,
            'password_changed_at' => now(),
        ]);
    }

    // (1) CAPTCHA disabled: login does NOT require captcha_id/captcha_answer.
    public function test_login_without_captcha_payload_succeeds_when_backend_captcha_is_disabled(): void
    {
        config(['esp.captcha_enabled' => false]);

        $r = $this->postJson('/api/v1/auth/login', [
            'email'    => 'ahmed@demo.esp',
            'password' => 'Demo1234!',
        ]);
        $r->assertOk();
        $r->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }

    // (2) CAPTCHA enabled: missing captcha payload returns structured 422.
    public function test_login_returns_422_when_backend_captcha_is_enabled_and_payload_missing(): void
    {
        config(['esp.captcha_enabled' => true]);

        $r = $this->postJson('/api/v1/auth/login', [
            'email'    => 'ahmed@demo.esp',
            'password' => 'Demo1234!',
        ]);
        $r->assertStatus(422);
        $r->assertJsonPath('errors.captcha_answer.0', 'رمز التحقق غير صحيح.');
        $r->assertJsonPath('captcha_failed', true);
    }

    // (3) /api/v1/auth/me returns {"user":null} before authentication.
    public function test_auth_me_returns_null_user_before_login(): void
    {
        $r = $this->getJson('/api/v1/auth/me');
        $r->assertOk();
        $r->assertExactJson(['user' => null]);
    }

    // (4) /api/v1/auth/me returns the authenticated user after login.
    public function test_auth_me_returns_authenticated_user_after_login(): void
    {
        config(['esp.captcha_enabled' => false]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email'    => 'ahmed@demo.esp',
            'password' => 'Demo1234!',
        ])->json();

        $r = $this->withHeader('Authorization', 'Bearer ' . $login['token'])
            ->getJson('/api/v1/auth/me');
        $r->assertOk();
        $r->assertJsonPath('user.email', 'ahmed@demo.esp');
    }

    // (5) CAPTCHA enabled + correct captcha_answer passes.
    public function test_login_with_valid_captcha_succeeds_when_backend_captcha_is_enabled(): void
    {
        config(['esp.captcha_enabled' => true]);

        // The captcha service stores the expected answer in cache under
        // `captcha:{id}`. Seed a known challenge directly so we don't
        // depend on GET /captcha.
        $id     = 'test-captcha-id';
        $answer = 'ABC123';
        Cache::put('captcha:' . $id, $answer, now()->addMinutes(5));

        $r = $this->postJson('/api/v1/auth/login', [
            'email'          => 'ahmed@demo.esp',
            'password'       => 'Demo1234!',
            'captcha_id'     => $id,
            'captcha_answer' => $answer,
        ]);
        $r->assertOk();
    }
}
