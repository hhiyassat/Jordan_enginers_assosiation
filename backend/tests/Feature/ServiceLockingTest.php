<?php

namespace Tests\Feature;

use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pins the "locked service" contract: every content mutation on a locked
 * row is refused with 423, both admin and superuser can toggle the lock,
 * and unlock is a separate explicit call (not a side-effect of update).
 */
class ServiceLockingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $superuser;
    private User $staff;
    private ServiceDefinition $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'org', 'name_en' => 'org', 'slug' => 'org', 'is_active' => true,
        ]);
        $this->admin     = $this->makeUser('admin',     'admin@t.esp');
        $this->superuser = $this->makeUser('superuser', 'super@t.esp');
        $this->staff     = $this->makeUser('staff',     'staff@t.esp');
        $this->service   = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'    => 'TST-001',
            'name_ar' => 'خدمة اختبار',
            'name_en' => 'Test Service',
            'currency'=> 'JOD',
            'schema'  => $this->minimalSchema(),
            'status'  => 'active',
            'is_locked' => true,
        ]);
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'organization_id' => $this->org->id,
            'name' => $role, 'email' => $email,
            'password' => Hash::make('Secret123!'),
            'role' => $role, 'is_active' => true,
            'password_changed_at' => now(),
        ]);
    }

    private function minimalSchema(): array
    {
        return [
            'workflow' => [
                'stages' => [[
                    'id' => 'review', 'label_ar' => 'مراجعة',
                    'role' => 'staff', 'sla_hours' => 24,
                    'actions' => ['approve', 'reject'],
                ]],
            ],
        ];
    }

    public function test_default_is_locked(): void
    {
        // The migration defaults new rows to locked so a freshly-loaded
        // catalog is protected out of the box.
        $this->assertTrue($this->service->fresh()->is_locked);
    }

    public function test_update_on_locked_service_returns_423(): void
    {
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/v1/services/{$this->service->id}", ['name_ar' => 'محاولة تعديل'])
            ->assertStatus(423)
            ->assertJsonPath('error', 'service_locked');
        $this->assertSame('خدمة اختبار', $this->service->fresh()->name_ar);
    }

    public function test_status_change_on_locked_service_returns_423(): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/services/{$this->service->id}/status", ['status' => 'draft'])
            ->assertStatus(423);
        $this->assertSame('active', $this->service->fresh()->status);
    }

    public function test_admin_can_unlock_a_service(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/services/{$this->service->id}/unlock")->assertOk();
        $this->assertFalse($this->service->fresh()->is_locked);
    }

    public function test_admin_can_relock_after_editing(): void
    {
        $this->service->update(['is_locked' => false]);
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/services/{$this->service->id}/lock")->assertOk();
        $this->assertTrue($this->service->fresh()->is_locked);
    }

    public function test_superuser_can_toggle_lock(): void
    {
        Sanctum::actingAs($this->superuser);
        $this->postJson("/api/v1/admin/services/{$this->service->id}/unlock")->assertOk();
        $this->assertFalse($this->service->fresh()->is_locked);
        $this->postJson("/api/v1/admin/services/{$this->service->id}/lock")->assertOk();
        $this->assertTrue($this->service->fresh()->is_locked);
    }

    public function test_staff_cannot_unlock(): void
    {
        Sanctum::actingAs($this->staff);
        $this->postJson("/api/v1/admin/services/{$this->service->id}/unlock")->assertStatus(403);
        $this->assertTrue($this->service->fresh()->is_locked, 'staff must not sneak past the tier gate');
    }

    public function test_update_after_unlock_succeeds(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/services/{$this->service->id}/unlock")->assertOk();
        $this->putJson("/api/v1/services/{$this->service->id}", ['name_ar' => 'اسم جديد'])
            ->assertOk();
        $this->assertSame('اسم جديد', $this->service->fresh()->name_ar);
    }

    public function test_superuser_can_edit_after_unlock(): void
    {
        // Regression: prior to this feature the services route was
        // role:admin, so superuser got a 403 even without any lock. The
        // canEditServices() helper should let them through when unlocked.
        Sanctum::actingAs($this->superuser);
        $this->postJson("/api/v1/admin/services/{$this->service->id}/unlock")->assertOk();
        $this->putJson("/api/v1/services/{$this->service->id}", ['name_ar' => 'من المستخدم الأعلى'])
            ->assertOk();
    }

    public function test_newly_created_service_defaults_to_locked(): void
    {
        // Regression pin for the migration default. If someone changes the
        // column default to false, the entire "unlock is intentional" story
        // collapses — every new service would start editable.
        $this->service->update(['is_locked' => false]);
        $fresh = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code'    => 'TST-002',
            'name_ar' => 'خدمة جديدة',
            'name_en' => 'New Service',
            'currency'=> 'JOD',
            'schema'  => $this->minimalSchema(),
            'status'  => 'active',
            // Deliberately NOT passing is_locked — leaning on the column default.
        ]);
        // Re-hydrate from DB so the column default kicks in — Eloquent's
        // in-memory model doesn't backfill column defaults on create().
        $this->assertTrue($fresh->fresh()->is_locked, 'DB default must be true (locked)');
    }

    public function test_chat_schema_endpoint_refuses_when_target_service_is_locked(): void
    {
        // The AI chat-update endpoint accepts an optional service_id — when
        // present, the endpoint must refuse if that target is locked so the
        // "protection when locked" contract is uniform across every edit path.
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/admin/services/chat-schema', [
            'message'        => 'add a phone field',
            'current_schema' => $this->minimalSchema(),
            'service_id'     => $this->service->id,
        ])->assertStatus(423)
          ->assertJsonPath('error', 'service_locked');
    }

    public function test_chat_schema_endpoint_ignores_missing_service_id(): void
    {
        // Callers producing a brand-new schema (no target yet) can omit
        // service_id — the lock guard must not fire in that case. The
        // endpoint still requires a valid API key which is absent in tests,
        // so we expect a 503 (config missing), not a 423.
        Sanctum::actingAs($this->admin);
        $r = $this->postJson('/api/v1/admin/services/chat-schema', [
            'message'        => 'draft a new car-rental service',
            'current_schema' => $this->minimalSchema(),
        ]);
        // The exact status depends on the ANTHROPIC_API_KEY test env. What
        // matters is that we did NOT hit the 423 lock guard.
        $this->assertNotSame(423, $r->status(),
            'Missing service_id must NOT trigger the lock guard');
    }
}
