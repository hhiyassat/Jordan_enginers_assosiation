<?php

namespace Tests\Feature;

use App\Http\Middleware\ReadTokenFromCookie;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * JORD-52 (PM): session cookie is env-driven.
 *
 * Ops can tune the lifetime (ESP_SESSION_LIFETIME_MINUTES) and the
 * Secure flag (ESP_SESSION_COOKIE_SECURE) per deployment without
 * a code change. Two symptoms this addresses:
 *   • "reload kicks to login" on a deploy where the default 8-hour
 *     lifetime is too short for the workflow.
 *   • Cookie never comes back over http:// behind a TLS-terminating
 *     load balancer — the browser refuses to send a Secure cookie
 *     on an insecure origin.
 */
class SessionCookieConfigTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create([
            'name_ar' => 'x', 'name_en' => 'x', 'slug' => 'x', 'is_active' => true,
        ]);
        $this->user = User::create([
            'organization_id' => $org->id,
            'name' => 'u', 'email' => 'u@t.esp',
            'password' => Hash::make('Secret123!'),
            'role' => 'admin', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
    }

    private function login(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'u@t.esp', 'password' => 'Secret123!',
            'captcha_id' => 'test-bypass', 'captcha_answer' => 'test-bypass',
        ]);
    }

    public function test_default_cookie_uses_the_8_hour_lifetime(): void
    {
        $res = $this->login()->assertOk();
        $cookie = collect($res->headers->getCookies())
            ->first(fn ($c) => $c->getName() === ReadTokenFromCookie::COOKIE_NAME);

        $this->assertNotNull($cookie);
        // Expires ~8h from now (480 min). Allow a 60s cushion.
        $expected = now()->addMinutes(480)->timestamp;
        $this->assertEqualsWithDelta($expected, $cookie->getExpiresTime(), 60);
    }

    public function test_env_can_extend_the_cookie_lifetime(): void
    {
        putenv('ESP_SESSION_LIFETIME_MINUTES=1440'); // 24 hours
        $res = $this->login()->assertOk();
        putenv('ESP_SESSION_LIFETIME_MINUTES');     // clean up for other tests

        $cookie = collect($res->headers->getCookies())
            ->first(fn ($c) => $c->getName() === ReadTokenFromCookie::COOKIE_NAME);

        $expected = now()->addMinutes(1440)->timestamp;
        $this->assertEqualsWithDelta($expected, $cookie->getExpiresTime(), 60);
    }

    public function test_env_lifetime_is_clamped_to_a_sane_range(): void
    {
        // Below the 30-minute floor → floor wins.
        putenv('ESP_SESSION_LIFETIME_MINUTES=1');
        $cookie = collect($this->login()->assertOk()->headers->getCookies())
            ->first(fn ($c) => $c->getName() === ReadTokenFromCookie::COOKIE_NAME);
        putenv('ESP_SESSION_LIFETIME_MINUTES');

        $expected = now()->addMinutes(30)->timestamp;
        $this->assertEqualsWithDelta($expected, $cookie->getExpiresTime(), 60);
    }

    public function test_secure_flag_auto_defaults_to_false_in_non_production_envs(): void
    {
        // The test env isn't 'production'. Default 'auto' → secure=false.
        $cookie = collect($this->login()->assertOk()->headers->getCookies())
            ->first(fn ($c) => $c->getName() === ReadTokenFromCookie::COOKIE_NAME);

        $this->assertFalse($cookie->isSecure(),
            'Non-production env with ESP_SESSION_COOKIE_SECURE=auto must emit a non-Secure cookie');
    }

    public function test_env_can_force_secure_off_even_when_production(): void
    {
        // Simulate production behind an HTTP-facing LB: ops wants
        // Secure explicitly off.
        putenv('ESP_SESSION_COOKIE_SECURE=false');
        $cookie = collect($this->login()->assertOk()->headers->getCookies())
            ->first(fn ($c) => $c->getName() === ReadTokenFromCookie::COOKIE_NAME);
        putenv('ESP_SESSION_COOKIE_SECURE');

        $this->assertFalse($cookie->isSecure());
    }

    public function test_env_can_force_secure_on_regardless_of_env(): void
    {
        putenv('ESP_SESSION_COOKIE_SECURE=true');
        $cookie = collect($this->login()->assertOk()->headers->getCookies())
            ->first(fn ($c) => $c->getName() === ReadTokenFromCookie::COOKIE_NAME);
        putenv('ESP_SESSION_COOKIE_SECURE');

        $this->assertTrue($cookie->isSecure());
    }
}
