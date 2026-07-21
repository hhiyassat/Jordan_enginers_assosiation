<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\RecurringObligation;
use App\Models\User;
use App\Services\RecurringDuesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * JORD-79: recurring obligations (F-04 registration + F-05 annual
 * dues + 15/30% late surcharge per JEA manual pp.96-97).
 *
 * Pins:
 *   • RATES table matches the manual.
 *   • ensureRegistrationFee creates one F-04 row, idempotent.
 *   • openAnnualDuesFor creates one F-05 per active applicant,
 *     idempotent, and skips inactive users.
 *   • Late surcharge: 0% on/before due, 15% Mar-Jun, 30% Jul onward.
 *   • Cross-org obligations 404 on pay endpoint.
 *   • Paid obligation cannot be paid twice.
 */
class RecurringDuesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $office;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->admin = User::create([
            'organization_id' => $this->org->id, 'name' => 'admin', 'email' => 'admin@t.esp',
            'password' => 'x', 'role' => 'admin', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->office = User::create([
            'organization_id' => $this->org->id, 'name' => 'Office A', 'email' => 'a@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(),
            'office_classification' => 'engineering',
        ]);
    }

    public function test_rates_table_matches_manual(): void
    {
        // Pin the manual's numbers so a future edit that changes the
        // consultant fee to 500 (typo? migration?) fails here.
        $this->assertSame(60,   RecurringDuesService::RATES['individual_engineer']['registration']);
        $this->assertSame(30,   RecurringDuesService::RATES['individual_engineer']['annual_dues']);
        $this->assertSame(80,   RecurringDuesService::RATES['engineering']['registration']);
        $this->assertSame(60,   RecurringDuesService::RATES['engineering']['annual_dues']);
        $this->assertSame(100,  RecurringDuesService::RATES['consultant']['registration']);
        $this->assertSame(80,   RecurringDuesService::RATES['consultant']['annual_dues']);
        $this->assertSame(3500, RecurringDuesService::RATES['foreign']['registration']);
        $this->assertSame(2000, RecurringDuesService::RATES['foreign']['annual_dues']);
    }

    public function test_ensure_registration_fee_creates_one_row_per_office(): void
    {
        $ob = app(RecurringDuesService::class)->ensureRegistrationFee($this->office);
        $this->assertNotNull($ob);
        $this->assertSame(RecurringObligation::KIND_REGISTRATION, $ob->kind);
        $this->assertSame(80.00, (float) $ob->amount_jod, 'engineering tier registration = 80 JOD');
        $this->assertSame($this->office->id, $ob->office_user_id);
    }

    public function test_ensure_registration_fee_is_idempotent(): void
    {
        $svc = app(RecurringDuesService::class);
        $a = $svc->ensureRegistrationFee($this->office);
        $b = $svc->ensureRegistrationFee($this->office);
        $this->assertSame($a->id, $b->id, 'Second call returns the same row');
        $this->assertSame(1, RecurringObligation::count());
    }

    public function test_open_annual_dues_creates_one_per_active_applicant(): void
    {
        // Add a second active office + one inactive one; only actives get billed.
        $active2 = User::create([
            'organization_id' => $this->org->id, 'name' => 'B', 'email' => 'b@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(), 'office_classification' => 'consultant',
        ]);
        $inactive = User::create([
            'organization_id' => $this->org->id, 'name' => 'C', 'email' => 'c@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => false,
            'password_changed_at' => now(), 'office_classification' => 'individual_engineer',
        ]);

        $count = app(RecurringDuesService::class)->openAnnualDuesFor(2026);
        $this->assertSame(2, $count, 'Only the two active applicants get dues rows');

        $officeDues = RecurringObligation::where('office_user_id', $this->office->id)
            ->where('kind', RecurringObligation::KIND_ANNUAL_DUES)->first();
        $this->assertSame(60.00, (float) $officeDues->amount_jod, 'engineering tier annual = 60');

        $active2Dues = RecurringObligation::where('office_user_id', $active2->id)
            ->where('kind', RecurringObligation::KIND_ANNUAL_DUES)->first();
        $this->assertSame(80.00, (float) $active2Dues->amount_jod, 'consultant tier annual = 80');

        $this->assertSame(0,
            RecurringObligation::where('office_user_id', $inactive->id)->count(),
            'Inactive user must not be billed');
    }

    public function test_open_annual_dues_is_idempotent_across_reruns(): void
    {
        $svc = app(RecurringDuesService::class);
        $first  = $svc->openAnnualDuesFor(2026);
        $second = $svc->openAnnualDuesFor(2026);
        $this->assertSame(1, $first);
        $this->assertSame(0, $second, 'Re-running same year creates no new rows');
        $this->assertSame(1, RecurringObligation::count());
    }

    public function test_late_surcharge_before_due_date_is_zero(): void
    {
        $svc = app(RecurringDuesService::class);
        $due = Carbon::create(2026, 2, 28, 23, 59);
        // Paid on Feb 15 → within grace, no surcharge.
        $this->assertSame(0.0, $svc->computeLateSurcharge(100, $due, Carbon::create(2026, 2, 15)));
        // Paid EXACTLY on due_date → still no surcharge.
        $this->assertSame(0.0, $svc->computeLateSurcharge(100, $due, $due->copy()));
    }

    public function test_late_surcharge_march_through_june_is_15_percent(): void
    {
        $svc = app(RecurringDuesService::class);
        $due = Carbon::create(2026, 2, 28, 23, 59);
        // Paid on Mar 1 → +15%. On Jun 30 → still +15%.
        $this->assertSame(15.00, $svc->computeLateSurcharge(100, $due, Carbon::create(2026, 3, 1)));
        $this->assertSame(15.00, $svc->computeLateSurcharge(100, $due, Carbon::create(2026, 6, 30)));
    }

    public function test_late_surcharge_after_june_is_30_percent(): void
    {
        $svc = app(RecurringDuesService::class);
        $due = Carbon::create(2026, 2, 28, 23, 59);
        // Paid Jul 1 → +30%. Paid a year later → still +30%.
        $this->assertSame(30.00, $svc->computeLateSurcharge(100, $due, Carbon::create(2026, 7, 1)));
        $this->assertSame(30.00, $svc->computeLateSurcharge(100, $due, Carbon::create(2027, 3, 15)));
    }

    public function test_mark_paid_persists_surcharge_and_total(): void
    {
        $ob = app(RecurringDuesService::class)->openAnnualDuesFor(2026);
        $obligation = RecurringObligation::first();
        // Pay in April → 15% surcharge on 60 = 9 → total 69.
        $result = app(RecurringDuesService::class)->markPaid(
            $obligation, 'BANK-REF-123',
            Carbon::create(2026, 4, 15),
        );
        $this->assertSame(9.00, (float) $result->late_surcharge_jod);
        $this->assertSame(69.00, (float) $result->total_paid_jod);
        $this->assertSame('BANK-REF-123', $result->payment_reference);
        $this->assertNotNull($result->paid_at);
    }

    public function test_admin_pay_endpoint_rejects_cross_org_lookup(): void
    {
        // Create a second org + obligation under it. Our admin's PATCH
        // must NOT be able to mark it paid.
        $otherOrg = Organization::create([
            'name_ar' => 'x', 'name_en' => 'x', 'slug' => 'x', 'is_active' => true,
        ]);
        $otherOffice = User::create([
            'organization_id' => $otherOrg->id, 'name' => 'x', 'email' => 'x@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true,
            'password_changed_at' => now(), 'office_classification' => 'individual_engineer',
        ]);
        $foreignOb = app(RecurringDuesService::class)->ensureRegistrationFee($otherOffice);

        Sanctum::actingAs($this->admin);
        $res = $this->postJson("/api/v1/admin/dues/{$foreignOb->id}/pay", [
            'payment_reference' => 'HACK',
        ]);
        $res->assertNotFound();
        $this->assertNull($foreignOb->fresh()->paid_at);
    }

    public function test_admin_pay_endpoint_refuses_double_payment(): void
    {
        $ob = app(RecurringDuesService::class)->ensureRegistrationFee($this->office);
        app(RecurringDuesService::class)->markPaid($ob, 'FIRST');

        Sanctum::actingAs($this->admin);
        $res = $this->postJson("/api/v1/admin/dues/{$ob->id}/pay", [
            'payment_reference' => 'SECOND',
        ]);
        $res->assertStatus(422);
        $res->assertJsonPath('message', 'هذه الرسوم مدفوعة سابقاً — لا يمكن الدفع مرتين.');
    }

    public function test_admin_index_endpoint_returns_all_obligations_for_the_office(): void
    {
        $svc = app(RecurringDuesService::class);
        $svc->ensureRegistrationFee($this->office);
        $svc->openAnnualDuesFor(2026);

        Sanctum::actingAs($this->admin);
        $res = $this->getJson("/api/v1/admin/offices/{$this->office->id}/dues");
        $res->assertOk();
        $this->assertCount(2, $res->json('obligations'));
        $this->assertSame('engineering', $res->json('office.office_classification'));
        // Rate table returned so the frontend can render "your tier
        // pays X" without a second call.
        $this->assertArrayHasKey('engineering', $res->json('rate_table'));
    }

    public function test_console_command_dues_open_annual_creates_rows(): void
    {
        // End-to-end: `php artisan dues:open-annual --year=2027` opens
        // dues via the RecurringDuesService.
        $this->artisan('dues:open-annual', ['--year' => 2027])
            ->assertSuccessful();
        $this->assertSame(1,
            RecurringObligation::where('period_year', 2027)
                ->where('kind', 'annual_dues')->count());
    }
}
