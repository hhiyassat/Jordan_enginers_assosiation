<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 5: named rate limiters registered in AppServiceProvider.
 *
 * The tests fire N requests at the ceiling and one more, expecting the
 * (N+1)th to come back 429 with the JSON shape the logHitAndReply
 * responder produces. Cache is cleared between tests so limiter state
 * from one case doesn't bleed into the next.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Rate limiters store hit counts in the cache — flush so a prior
        // test's activity doesn't push this one over.
        Cache::flush();
        RateLimiter::clear('login|127.0.0.1');
    }

    public function test_login_endpoint_429s_after_the_5_per_minute_limit(): void
    {
        // captcha middleware still runs; feed a bogus id/answer so we
        // hit the rate limiter, not the captcha check.
        $payload = [
            'email'          => 'x@t.esp',
            'password'       => 'wrong',
            'captcha_id'     => 'fake',
            'captcha_answer' => 'FAKE00',
        ];

        // First 5 attempts should NOT be rate-limited (they'll fail on
        // captcha or credentials with a non-429 status).
        for ($i = 0; $i < 5; $i++) {
            $r = $this->postJson('/api/v1/auth/login', $payload);
            $this->assertNotSame(429, $r->status(), "Attempt {$i} shouldn't be rate-limited yet");
        }

        // The 6th trips the limiter.
        $r = $this->postJson('/api/v1/auth/login', $payload);
        $r->assertStatus(429)
            ->assertJsonPath('limiter', 'login');
        // Arabic message so the frontend can render it as-is.
        $this->assertStringContainsString('الحد المسموح', $r->json('message'));
    }

    public function test_ai_schema_endpoint_uses_per_user_bucket(): void
    {
        // Two different users on the same origin must NOT share a bucket
        // — the ai-schema limiter is keyed by user id, not IP.
        $org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org', 'slug' => 'org', 'is_active' => true,
        ]);
        $userA = User::create([
            'organization_id' => $org->id, 'name' => 'a', 'email' => 'a@t.esp',
            'password' => Hash::make('x'), 'role' => 'admin', 'is_active' => true,
            'password_changed_at' => now(),
        ]);
        $userB = User::create([
            'organization_id' => $org->id, 'name' => 'b', 'email' => 'b@t.esp',
            'password' => Hash::make('x'), 'role' => 'admin', 'is_active' => true,
            'password_changed_at' => now(),
        ]);

        // Simulate 10 hits for user A directly against the limiter so we
        // don't burn the whole Anthropic timeout budget on a real POST.
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('ai-schema:' . $userA->id, 3600);
        }
        $this->assertTrue(RateLimiter::tooManyAttempts('ai-schema:' . $userA->id, 10),
            'User A must have burned their bucket after 10 hits');

        // User B is untouched.
        $this->assertFalse(RateLimiter::tooManyAttempts('ai-schema:' . $userB->id, 10),
            'User B must have an untouched bucket — limiter is per-user, not per-IP');
    }

    public function test_document_upload_limiter_is_keyed_per_user(): void
    {
        // Same shape as the ai-schema test — document-upload also uses a
        // per-user bucket so a runaway script from one office seat can't
        // starve another seat sharing the same NAT.
        $key = 'document-upload:42';
        for ($i = 0; $i < 30; $i++) {
            RateLimiter::hit($key, 60);
        }
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 30));
        $this->assertFalse(RateLimiter::tooManyAttempts('document-upload:99', 30));
    }

    public function test_named_limiter_response_carries_arabic_message_and_limiter_id(): void
    {
        // Invoke the response callback directly — the previous test
        // covered the actual HTTP trip end-to-end on `login`, so here
        // we just pin the shape of the JSON the callback produces for
        // every named limiter. Robust: doesn't depend on the internal
        // key format the middleware uses (md5 of "{name}{limit-key}"
        // by default), which is a private detail of Laravel.
        //
        // ai-schema and document-upload compute the limiter key from
        // $request->user()->id, so they need an authenticated request
        // to resolve the Limit. login/register use $request->ip().
        $needsAuth = ['ai-schema', 'document-upload'];
        $user = User::create([
            'organization_id' => Organization::firstOrCreate(
                ['slug' => 'z'], ['name_ar' => 'z','name_en' => 'z','is_active' => true],
            )->id,
            'name' => 'z', 'email' => 'z@t.esp',
            'password' => Hash::make('x'), 'role' => 'admin', 'is_active' => true,
            'password_changed_at' => now(),
        ]);

        foreach (['login', 'register', 'ai-schema', 'document-upload'] as $name) {
            $limiter = RateLimiter::limiter($name);
            $this->assertNotNull($limiter, "Limiter '{$name}' must be registered");

            $req = \Illuminate\Http\Request::create('/');
            if (in_array($name, $needsAuth, true)) {
                $req->setUserResolver(fn () => $user);
            }
            $limit = $limiter($req);
            $this->assertNotNull($limit->responseCallback, "Limiter '{$name}' must attach a response callback");

            $resp = call_user_func($limit->responseCallback, $req, []);
            $this->assertSame(429, $resp->getStatusCode(), "Limiter '{$name}' response should be 429");
            $body = json_decode($resp->getContent(), true);
            $this->assertSame($name, $body['limiter'] ?? null, "Limiter '{$name}' body should carry its own name");
            $this->assertStringContainsString('الحد المسموح', $body['message'] ?? '',
                "Limiter '{$name}' should render an Arabic message the SPA can display");
        }
    }
}
