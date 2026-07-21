<?php

namespace Tests\Feature;

use App\Engine\Disciplines;
use App\Models\Engineer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-76: pins the admin surface for JORD-70's boost flags.
 * Applicant / staff / auditor must NOT reach these endpoints.
 */
class OrganizationSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Organization $otherOrg;
    private User $admin;
    private User $applicant;
    private Engineer $engineer;
    private Engineer $otherOrgEngineer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->otherOrg = Organization::create([
            'name_ar' => 'other', 'name_en' => 'other', 'slug' => 'other', 'is_active' => true,
        ]);
        $this->admin = User::create([
            'organization_id' => $this->org->id, 'name' => 'admin', 'email' => 'admin@t.esp',
            'password' => 'x', 'role' => 'admin', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->applicant = User::create([
            'organization_id' => $this->org->id, 'name' => 'a', 'email' => 'a@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->engineer = Engineer::create([
            'organization_id' => $this->org->id, 'office_user_id' => $this->applicant->id,
            'name_ar' => 'م', 'membership_number' => 'EN-001',
            'specialization' => Disciplines::ARCHITECTURAL,
        ]);
        $this->otherOrgEngineer = Engineer::create([
            'organization_id' => $this->otherOrg->id, 'office_user_id' => $this->applicant->id,
            'name_ar' => 'م', 'membership_number' => 'EN-X',
            'specialization' => Disciplines::MECHANICAL,
        ]);
    }

    public function test_show_returns_flags_and_engineer_roster(): void
    {
        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/admin/organization');
        $res->assertOk();
        $res->assertJsonPath('organization.has_excellence_award', false);
        $res->assertJsonPath('organization.is_bit_khibra', false);
        $res->assertJsonPath('organization.has_iso_cert', false);
        // Engineer list scoped to this org — otherOrgEngineer must NOT appear.
        $ids = collect($res->json('engineers'))->pluck('id')->all();
        $this->assertContains($this->engineer->id, $ids);
        $this->assertNotContains($this->otherOrgEngineer->id, $ids);
    }

    public function test_update_toggles_the_flags(): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson('/api/v1/admin/organization', [
            'has_excellence_award' => true,
            'is_bit_khibra'        => true,
        ])->assertOk();

        $fresh = $this->org->fresh();
        $this->assertTrue($fresh->has_excellence_award);
        $this->assertTrue($fresh->is_bit_khibra);
        $this->assertFalse($fresh->has_iso_cert, 'Untouched flag must stay false');
    }

    public function test_update_engineer_toggles_specialization_head(): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/admin/engineers/{$this->engineer->id}", [
            'is_specialization_head' => true,
        ])->assertOk();
        $this->assertTrue($this->engineer->fresh()->is_specialization_head);
    }

    public function test_update_engineer_refuses_cross_org_lookup(): void
    {
        // Cross-org attack: admin tries to toggle another office's engineer.
        // findOrFail scoped to admin's own org → 404.
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/admin/engineers/{$this->otherOrgEngineer->id}", [
            'is_specialization_head' => true,
        ])->assertNotFound();
        $this->assertFalse($this->otherOrgEngineer->fresh()->is_specialization_head);
    }

    public function test_applicant_cannot_access(): void
    {
        Sanctum::actingAs($this->applicant);
        $this->getJson('/api/v1/admin/organization')->assertForbidden();
        $this->patchJson('/api/v1/admin/organization', ['has_iso_cert' => true])->assertForbidden();
        $this->patchJson("/api/v1/admin/engineers/{$this->engineer->id}", ['is_specialization_head' => true])->assertForbidden();
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/admin/organization')->assertUnauthorized();
    }
}
