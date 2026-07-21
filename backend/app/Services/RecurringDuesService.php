<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RecurringObligation;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * RecurringDuesService — JORD-79
 *
 * Owns the JEA manual pp.96-97 recurring obligations:
 *   • F-04 registration (one-time per office)
 *   • F-05 annual dues + late surcharge (15% end-of-Feb→end-of-June,
 *          30% after end-of-June)
 *
 * Called from three places:
 *   • Console scheduler (`schedule:run`) fires openAnnualDuesFor()
 *     on Feb 1 every year.
 *   • Office-creation flow can call ensureRegistrationFee() to seed
 *     the one-time obligation.
 *   • Admin pay endpoint calls markPaid() which computes the late
 *     surcharge inline based on paid_at vs due_date.
 *
 * Rate table (manual p.96-97, per classification tier):
 *
 *   Classification            Registration    Annual dues
 *   individual_engineer       60 JOD           30 JOD
 *   engineering               80 JOD           60 JOD
 *   consultant                100 JOD          80 JOD
 *   foreign                   3500 JOD         2000 JOD
 *
 * The "+15 JOD per additional specialization" nuance from the
 * manual isn't modelled here — most offices land in the base
 * tier and this keeps the MVP tractable. Extending is one hash-
 * map edit + one call-site tweak away.
 */
class RecurringDuesService
{
    /** @var array<string, array{registration: int, annual_dues: int}> */
    public const RATES = [
        'individual_engineer' => ['registration' => 60,   'annual_dues' => 30],
        'engineering'         => ['registration' => 80,   'annual_dues' => 60],
        'consultant'          => ['registration' => 100,  'annual_dues' => 80],
        'foreign'             => ['registration' => 3500, 'annual_dues' => 2000],
    ];

    /**
     * Create the one-time F-04 registration obligation for a fresh
     * office. Idempotent — the composite unique on
     * (office_user_id, kind, period_year) means a re-call returns
     * the existing row.
     */
    public function ensureRegistrationFee(User $office): RecurringObligation
    {
        $tier = $this->tierFor($office);
        $year = (int) now()->year;

        return RecurringObligation::firstOrCreate(
            [
                'office_user_id' => $office->id,
                'kind'           => RecurringObligation::KIND_REGISTRATION,
                'period_year'    => $year,
            ],
            [
                'organization_id' => $office->organization_id,
                'period_label_ar' => "رسوم تسجيل {$year}",
                'amount_jod'      => self::RATES[$tier]['registration'],
                // Registration is due immediately on office creation —
                // late surcharges don't apply per the manual.
                'due_date'        => now()->toDateString(),
            ],
        );
    }

    /**
     * Open annual dues for a given year across every active applicant
     * user. Fires from the Feb 1 cron. Returns the count opened.
     * Idempotent — composite unique dedupes if the cron runs twice.
     */
    public function openAnnualDuesFor(int $year): int
    {
        $dueDate = Carbon::create($year, 2, 28)->endOfDay()->toDateString();
        $count = 0;

        User::where('role', 'applicant')
            ->where('is_active', true)
            ->chunkById(200, function ($offices) use ($year, $dueDate, &$count) {
                foreach ($offices as $office) {
                    $tier = $this->tierFor($office);
                    $created = RecurringObligation::firstOrCreate(
                        [
                            'office_user_id' => $office->id,
                            'kind'           => RecurringObligation::KIND_ANNUAL_DUES,
                            'period_year'    => $year,
                        ],
                        [
                            'organization_id' => $office->organization_id,
                            'period_label_ar' => "الرسوم السنوية {$year}",
                            'amount_jod'      => self::RATES[$tier]['annual_dues'],
                            'due_date'        => $dueDate,
                        ],
                    );
                    if ($created->wasRecentlyCreated) $count++;
                }
            });

        return $count;
    }

    /**
     * Mark an obligation paid. Computes the late surcharge inline
     * against the passed $paidAt (defaults to now) so a back-dated
     * admin correction produces the same surcharge as the original
     * would have.
     */
    public function markPaid(
        RecurringObligation $obligation,
        string $paymentReference,
        ?Carbon $paidAt = null,
    ): RecurringObligation {
        $paidAt = $paidAt ?? now();
        $surcharge = $this->computeLateSurcharge(
            (float) $obligation->amount_jod,
            $obligation->due_date,
            $paidAt,
        );

        $obligation->update([
            'paid_at'            => $paidAt,
            'payment_reference'  => $paymentReference,
            'late_surcharge_jod' => $surcharge,
            'total_paid_jod'     => round((float) $obligation->amount_jod + $surcharge, 2),
        ]);

        return $obligation->fresh();
    }

    /**
     * Manual quote (p. 97):
     *   "تدفع الرسوم السنوية حتى نهاية شهر شباط ... رسم إضافي (15%)
     *    حتى نهاية شهر حزيران و(30%) بعد ذلك."
     *
     * Translated:
     *   • Paid on/before due_date (typically Feb 28) → 0% surcharge.
     *   • Paid between Mar 1 and Jun 30 of the same year → +15%.
     *   • Paid after Jun 30 → +30%.
     *
     * Anchor is the CALENDAR year of the due date, not paid_at —
     * a payment made in 2027 against a 2026 obligation is treated
     * as "after Jun 30 of 2026" (i.e. +30%), which matches JEA's
     * escalating-with-time policy.
     */
    public function computeLateSurcharge(
        float $amount,
        Carbon $dueDate,
        Carbon $paidAt,
    ): float {
        if ($paidAt->lessThanOrEqualTo($dueDate)) {
            return 0.0;
        }
        $graceEnd = Carbon::create($dueDate->year, 6, 30)->endOfDay();
        $rate = $paidAt->lessThanOrEqualTo($graceEnd) ? 0.15 : 0.30;
        return round($amount * $rate, 2);
    }

    private function tierFor(User $office): string
    {
        $tier = (string) ($office->office_classification ?? 'individual_engineer');
        // Defensive: unknown / stale classification → cheapest tier
        // so a mis-classified office doesn't get billed at consultant
        // rates without a human decision.
        return array_key_exists($tier, self::RATES) ? $tier : 'individual_engineer';
    }
}
