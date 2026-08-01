<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * P0-E-2 · Security event logging.
 *
 * Pins the invariant that AuthController, CheckRole, and the Nashmi
 * middleware emit their events on the `security` channel with the
 * documented event names — so ops dashboards and incident-response
 * queries can rely on the shape not drifting.
 */
class SecurityEventLoggingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $applicant;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org', 'slug' => 'org-sec', 'is_active' => true,
        ]);
        $this->applicant = User::create([
            'organization_id'    => $this->org->id, 'name' => 'a',
            'email'              => 'sec-app@t.esp', 'password' => Hash::make('Secret123!'),
            'role'               => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->admin = User::create([
            'organization_id'    => $this->org->id, 'name' => 'admin',
            'email'              => 'sec-admin@t.esp', 'password' => Hash::make('Secret123!'),
            'role'               => 'admin', 'is_active' => true, 'password_changed_at' => now(),
        ]);
    }

    /**
     * Helper: install a security-channel capture that also lets every
     * other channel (api_access, integration, default) work normally.
     * Returns an array reference that accumulates every info/warning/
     * error call made to the security channel.
     *
     * @param  array<int, array{level: string, message: string, context: array<string, mixed>}>  $captured
     */
    private function captureSecurityChannel(array &$captured): void
    {
        $spy = new class ($captured) {
            /** @var array<int, array<string, mixed>> */
            public array $bag;
            public function __construct(array &$bag)
            {
                $this->bag = &$bag;
            }
            public function info(string $message, array $context = []): void
            {
                $this->bag[] = ['level' => 'info', 'message' => $message, 'context' => $context];
            }
            public function warning(string $message, array $context = []): void
            {
                $this->bag[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
            }
            public function error(string $message, array $context = []): void
            {
                $this->bag[] = ['level' => 'error', 'message' => $message, 'context' => $context];
            }
            public function critical(string $message, array $context = []): void
            {
                $this->bag[] = ['level' => 'critical', 'message' => $message, 'context' => $context];
            }
        };

        // Swap Log::channel('security') to return our capture, while
        // every other channel goes to the real log manager.
        $realManager = app('log');
        Log::swap(new class ($realManager, $spy) {
            public function __construct(
                private readonly \Illuminate\Log\LogManager $real,
                private readonly object $spy,
            ) {}
            public function channel(?string $name = null)
            {
                return $name === 'security' ? $this->spy : $this->real->channel($name);
            }
            public function __call(string $method, array $args)
            {
                return $this->real->{$method}(...$args);
            }
        });
    }

    public function test_login_success_emits_security_event(): void
    {
        $captured = [];
        $this->captureSecurityChannel($captured);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'sec-app@t.esp',
            'password' => 'Secret123!',
        ])->assertOk();

        $events = array_column($captured, 'message');
        $this->assertContains('login_success', $events);
    }

    public function test_login_failure_emits_security_event_with_email_attempt(): void
    {
        $captured = [];
        $this->captureSecurityChannel($captured);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'sec-app@t.esp',
            'password' => 'WrongPassword!',
        ])->assertStatus(401);

        $failures = array_filter($captured, fn ($e) => $e['message'] === 'login_failed');
        $this->assertNotEmpty($failures, 'login_failed event must be emitted');

        $encoded = json_encode($captured);
        $this->assertStringNotContainsString('WrongPassword', (string) $encoded,
            'P0-E-2 invariant: passwords must never appear in security-log context.');
        $this->assertStringContainsString('sec-app@t.esp', (string) $encoded,
            'Attempted email is the audit-relevant field and MUST be captured.');
    }

    public function test_authorization_denied_emits_security_event(): void
    {
        $captured = [];
        $this->captureSecurityChannel($captured);

        \Laravel\Sanctum\Sanctum::actingAs($this->applicant);
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(403);

        $events = array_column($captured, 'message');
        $this->assertContains('authorization_denied', $events);
    }
}
