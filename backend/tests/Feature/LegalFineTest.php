<?php

namespace Tests\Feature;

use App\Models\LegalFine;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-82: legal fines (Art.14 owner fines for using an unlicensed
 * contractor). Pins the range + area-consistency rules the manual
 * spells out.
 */
class LegalFineTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $other;

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
        $this->other = User::create([
            'organization_id' => $this->org->id, 'name' => 'x', 'email' => 'x@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
    }

    public function test_bounds_table_matches_manual(): void
    {
        // Pin the exact ranges the manual quotes so a future edit
        // that changes the small-max to 50000 gets caught here.
        $this->assertSame(1000, LegalFine::BOUNDS['unlicensed_contractor_small']['min']);
        $this->assertSame(5000, LegalFine::BOUNDS['unlicensed_contractor_small']['max']);
        $this->assertSame(5000, LegalFine::BOUNDS['unlicensed_contractor_large']['min']);
        $this->assertSame(50000, LegalFine::BOUNDS['unlicensed_contractor_large']['max']);
        $this->assertSame(250, LegalFine::BOUNDS['unlicensed_contractor_small']['area_threshold_m2']);
    }

    public function test_admin_can_issue_a_fine_within_range(): void
    {
        Sanctum::actingAs($this->admin);
        $res = $this->postJson('/api/v1/admin/legal-fines', [
            'kind'           => 'unlicensed_contractor_small',
            'amount_jod'     => 2500,
            'target_display' => 'محمد الأحمد',
            'project_area_m2' => 200,
            'reason'         => 'استخدام مقاول غير مرخّص لمشروع بمساحة 200 م².',
        ]);
        $res->assertCreated();
        $fine = LegalFine::first();
        $this->assertNotNull($fine);
        $this->assertSame(2500.00, (float) $fine->amount_jod);
        $this->assertSame('محمد الأحمد', $fine->target_display);
        $this->assertSame($this->admin->id, $fine->issued_by_user_id);
        $this->assertNotNull($fine->issued_at);
    }

    public function test_amount_below_range_rejected(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/admin/legal-fines', [
            'kind'           => 'unlicensed_contractor_small',
            'amount_jod'     => 500,  // below 1000 minimum
            'target_display' => 'x',
            'reason'         => 'not enough amount below the min bound.',
        ])->assertStatus(422)
          ->assertJsonPath('errors.amount_jod', fn ($msg) => str_contains($msg, '1000'));
    }

    public function test_amount_above_range_rejected(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/admin/legal-fines', [
            'kind'           => 'unlicensed_contractor_small',
            'amount_jod'     => 6000,  // above 5000 max for small
            'target_display' => 'x',
            'reason'         => 'over the small tier maximum this cannot pass.',
        ])->assertStatus(422);
    }

    public function test_large_kind_below_range_rejected(): void
    {
        Sanctum::actingAs($this->admin);
        // Trying to use "large" kind with 4000 → below its 5000 min.
        $this->postJson('/api/v1/admin/legal-fines', [
            'kind'           => 'unlicensed_contractor_large',
            'amount_jod'     => 4000,
            'target_display' => 'x',
            'reason'         => 'amount too low for the large tier bound.',
        ])->assertStatus(422);
    }

    public function test_small_kind_with_over_threshold_area_is_rejected(): void
    {
        // area = 300 → doesn't fit small (which requires ≤ 250).
        // Admin picked wrong tier.
        Sanctum::actingAs($this->admin);
        $res = $this->postJson('/api/v1/admin/legal-fines', [
            'kind'           => 'unlicensed_contractor_small',
            'amount_jod'     => 3000,
            'target_display' => 'x',
            'project_area_m2' => 300,
            'reason'         => 'area indicates the large tier applies here.',
        ]);
        $res->assertStatus(422);
        $res->assertJsonPath('errors.kind', fn ($msg) => str_contains($msg, 'مساحة'));
    }

    public function test_large_kind_with_below_threshold_area_is_rejected(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/admin/legal-fines', [
            'kind'           => 'unlicensed_contractor_large',
            'amount_jod'     => 10000,
            'target_display' => 'x',
            'project_area_m2' => 150,  // small tier by area
            'reason'         => 'area indicates the small tier applies here.',
        ])->assertStatus(422);
    }

    public function test_mark_paid_records_reference_and_timestamp(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/admin/legal-fines', [
            'kind'           => 'unlicensed_contractor_large',
            'amount_jod'     => 12000,
            'target_display' => 'شركة الأمثلة',
            'project_area_m2' => 500,
            'reason'         => 'استخدام مقاول غير مرخص لمبنى 500 م².',
        ])->assertCreated();

        $fine = LegalFine::first();
        $this->postJson("/api/v1/admin/legal-fines/{$fine->id}/pay", [
            'payment_reference' => 'COURT-REF-2026-42',
        ])->assertOk();

        $fresh = $fine->fresh();
        $this->assertNotNull($fresh->paid_at);
        $this->assertSame('COURT-REF-2026-42', $fresh->payment_reference);
    }

    public function test_double_payment_refused(): void
    {
        $fine = LegalFine::create([
            'organization_id'   => $this->org->id,
            'target_display'    => 'x', 'kind' => 'unlicensed_contractor_small',
            'amount_jod'        => 1500, 'reason' => 'x',
            'issued_by_user_id' => $this->admin->id,
            'issued_at'         => now(), 'paid_at' => now(),
        ]);
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/legal-fines/{$fine->id}/pay", [
            'payment_reference' => 'BR',
        ])->assertStatus(422);
    }

    public function test_pay_refuses_cross_org_lookup(): void
    {
        $otherOrg = Organization::create([
            'name_ar' => 'o', 'name_en' => 'o', 'slug' => 'o', 'is_active' => true,
        ]);
        $fine = LegalFine::create([
            'organization_id'   => $otherOrg->id,
            'target_display'    => 'x', 'kind' => 'unlicensed_contractor_small',
            'amount_jod'        => 1500, 'reason' => 'x',
            'issued_by_user_id' => $this->admin->id, 'issued_at' => now(),
        ]);
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/legal-fines/{$fine->id}/pay", [
            'payment_reference' => 'HACK',
        ])->assertNotFound();
    }

    public function test_applicant_cannot_issue_or_list(): void
    {
        Sanctum::actingAs($this->other);
        $this->postJson('/api/v1/admin/legal-fines', [
            'kind' => 'unlicensed_contractor_small', 'amount_jod' => 1500,
            'target_display' => 'x', 'reason' => 'xxxxxxxxxxxxxxxxxxxx',
        ])->assertForbidden();
        $this->getJson('/api/v1/admin/legal-fines')->assertForbidden();
    }

    public function test_index_returns_bounds_alongside_fines(): void
    {
        LegalFine::create([
            'organization_id'   => $this->org->id,
            'target_display'    => 'x', 'kind' => 'unlicensed_contractor_small',
            'amount_jod'        => 2000, 'reason' => 'x',
            'issued_by_user_id' => $this->admin->id, 'issued_at' => now(),
        ]);
        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/admin/legal-fines');
        $res->assertOk();
        $this->assertArrayHasKey('bounds', $res->json());
        $this->assertSame(50000, $res->json('bounds.unlicensed_contractor_large.max'));
    }
}
