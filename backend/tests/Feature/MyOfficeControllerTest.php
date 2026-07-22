<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Organization;
use Modules\JeaDues\Models\RecurringObligation;
use App\Models\Sanction;
use App\Models\User;
use Modules\JeaDues\Services\RecurringDuesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-84: applicant self-service — office user sees their OWN
 * dues + complaints filed against them + sanctions. Read-only.
 *
 * Pins the scoping: an office MUST only see their own data —
 * another office's obligations/complaints/sanctions never leak
 * even inside the same org.
 */
class MyOfficeControllerTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $me;
    private User $otherOffice;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->me = User::create([
            'organization_id' => $this->org->id, 'name' => 'Office Me', 'email' => 'me@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(), 'office_classification' => 'engineering',
        ]);
        $this->otherOffice = User::create([
            'organization_id' => $this->org->id, 'name' => 'Other Office', 'email' => 'other@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(), 'office_classification' => 'consultant',
        ]);
        $this->admin = User::create([
            'organization_id' => $this->org->id, 'name' => 'admin', 'email' => 'admin@t.esp',
            'password' => 'x', 'role' => 'admin', 'is_active' => true, 'password_changed_at' => now(),
        ]);
    }

    public function test_my_dues_returns_only_my_obligations(): void
    {
        // Two obligations for me, one for the other office.
        app(RecurringDuesService::class)->ensureRegistrationFee($this->me);
        app(RecurringDuesService::class)->openAnnualDuesFor(2026);

        Sanctum::actingAs($this->me);
        $res = $this->getJson('/api/v1/my/dues');
        $res->assertOk();
        // Registration + annual = 2 rows for me.
        $this->assertCount(2, $res->json('obligations'));
        // Every row must belong to me.
        foreach ($res->json('obligations') as $o) {
            $this->assertSame($this->me->id, $o['office_user_id']);
        }
        // me.name + tier surfaced so the frontend can render context.
        $this->assertSame('engineering', $res->json('me.office_classification'));
        // Rate table included.
        $this->assertArrayHasKey('engineering', $res->json('rate_table'));
    }

    public function test_my_dues_does_not_leak_other_office_obligations(): void
    {
        app(RecurringDuesService::class)->ensureRegistrationFee($this->otherOffice);
        Sanctum::actingAs($this->me);
        $res = $this->getJson('/api/v1/my/dues');
        $this->assertCount(0, $res->json('obligations'));
    }

    public function test_my_complaints_returns_complaints_against_me(): void
    {
        // One complaint against me, one against otherOffice.
        Complaint::create([
            'organization_id' => $this->org->id,
            'target_office_user_id' => $this->me->id,
            'reporter_user_id' => $this->admin->id,
            'kind' => 'safety_violation', 'description' => 'مخالفة سلامة موثقة.',
            'status' => Complaint::STATUS_OPEN,
            'investigation_deadline' => now()->addDays(30)->toDateString(),
        ]);
        Complaint::create([
            'organization_id' => $this->org->id,
            'target_office_user_id' => $this->otherOffice->id,
            'reporter_user_id' => $this->admin->id,
            'kind' => 'fee_undercutting', 'description' => 'مخالفة تسعير.',
            'status' => Complaint::STATUS_OPEN,
            'investigation_deadline' => now()->addDays(30)->toDateString(),
        ]);

        Sanctum::actingAs($this->me);
        $res = $this->getJson('/api/v1/my/complaints');
        $res->assertOk();
        $this->assertCount(1, $res->json('complaints'));
        $this->assertSame($this->me->id, $res->json('complaints.0.target_office_user_id'));
    }

    public function test_my_complaints_strips_reporter_display(): void
    {
        // External reporter (municipal officer, no login). Their display
        // name should NOT reach the target office — manual p.278
        // confidentiality until decision.
        Complaint::create([
            'organization_id' => $this->org->id,
            'target_office_user_id' => $this->me->id,
            'reporter_user_id' => null,
            'reporter_display' => 'مفتش بلدية عمان',
            'kind' => 'other', 'description' => 'x'.str_repeat('a', 20),
            'status' => Complaint::STATUS_OPEN,
            'investigation_deadline' => now()->addDays(30)->toDateString(),
        ]);
        Sanctum::actingAs($this->me);
        $res = $this->getJson('/api/v1/my/complaints');
        $row = $res->json('complaints.0');
        $this->assertArrayNotHasKey('reporter_display', $row);
    }

    public function test_my_sanctions_returns_my_active_and_historical_sanctions(): void
    {
        // Active + expired sanction for me; one for otherOffice.
        Sanction::create([
            'organization_id' => $this->org->id, 'office_user_id' => $this->me->id,
            'kind' => Sanction::KIND_WARNING, 'effective_from' => now()->subMonth()->toDateString(),
            'effective_until' => now()->subMonth()->toDateString(),
            'reason' => 'warned', 'issued_by_user_id' => $this->admin->id,
        ]);
        Sanction::create([
            'organization_id' => $this->org->id, 'office_user_id' => $this->me->id,
            'kind' => Sanction::KIND_SUSPENSION_1YR,
            'effective_from' => now()->subMonth()->toDateString(),
            'effective_until' => now()->addMonths(11)->toDateString(),
            'reason' => 'suspended', 'issued_by_user_id' => $this->admin->id,
        ]);
        Sanction::create([
            'organization_id' => $this->org->id, 'office_user_id' => $this->otherOffice->id,
            'kind' => Sanction::KIND_WARNING, 'effective_from' => now()->toDateString(),
            'effective_until' => now()->toDateString(),
            'reason' => 'other', 'issued_by_user_id' => $this->admin->id,
        ]);

        Sanctum::actingAs($this->me);
        $res = $this->getJson('/api/v1/my/sanctions');
        $this->assertCount(2, $res->json('sanctions'));
        foreach ($res->json('sanctions') as $s) {
            $this->assertSame($this->me->id, $s['office_user_id']);
        }
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/my/dues')->assertUnauthorized();
        $this->getJson('/api/v1/my/complaints')->assertUnauthorized();
        $this->getJson('/api/v1/my/sanctions')->assertUnauthorized();
    }
}
