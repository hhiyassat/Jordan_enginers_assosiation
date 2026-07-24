<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\JeaServices\Database\Seeders\ManualReferencesSeeder;
use Modules\JeaServices\Models\ManualReference;
use Tests\TestCase;

/**
 * ManualReferenceTest — الأساس النظامي من كتاب التعليمات الفنية.
 *
 * يغطّي:
 *   • قراءة أي مستخدم مسجَّل
 *   • تعديل من admin يرفع pending flag
 *   • acknowledge من admin يمسح flag ويحدِّث original baseline
 *   • idempotency للسيدر — إعادة التشغيل لا تمحو تعديل معلَّق
 */
class ManualReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function seedRefs(): void
    {
        $this->seed(ManualReferencesSeeder::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Bypass session/inactivity middleware for these tests — we
        // just care about the auth + role guards, not token freshness.
        $this->withoutMiddleware([
            \App\Http\Middleware\TokenInactivityCheck::class,
            \App\Http\Middleware\EnforcePasswordPolicy::class,
            \App\Http\Middleware\TrackUserActivity::class,
        ]);
    }

    private function makeUser(string $role): User
    {
        $org = Organization::create([
            'name_ar' => 'اختبار', 'name_en' => 'Test',
            'slug' => 'test-mr-' . uniqid(), 'is_active' => true,
        ]);
        return User::create([
            'organization_id' => $org->id,
            'name'  => $role,
            'email' => "{$role}-" . uniqid() . '@t.co',
            'password' => Hash::make('Secret123!'),
            'role'  => $role,
            'is_active' => true,
            'password_changed_at' => now(),
        ]);
    }

    public function test_seeder_populates_references_from_manual_summary(): void
    {
        $this->seedRefs();

        $this->assertGreaterThan(15, ManualReference::count());
        $this->assertTrue(ManualReference::where('jord_ticket', 'JORD-59')->exists());
        $this->assertTrue(ManualReference::where('jord_ticket', 'JORD-63')->exists());
    }

    public function test_any_authenticated_user_can_read_a_reference(): void
    {
        $this->seedRefs();
        $applicant = $this->makeUser("applicant");
        $ref = ManualReference::first();

        $this->actingAs($applicant)
            ->getJson("/api/v1/manual-references/{$ref->id}")
            ->assertStatus(200)
            ->assertJsonPath('reference.jord_ticket', $ref->jord_ticket);
    }

    public function test_admin_edit_raises_needs_reimplementation_flag(): void
    {
        $this->seedRefs();
        $admin = $this->makeUser("admin");
        $ref = ManualReference::first();

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/manual-references/{$ref->id}", [
                'text_ar'     => 'نصٌّ معدَّل بعد المراجعة مع النقابة.',
                'edit_reason' => 'توضيح لغوي بناءً على مراجعة اللجنة',
            ])
            ->assertStatus(200);

        $ref->refresh();
        $this->assertTrue($ref->needs_reimplementation);
        $this->assertSame($admin->id, $ref->edited_by);
        $this->assertNotNull($ref->edited_at);
        $this->assertSame('نصٌّ معدَّل بعد المراجعة مع النقابة.', $ref->text_ar);
        // Original text should NOT change on edit — only on acknowledge.
        $this->assertNotSame($ref->text_ar, $ref->text_ar_original);
    }

    public function test_applicant_cannot_edit_reference(): void
    {
        $this->seedRefs();
        $applicant = $this->makeUser("applicant");
        $ref = ManualReference::first();

        $this->actingAs($applicant)
            ->patchJson("/api/v1/admin/manual-references/{$ref->id}", [
                'text_ar' => 'محاولة تعديل من غير admin',
            ])
            ->assertStatus(403);
    }

    public function test_acknowledge_clears_flag_and_updates_original(): void
    {
        $this->seedRefs();
        $admin = $this->makeUser("admin");
        $ref = ManualReference::first();

        // Edit first
        $this->actingAs($admin)->patchJson("/api/v1/admin/manual-references/{$ref->id}", [
            'text_ar' => 'التعديل الجديد.',
        ])->assertStatus(200);

        // Then ack
        $this->actingAs($admin)
            ->postJson("/api/v1/admin/manual-references/{$ref->id}/ack")
            ->assertStatus(200);

        $ref->refresh();
        $this->assertFalse($ref->needs_reimplementation);
        $this->assertSame('التعديل الجديد.', $ref->text_ar_original);
    }

    public function test_pending_index_returns_only_flagged_refs(): void
    {
        $this->seedRefs();
        $admin = $this->makeUser("admin");
        $ref = ManualReference::first();

        // No pending yet
        $this->actingAs($admin)
            ->getJson('/api/v1/manual-references?pending=1')
            ->assertStatus(200)
            ->assertJsonCount(0, 'references');

        // Edit one → should now appear pending
        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/manual-references/{$ref->id}", [
                'text_ar' => 'مُعدَّل من الاختبار.',
            ])
            ->assertStatus(200);

        $ref->refresh();
        $this->assertTrue($ref->needs_reimplementation, 'PATCH must set needs_reimplementation');

        $this->actingAs($admin)
            ->getJson('/api/v1/manual-references?pending=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'references')
            ->assertJsonPath('references.0.id', $ref->id);
    }

    public function test_reseed_preserves_pending_edits(): void
    {
        $this->seedRefs();
        $admin = $this->makeUser("admin");
        $ref = ManualReference::first();

        $this->actingAs($admin)->patchJson("/api/v1/admin/manual-references/{$ref->id}", [
            'text_ar' => 'نصٌّ من المراجعة.',
        ]);

        // Re-run seeder — should NOT overwrite the pending edit
        $this->seed(ManualReferencesSeeder::class);

        $ref->refresh();
        $this->assertSame('نصٌّ من المراجعة.', $ref->text_ar, 'reseed must preserve admin edits');
        $this->assertTrue($ref->needs_reimplementation);
    }

    public function test_unchanged_edit_does_not_raise_flag(): void
    {
        $this->seedRefs();
        $admin = $this->makeUser("admin");
        $ref = ManualReference::first();
        $original = $ref->text_ar;

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/manual-references/{$ref->id}", ['text_ar' => $original])
            ->assertStatus(200)
            ->assertJsonPath('unchanged', true);

        $ref->refresh();
        $this->assertFalse($ref->needs_reimplementation);
    }

    public function test_list_by_service_returns_only_that_services_refs(): void
    {
        $this->seedRefs();
        $applicant = $this->makeUser("applicant");

        $this->actingAs($applicant)
            ->getJson('/api/v1/manual-references?service=DRW-P-006')
            ->assertStatus(200)
            ->assertJsonFragment(['jord_ticket' => 'JORD-64-solar']);
    }
}
