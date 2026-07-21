<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-85 admin fee editor — PATCH /admin/services/{id}/fee.
 * Focused fee-only editor: admins can set fixed / per_unit / free
 * without sending the whole schema. Locked services still refuse.
 */
class ServiceFeeEditorTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $applicant;
    private ServiceDefinition $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->admin = User::create([
            'organization_id' => $this->org->id, 'name' => 'admin', 'email' => 'a@t.esp',
            'password' => 'x', 'role' => 'admin', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->applicant = User::create([
            'organization_id' => $this->org->id, 'name' => 'office', 'email' => 'o@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->svc = ServiceDefinition::create([
            'organization_id' => $this->org->id, 'code' => 'SVC-1',
            'name_ar' => 's', 'name_en' => 's', 'status' => 'draft', 'is_locked' => false,
            'schema' => ['fee' => ['type' => 'fixed', 'amount' => 0, 'currency' => 'JOD']],
        ]);
    }

    public function test_admin_can_set_fixed_fee(): void
    {
        Sanctum::actingAs($this->admin);
        $res = $this->patchJson("/api/v1/admin/services/{$this->svc->id}/fee", [
            'type' => 'fixed', 'amount' => 250, 'currency' => 'JOD',
        ]);
        $res->assertOk();
        $fee = $this->svc->fresh()->schema['fee'];
        $this->assertSame('fixed', $fee['type']);
        $this->assertEqualsWithDelta(250, (float) $fee['amount'], 0.01);
        $this->assertSame('JOD', $fee['currency']);
        // Audit source captures who + when.
        $this->assertStringContainsString('#'.$this->admin->id, $fee['source']);
    }

    public function test_admin_can_set_per_unit_fee(): void
    {
        Sanctum::actingAs($this->admin);
        $res = $this->patchJson("/api/v1/admin/services/{$this->svc->id}/fee", [
            'type' => 'per_unit', 'basis' => 'area_m2', 'rate' => 2.5, 'currency' => 'JOD',
        ]);
        $res->assertOk();
        $fee = $this->svc->fresh()->schema['fee'];
        $this->assertSame('per_unit', $fee['type']);
        $this->assertSame('area_m2', $fee['basis']);
        $this->assertEqualsWithDelta(2.5, (float) $fee['rate'], 0.001);
    }

    public function test_per_unit_without_basis_or_rate_is_rejected(): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/admin/services/{$this->svc->id}/fee", [
            'type' => 'per_unit', 'currency' => 'JOD',
        ])->assertStatus(422);
    }

    public function test_free_fee_is_a_flag_only_block(): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/admin/services/{$this->svc->id}/fee", [
            'type' => 'free',
        ])->assertOk();
        $fee = $this->svc->fresh()->schema['fee'];
        $this->assertSame('free', $fee['type']);
        $this->assertArrayNotHasKey('amount', $fee);
        $this->assertArrayNotHasKey('basis', $fee);
    }

    public function test_preserves_existing_surcharges_across_edits(): void
    {
        // Seed a fee with an existing surcharge (like SiteSurveyFeesSeeder does).
        $this->svc->update(['schema' => ['fee' => [
            'type' => 'per_unit', 'basis' => 'length_lm', 'rate' => 0.15, 'currency' => 'JOD',
            'surcharges' => [['code' => 'syndicate_1pct', 'kind' => 'percent_of_base', 'rate' => 0.01]],
        ]]]);
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/admin/services/{$this->svc->id}/fee", [
            'type' => 'per_unit', 'basis' => 'length_lm', 'rate' => 0.20, 'currency' => 'JOD',
        ])->assertOk();
        $fee = $this->svc->fresh()->schema['fee'];
        $this->assertEqualsWithDelta(0.20, (float) $fee['rate'], 0.001);
        $this->assertNotEmpty($fee['surcharges']);
        $this->assertSame('syndicate_1pct', $fee['surcharges'][0]['code']);
    }

    public function test_locked_service_refuses_fee_edit(): void
    {
        $this->svc->update(['is_locked' => true]);
        Sanctum::actingAs($this->admin);
        // The catalog controller returns a JSON envelope with 423 (Locked).
        $this->patchJson("/api/v1/admin/services/{$this->svc->id}/fee", [
            'type' => 'fixed', 'amount' => 10, 'currency' => 'JOD',
        ])->assertStatus(423);
    }

    public function test_non_admin_is_forbidden(): void
    {
        Sanctum::actingAs($this->applicant);
        $this->patchJson("/api/v1/admin/services/{$this->svc->id}/fee", [
            'type' => 'fixed', 'amount' => 10, 'currency' => 'JOD',
        ])->assertForbidden();
    }
}
