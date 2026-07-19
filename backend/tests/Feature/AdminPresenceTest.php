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
 * JORD-24: admin online/offline/idle presence.
 *
 * • TrackUserActivity middleware bumps last_seen_at on every
 *   authenticated request (coalesced to at most once per minute).
 * • GET /admin/users returns a `presence` field per row: online
 *   (<=5m), idle (<=30m), offline (>30m or never seen).
 */
class AdminPresenceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org',
            'slug' => 'org-presence-' . uniqid(),
            'is_active' => true,
        ]);
        $this->admin = $this->makeUser('admin', 'admin-p@t.esp');
    }

    private function makeUser(string $role, string $email, ?string $seenAgo = null): User
    {
        $seen = null;
        if ($seenAgo === 'now')     $seen = now();
        if ($seenAgo === '2min')    $seen = now()->subMinutes(2);
        if ($seenAgo === '10min')   $seen = now()->subMinutes(10);
        if ($seenAgo === '1h')      $seen = now()->subHour();

        return User::create([
            'organization_id' => $this->org->id,
            'name' => $role, 'email' => $email,
            'password' => Hash::make('Secret123!'),
            'role' => $role, 'is_active' => true,
            'password_changed_at' => now(),
            'last_seen_at' => $seen,
        ]);
    }

    public function test_listUsers_bucketises_presence_status(): void
    {
        $this->makeUser('applicant', 'never@t.esp',   null);
        $this->makeUser('applicant', 'online@t.esp',  'now');
        $this->makeUser('applicant', 'still@t.esp',   '2min');
        $this->makeUser('applicant', 'idle@t.esp',    '10min');
        $this->makeUser('applicant', 'offline@t.esp', '1h');

        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/admin/users');
        $res->assertOk();

        $byEmail = collect($res->json('users'))->keyBy('email');
        $this->assertSame('offline', $byEmail['never@t.esp']['presence']);
        $this->assertSame('online',  $byEmail['online@t.esp']['presence']);
        $this->assertSame('online',  $byEmail['still@t.esp']['presence']);
        $this->assertSame('idle',    $byEmail['idle@t.esp']['presence']);
        $this->assertSame('offline', $byEmail['offline@t.esp']['presence']);
    }

    public function test_middleware_bumps_last_seen_at_on_authenticated_request(): void
    {
        $user = $this->makeUser('applicant', 'bump@t.esp', null);
        Sanctum::actingAs($user);

        $this->assertNull($user->fresh()->last_seen_at);
        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->assertNotNull($user->fresh()->last_seen_at);
    }

    public function test_middleware_coalesces_writes_to_at_most_once_per_minute(): void
    {
        // Set last_seen_at to just now — the middleware must NOT touch it
        // again until a minute has passed. Prevents write-amplification
        // when the frontend polls the bell endpoint every 30s.
        $user = $this->makeUser('applicant', 'coalesce@t.esp', 'now');
        $stampBefore = $user->fresh()->last_seen_at;
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->assertTrue($stampBefore->equalTo($user->fresh()->last_seen_at));
    }
}
