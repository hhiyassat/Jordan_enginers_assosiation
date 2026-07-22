<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * JORD-57: audit retention bumped 7 → 10 years to match JEA 2025
 * technical-instructions manual (p. 20 & 44 / Art.23-e). These tests
 * pin both the default AND the boundary — anyone accidentally
 * reverting the default catches on the first assertion; anyone
 * off-by-one on the cutoff arithmetic catches on the boundary test.
 */
class AuditLogPruneTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->user = User::create([
            'organization_id' => $this->org->id, 'name' => 'u', 'email' => 'u@t.esp',
            'password' => 'x', 'role' => 'admin', 'is_active' => true, 'password_changed_at' => now(),
        ]);
    }

    public function test_default_retention_is_ten_years(): void
    {
        // The manual literal is "لا تقل عن عشر سنوات" — 10 years is the
        // *minimum*. Anything shorter (like the previous 7-year default)
        // fails compliance. Deployments that need longer retention can
        // still override via AUDIT_LOG_RETENTION_YEARS in .env.
        $this->assertSame(10, (int) config('esp.audit_retention_years'));
    }

    public function test_prune_deletes_rows_older_than_ten_years(): void
    {
        $this->makeLog(now()->subYears(11));         // must be pruned
        $this->makeLog(now()->subYears(10)->subDay()); // must be pruned (just past boundary)
        $freshId = $this->makeLog(now()->subYears(9)->subDays(364))->id; // must survive

        $this->artisan('audit:prune')->assertSuccessful();

        $this->assertSame(1, AuditLog::count(),
            'Only the sub-10-year row should survive');
        $this->assertNotNull(AuditLog::find($freshId),
            'The 9y364d row must not be swept up by the 10y cutoff');
    }

    public function test_prune_respects_the_env_override(): void
    {
        // If ops flip the env for a specific deployment (longer retention
        // window because a legal review demands it), the command must
        // honor it — this used to hardcode 7 in the fallback and any
        // env override was silently ignored below that.
        config(['esp.audit_retention_years' => 15]);
        $this->makeLog(now()->subYears(11)); // NOT pruned under 15-year window
        $this->makeLog(now()->subYears(16)); // pruned

        $this->artisan('audit:prune')->assertSuccessful();

        $this->assertSame(1, AuditLog::count());
    }

    public function test_dry_run_does_not_delete(): void
    {
        $this->makeLog(now()->subYears(11));
        $this->artisan('audit:prune', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(1, AuditLog::count(), 'dry-run must be side-effect-free');
    }

    private function makeLog(Carbon $createdAt): AuditLog
    {
        // Eloquent's timestamps trait overwrites created_at on create(),
        // so we backdate with a direct UPDATE after — the alternative
        // (disabling timestamps) leaks into concurrent tests.
        $log = AuditLog::create([
            'organization_id' => $this->org->id,
            'user_id'         => $this->user->id,
            'auditable_type'  => User::class,
            'auditable_id'    => $this->user->id,
            'action'          => 'test.event',
        ]);
        // audit_logs is append-only — no `updated_at` column exists —
        // so we backdate `created_at` only.
        AuditLog::where('id', $log->id)->update([
            'created_at' => $createdAt,
        ]);
        return $log->refresh();
    }
}
