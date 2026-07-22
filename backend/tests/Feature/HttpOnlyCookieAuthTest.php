<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ReadTokenFromCookie;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * JORD-30 — httpOnly cookie carrier for the Sanctum bearer token.
 *
 * These tests pin:
 *   1. Login writes an esp_session cookie with the right flags
 *      (httpOnly, SameSite=Strict).
 *   2. A subsequent request that carries ONLY the cookie (no
 *      Authorization header) succeeds — the ReadTokenFromCookie
 *      middleware promotes it before Sanctum runs.
 *   3. Logout clears the cookie.
 *   4. An explicit bearer header STILL wins if both are present
 *      (backward compat with integrations that mint their own).
 */
class HttpOnlyCookieAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Bypass the captcha layer that protects the login/register
        // routes. Other suites do the same via CAPTCHA_ENABLED=false.
        config(['esp.captcha_enabled' => false]);
        $org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org',
            'slug' => 'org-cookie-' . uniqid(),
            'is_active' => true,
        ]);
        $this->user = User::create([
            'organization_id' => $org->id,
            'name' => 'ap', 'email' => 'ap-cookie@t.esp',
            'password' => Hash::make('Secret123!'),
            'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
    }

    /**
     * Extract the raw cookie object from a Laravel test response so we
     * can inspect the httpOnly / SameSite flags directly instead of just
     * checking that the cookie value round-trips.
     */
    private function extractSessionCookie(\Illuminate\Testing\TestResponse $res): \Symfony\Component\HttpFoundation\Cookie
    {
        foreach ($res->headers->getCookies() as $c) {
            if ($c->getName() === ReadTokenFromCookie::COOKIE_NAME) {
                return $c;
            }
        }
        $this->fail('esp_session cookie was not set on the response');
    }

    public function test_login_sets_httponly_samesite_strict_cookie(): void
    {
        $res = $this->postJson('/api/v1/auth/login', [
            'email' => 'ap-cookie@t.esp',
            'password' => 'Secret123!',
        ]);
        $res->assertOk();
        $cookie = $this->extractSessionCookie($res);
        $this->assertTrue($cookie->isHttpOnly(), 'session cookie must be httpOnly');
        $this->assertSame('strict', $cookie->getSameSite(), 'session cookie must be SameSite=Strict');
        $this->assertNotEmpty($cookie->getValue(), 'session cookie must carry the token');
        // JSON still carries the token for backward compat.
        $this->assertNotEmpty($res->json('token'));
    }

    public function test_cookie_alone_authenticates_subsequent_requests(): void
    {
        // Login → grab the cookie's raw value.
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'ap-cookie@t.esp',
            'password' => 'Secret123!',
        ]);
        $token = $this->extractSessionCookie($loginRes)->getValue();

        // Follow-up request with ONLY the cookie — no Authorization header.
        $me = $this->call('GET', '/api/v1/auth/me', [], [
            ReadTokenFromCookie::COOKIE_NAME => $token,
        ], [], ['HTTP_ACCEPT' => 'application/json']);
        $me->assertOk()->assertJsonPath('user.email', 'ap-cookie@t.esp');
    }

    public function test_logout_clears_the_session_cookie(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'ap-cookie@t.esp',
            'password' => 'Secret123!',
        ]);
        $token = $this->extractSessionCookie($loginRes)->getValue();

        $res = $this->call('POST', '/api/v1/auth/logout', [], [
            ReadTokenFromCookie::COOKIE_NAME => $token,
        ], [], ['HTTP_ACCEPT' => 'application/json']);
        $res->assertOk();

        // The response should carry a clearing cookie: empty value
        // (Symfony represents this as '' or null depending on version)
        // AND a past-expiry timestamp so the browser drops it.
        $cleared = $this->extractSessionCookie($res);
        $this->assertContains($cleared->getValue(), ['', null], 'cleared cookie should have empty value');
        $this->assertLessThan(time(), $cleared->getExpiresTime(), 'cleared cookie must expire in the past');
    }

    public function test_explicit_bearer_header_still_wins_when_both_present(): void
    {
        // Backward-compat: an integration script that mints its own
        // token via /auth/login and continues sending an Authorization
        // header must keep working even if a stale cookie is around.
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'ap-cookie@t.esp',
            'password' => 'Secret123!',
        ]);
        $token = $loginRes->json('token');
        $this->assertNotEmpty($token);

        $me = $this->call('GET', '/api/v1/auth/me', [], [
            ReadTokenFromCookie::COOKIE_NAME => 'stale-cookie-value-ignored',
        ], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        $me->assertOk()->assertJsonPath('user.email', 'ap-cookie@t.esp');
    }

    /**
     * JORD-84 (PM): GET /auth/me is now a public identity probe.
     * A missing cookie and missing header return 200 + {user: null}
     * instead of 401 so a blind first-load call doesn't surface as
     * a red row in the browser console. Any actual protected route
     * still 401s — that invariant lives in other tests.
     */
    public function test_missing_cookie_and_missing_header_returns_null_user_200(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertExactJson(['user' => null]);
    }
}
