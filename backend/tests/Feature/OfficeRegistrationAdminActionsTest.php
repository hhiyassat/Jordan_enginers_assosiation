<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Modules\JeaProjects\Models\Engineer;
use Modules\JeaServices\Models\OfficeRegistrationRequest;
use Tests\TestCase;

/**
 * OfficeRegistrationController admin endpoints (list / show / approve / reject).
 *
 * Pins:
 *   • Non-admin cannot list, view, approve or reject.
 *   • Admin can list pending registrations.
 *   • Admin approve creates Organization + User + Engineer(s) atomically.
 *   • Admin approve normalizes 'civil' → 'structural' on Engineer.specialization.
 *   • Cannot approve a non-pending registration.
 *   • Cannot reject a non-pending registration.
 *   • Reject without notes is 422.
 *   • Reject sets status + reviewer/at without creating any entities.
 */
class OfficeRegistrationAdminActionsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $jeaOrg;
    private User $admin;
    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jeaOrg = Organization::create([
            'name_ar' => 'JEA', 'name_en' => 'JEA',
            'slug' => 'jea', 'is_active' => true,
        ]);
        $this->admin = User::create([
            'organization_id' => $this->jeaOrg->id,
            'name' => 'JEA admin', 'email' => 'admin@jea.test',
            'password' => Hash::make('x'), 'role' => 'admin',
            'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->applicant = User::create([
            'organization_id' => $this->jeaOrg->id,
            'name' => 'applicant', 'email' => 'applicant@t.co',
            'password' => Hash::make('x'), 'role' => 'applicant',
            'is_active' => true, 'password_changed_at' => now(),
        ]);
    }

    // ── authorization ────────────────────────────────────────────

    public function test_applicant_cannot_list_registrations(): void
    {
        Sanctum::actingAs($this->applicant);
        $this->getJson('/api/v1/admin/office-registrations')->assertStatus(403);
    }

    public function test_admin_can_list_registrations(): void
    {
        $this->createPending();
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/admin/office-registrations')->assertOk();
    }

    // ── approve ──────────────────────────────────────────────────

    public function test_admin_approve_creates_org_user_and_engineers(): void
    {
        $reg = $this->createPending([
            'engineers' => [
                ['name_ar'=>'م. أحمد', 'name_en'=>'Ahmad', 'membership_number'=>'M1', 'specialization'=>'architectural'],
                ['name_ar'=>'م. سارة', 'membership_number'=>'M2', 'specialization'=>'civil'], // alias
            ],
        ]);

        Sanctum::actingAs($this->admin);
        $r = $this->postJson("/api/v1/admin/office-registrations/{$reg->id}/approve", [
            'review_notes' => 'ok',
        ])->assertOk()->json();

        $orgId = $r['organization_id'];

        // Organization created
        $org = Organization::findOrFail($orgId);
        $this->assertSame($reg->office_name_ar, $org->name_ar);
        $this->assertSame($reg->office_name_en, $org->name_en);
        $this->assertTrue((bool) $org->is_active);

        // Admin User created for the new office
        $officeAdmin = User::where('email', $reg->office_email)->firstOrFail();
        $this->assertSame($orgId, $officeAdmin->organization_id);
        $this->assertSame('applicant', $officeAdmin->role);
        $this->assertTrue((bool) $officeAdmin->is_active);

        // Engineers created — 'civil' normalizes to 'structural' at persist time.
        // withoutOrgScope() because the acting admin belongs to a different
        // organization than the newly-created office — BelongsToOrganization
        // would otherwise filter these out.
        $engineers = Engineer::withoutOrgScope()->where('organization_id', $orgId)->get();
        $this->assertCount(2, $engineers);
        $specs = $engineers->pluck('specialization')->toArray();
        $this->assertContains('architectural', $specs);
        $this->assertContains('structural', $specs, "'civil' alias must fold to 'structural'");
        $this->assertNotContains('civil', $specs, "'civil' should not persist as-is; must normalize");

        // Registration record updated
        $reg->refresh();
        $this->assertSame(OfficeRegistrationRequest::STATUS_APPROVED, $reg->status);
        $this->assertSame($orgId, $reg->approved_organization_id);
        $this->assertSame($this->admin->id, $reg->reviewed_by_user_id);
        $this->assertNotNull($reg->reviewed_at);
        $this->assertSame('ok', $reg->review_notes);
    }

    public function test_admin_cannot_approve_a_non_pending_registration(): void
    {
        $reg = $this->createPending();
        $reg->update(['status' => OfficeRegistrationRequest::STATUS_REJECTED]);

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/office-registrations/{$reg->id}/approve")
            ->assertStatus(422);
    }

    public function test_applicant_cannot_approve(): void
    {
        $reg = $this->createPending();
        Sanctum::actingAs($this->applicant);
        $this->postJson("/api/v1/admin/office-registrations/{$reg->id}/approve")
            ->assertStatus(403);
    }

    // ── reject ───────────────────────────────────────────────────

    public function test_admin_reject_requires_review_notes(): void
    {
        $reg = $this->createPending();
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/office-registrations/{$reg->id}/reject", ['review_notes' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['review_notes']);
    }

    public function test_admin_reject_sets_status_without_creating_entities(): void
    {
        $reg = $this->createPending();
        $orgCount = Organization::count();
        $userCount = User::count();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/office-registrations/{$reg->id}/reject", [
            'review_notes' => 'رخصة غير صالحة',
        ])->assertOk();

        $reg->refresh();
        $this->assertSame(OfficeRegistrationRequest::STATUS_REJECTED, $reg->status);
        $this->assertSame('رخصة غير صالحة', $reg->review_notes);
        $this->assertSame($this->admin->id, $reg->reviewed_by_user_id);
        $this->assertNotNull($reg->reviewed_at);
        $this->assertNull($reg->approved_organization_id);

        // No side-effects
        $this->assertSame($orgCount, Organization::count());
        $this->assertSame($userCount, User::count());
    }

    public function test_admin_cannot_reject_a_non_pending_registration(): void
    {
        $reg = $this->createPending();
        $reg->update(['status' => OfficeRegistrationRequest::STATUS_APPROVED]);

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/office-registrations/{$reg->id}/reject", ['review_notes' => 'x'])
            ->assertStatus(422);
    }

    // ── helpers ──────────────────────────────────────────────────

    /** @param array<string,mixed> $overrides */
    private function createPending(array $overrides = []): OfficeRegistrationRequest
    {
        return OfficeRegistrationRequest::create(array_merge([
            'reference_number'   => OfficeRegistrationRequest::generateReference(),
            'office_name_ar'     => 'مكتب اختبار',
            'office_name_en'     => 'Test Office ' . uniqid(),
            'office_license_no'  => 'LIC-' . uniqid(),
            'office_city'        => 'عمان',
            'office_address_ar'  => 'ش. الاختبار',
            'office_phone'       => '+962-79-0000000',
            'office_email'       => 'office-' . uniqid() . '@example.com',
            'engineers'          => [[
                'name_ar'=>'م. أحمد', 'membership_number'=>'M1', 'specialization'=>'architectural',
            ]],
            'status'             => OfficeRegistrationRequest::STATUS_PENDING,
        ], $overrides));
    }
}
