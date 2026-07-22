<?php

namespace Tests\Feature;

use Modules\JeaProjects\Engine\Disciplines;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ApplicationReview;
use App\Models\Notification;
use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * JORD-80: retention reminder cron. Pins:
 *   • Fires at 30 / 7 / 1 day thresholds — nothing outside those.
 *   • Dedupes per (app_id, kind, threshold) so daily re-runs during
 *     the 30-day window don't spam the applicant.
 *   • Skips applications without an expiry (non-drawings / not
 *     approved / no supervision doc).
 *   • --dry-run emits nothing.
 *   • Both output_validity AND supervision reminders fire when
 *     both dates fall in the same window.
 */
class RemindExpiriesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $applicant;
    private User $reviewer;
    private ServiceDefinition $drawing;
    private ServiceDefinition $cert;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->applicant = User::create([
            'organization_id' => $this->org->id, 'name' => 'A', 'email' => 'a@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $this->reviewer = User::create([
            'organization_id' => $this->org->id, 'name' => 'r', 'email' => 'r@t.esp',
            'password' => 'x', 'role' => 'staff', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        // DRW-P-* shape: parent_code JEA-PROJ + supervision_services_agreement
        // doc + certificate.validity_months=60. That's what triggers
        // both output_validity_expiry AND supervision_expiry.
        $this->drawing = ServiceDefinition::create([
            'organization_id' => $this->org->id,
            'code' => 'DRW-P-TEST', 'parent_code' => 'JEA-PROJ',
            'name_ar' => 'd', 'name_en' => 'd', 'currency' => 'JOD',
            'schema' => [
                'fields' => [],
                'documents' => [['id' => 'supervision_services_agreement', 'label_ar' => 'اتفاقية']],
                'workflow' => ['stages' => [['id' => 'r', 'label_ar' => 'r', 'role' => 'staff', 'sla_hours' => 24]]],
                'certificate' => ['validity_months' => 60, 'title_ar' => 'x', 'title_en' => 'x', 'fields_on_cert' => []],
            ],
            'status' => 'active',
        ]);
        // CERT-* shape: no supervision, no drawing validity.
        $this->cert = ServiceDefinition::create([
            'organization_id' => $this->org->id, 'code' => 'CERT-TEST',
            'name_ar' => 'c', 'name_en' => 'c', 'currency' => 'JOD',
            'schema' => ['fields' => [], 'workflow' => ['stages' => [['id' => 'r', 'label_ar' => 'r', 'role' => 'staff', 'sla_hours' => 24]]]],
            'status' => 'active',
        ]);
    }

    public function test_fires_supervision_reminder_at_7_days_out(): void
    {
        // Set approval date so supervision_expiry = today + 5 days
        // (falls into the 7-day threshold, not 30).
        $approvalDate = Carbon::now()->subMonths(6)->addDays(5);
        $this->approvedApp($this->drawing, $approvalDate);

        $this->artisan('retention:remind')->assertSuccessful();

        $notice = Notification::where('type', 'reminder.supervision_expiry')->first();
        $this->assertNotNull($notice);
        $this->assertSame(7, (int) data_get($notice->payload, 'threshold_days'));
        $this->assertStringContainsString('صلاحية عقد الإشراف', $notice->title);
    }

    public function test_fires_output_validity_reminder_at_30_days_out(): void
    {
        // Set approval date so output_validity_expiry = today + 20 days
        // (falls into 30 threshold, since 20 <= 30 and !<=7).
        $approvalDate = Carbon::now()->subMonths(60)->addDays(20);
        $this->approvedApp($this->drawing, $approvalDate);

        $this->artisan('retention:remind')->assertSuccessful();

        $notice = Notification::where('type', 'reminder.output_validity_expiry')->first();
        $this->assertNotNull($notice);
        $this->assertSame(30, (int) data_get($notice->payload, 'threshold_days'));
    }

    public function test_does_not_fire_outside_the_30_day_window(): void
    {
        // supervision_expiry = 100 days from now — no threshold matches.
        $approvalDate = Carbon::now()->subMonths(6)->addDays(100);
        $this->approvedApp($this->drawing, $approvalDate);
        $this->artisan('retention:remind')->assertSuccessful();
        $this->assertSame(0, Notification::count());
    }

    public function test_does_not_fire_for_already_expired_dates(): void
    {
        // supervision_expiry = 10 days AGO (negative daysRemaining).
        // The threshold check requires daysRemaining >= 0, so this
        // must NOT emit — a re-approval workflow handles the expired
        // case, not a reminder.
        $approvalDate = Carbon::now()->subMonths(6)->subDays(10);
        $this->approvedApp($this->drawing, $approvalDate);
        $this->artisan('retention:remind')->assertSuccessful();
        $this->assertSame(0, Notification::count());
    }

    public function test_dedupes_on_second_run_within_the_same_window(): void
    {
        $approvalDate = Carbon::now()->subMonths(6)->addDays(5); // supervision 5d away
        $this->approvedApp($this->drawing, $approvalDate);

        $this->artisan('retention:remind')->assertSuccessful();
        $this->artisan('retention:remind')->assertSuccessful();
        $this->artisan('retention:remind')->assertSuccessful();

        // 3 cron runs, still only 1 supervision reminder (dedupe on
        // app+kind+threshold). Plus 1 output_validity at 30d threshold
        // if the drawing approval was recent — approval was 6 months
        // ago so output_validity is ~54 months away, no match.
        $this->assertSame(1, Notification::where('type', 'reminder.supervision_expiry')->count());
    }

    public function test_dry_run_emits_nothing(): void
    {
        $approvalDate = Carbon::now()->subMonths(6)->addDays(5);
        $this->approvedApp($this->drawing, $approvalDate);

        $this->artisan('retention:remind', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Notification::count());
    }

    public function test_skips_non_drawings_applications(): void
    {
        // CERT-TEST approved recently — has no supervision_expiry
        // (not a JEA-PROJ) and no output_validity_expiry (no
        // certificate.validity_months). Guard must skip cleanly.
        $app = Application::create([
            'reference_number' => strtoupper(bin2hex(random_bytes(4))),
            'organization_id' => $this->org->id,
            'service_definition_id' => $this->cert->id,
            'applicant_id' => $this->applicant->id,
            'status' => Application::STATUS_APPROVED,
            'data' => [], 'fee_amount' => 0,
        ]);
        ApplicationReview::create([
            'application_id' => $app->id, 'reviewer_id' => $this->reviewer->id,
            'stage_id' => 'r', 'decision' => Application::STATUS_APPROVED,
            'notes' => 'ok', 'review_round' => 1,
        ]);

        $this->artisan('retention:remind')->assertSuccessful();
        $this->assertSame(0, Notification::count());
    }

    public function test_1_day_threshold_wins_over_7_when_both_would_match(): void
    {
        // Supervision expires in 1 day: THRESHOLDS iterated closest-first
        // (1, 7, 30) so 1 wins. Prevents the applicant getting a stale
        // "7 days left" reminder when it's actually about to lapse today.
        $approvalDate = Carbon::now()->subMonths(6)->addDay();
        $this->approvedApp($this->drawing, $approvalDate);

        $this->artisan('retention:remind')->assertSuccessful();

        $notice = Notification::where('type', 'reminder.supervision_expiry')->first();
        $this->assertSame(1, (int) data_get($notice->payload, 'threshold_days'));
    }

    private function approvedApp(ServiceDefinition $svc, Carbon $approvedAt): Application
    {
        $app = Application::create([
            'reference_number' => strtoupper(bin2hex(random_bytes(4))),
            'organization_id' => $this->org->id,
            'service_definition_id' => $svc->id,
            'applicant_id' => $this->applicant->id,
            'status' => Application::STATUS_APPROVED,
            'data' => [], 'fee_amount' => 0,
        ]);
        $review = ApplicationReview::create([
            'application_id' => $app->id, 'reviewer_id' => $this->reviewer->id,
            'stage_id' => 'r', 'decision' => Application::STATUS_APPROVED,
            'notes' => 'ok', 'review_round' => 1,
        ]);
        // Backdate created_at so the accessors compute the correct
        // supervision_expiry + output_validity_expiry.
        ApplicationReview::where('id', $review->id)->update(['created_at' => $approvedAt]);
        return $app->fresh();
    }
}
