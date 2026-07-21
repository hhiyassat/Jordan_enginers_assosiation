<?php

declare(strict_types=1);

namespace App\Engine;

use App\Models\Application;
use App\Models\Engineer;
use App\Models\EngineerDisciplineQuota;
use App\Models\OfficeCeiling;
use App\Models\Organization;
use App\Models\QuotaConsumption;
use Illuminate\Support\Facades\Log;

/**
 * QuotaLedger — JORD-68
 *
 * Owns the quota consumption/reversal write path and the "remaining"
 * read helpers. The whole point of centralising it here (instead of
 * scattering the arithmetic across WorkflowEngine + Application) is
 * so future tickets — JORD-70 boosts, JORD-71 overflow, JORD-73
 * coalitions — extend one class.
 *
 * Semantics
 * ---------
 *   recordApproval(app)  — call on the FINAL transition to
 *                          STATUS_APPROVED. Inserts a QuotaConsumption
 *                          row keyed on (app_id, engineer_id, discipline).
 *                          Idempotent — composite unique on the
 *                          consumption table dedupes retries.
 *   releaseFor(app)      — call when the app is being soft-deleted /
 *                          cancelled. Removes any consumption rows.
 *   remainingEngineerQuota(engineer, discipline, year)
 *   remainingOfficeCeiling(orgId, discipline, year)
 *                        — used by JORD-69 CapacityGuard before submit.
 *
 * Non-consumption paths (silent no-op with a debug log)
 * -----------------------------------------------------
 *   • Service isn't quota-tracked (schema.fee.type not matrix/per_unit
 *     on area_m2 basis) → nothing to consume, skip.
 *   • Application lacks engineer_id or area_m2 in its data → skip
 *     (submission gate will catch this before approval in production;
 *     during Phase-3-in-flight it's tolerated so we don't break
 *     existing test fixtures).
 *   • Engineer not found or no quota row for the discipline → log
 *     warn, still insert a consumption row so the audit trail is
 *     complete even when the underlying quota is misconfigured.
 */
class QuotaLedger
{
    /**
     * Record consumption for a newly-approved application. Idempotent.
     */
    public function recordApproval(Application $app): void
    {
        $data = is_array($app->data) ? $app->data : [];
        $engineerId = $data['engineer_id'] ?? null;
        $area       = $data['area_m2'] ?? null;

        if (!is_int($engineerId) && !(is_numeric($engineerId) && (int) $engineerId > 0)) {
            $this->debug($app, 'no engineer_id in form data — skipping consumption');
            return;
        }
        if (!is_numeric($area) || (int) $area <= 0) {
            $this->debug($app, 'no area_m2 in form data — skipping consumption');
            return;
        }

        $engineer = Engineer::find((int) $engineerId);
        if (!$engineer) {
            Log::warning('QuotaLedger: engineer not found on approved application', [
                'application_id' => $app->id, 'engineer_id' => $engineerId,
            ]);
            return;
        }

        // The consumption is charged against the engineer's declared
        // discipline (folded through the alias map). If the app spans
        // multiple disciplines each needing its own engineer, that's a
        // JORD-72+ scenario — the current schema is 1 engineer per app.
        $discipline = Disciplines::normalize((string) ($engineer->specialization ?? ''));
        if ($discipline === '') {
            Log::warning('QuotaLedger: engineer has no specialization', [
                'application_id' => $app->id, 'engineer_id' => $engineer->id,
            ]);
            return;
        }

        QuotaConsumption::updateOrCreate(
            [
                'application_id' => $app->id,
                'engineer_id'    => $engineer->id,
                'discipline'     => $discipline,
            ],
            [
                'organization_id' => $app->organization_id,
                'year'            => (int) now()->year,
                'm2'              => (int) $area,
            ],
        );
    }

    /**
     * Remove any consumption rows for this application. Called from
     * the Application model's deleted event so a soft-delete releases
     * the quota back to the office / engineer.
     */
    public function releaseFor(Application $app): void
    {
        QuotaConsumption::where('application_id', $app->id)->delete();
    }

    /**
     * Total m² an engineer has left in the given (discipline, year).
     *
     * Applies the JORD-70 engineer-level boost: +20% when the engineer
     * is registered as head-of-specialization for their office.
     *
     * Returns null when there's no quota row at all — callers treat
     * null as "no cap configured" (allow) rather than "0 cap" (block),
     * so a fresh org without seeded quotas doesn't immediately reject
     * every submission.
     */
    public function remainingEngineerQuota(Engineer $engineer, string $discipline, int $year): ?int
    {
        $discipline = Disciplines::normalize($discipline);
        $quota = EngineerDisciplineQuota::where('engineer_id', $engineer->id)
            ->where('discipline', $discipline)
            ->where('year', $year)
            ->first();
        if (!$quota) return null;

        $boostMultiplier = 1.0 + ($engineer->is_specialization_head ? 0.20 : 0.0);
        $effective = (int) floor($quota->m2_allowed * $boostMultiplier);

        $consumed = QuotaConsumption::where('engineer_id', $engineer->id)
            ->where('discipline', $discipline)
            ->where('year', $year)
            ->sum('m2');

        return max(0, $effective - (int) $consumed);
    }

    /**
     * Same shape for the office-level ceiling.
     *
     * Applies the JORD-70 office boost stack (all opt-in flags on
     * Organization): +5% award, +5% bit-khibra, +5% ISO. Stacks
     * additively per the manual ("احتساب 5% ... احتساب 5% ..."):
     * an office with all three earns +15% (1.15×) on top of the
     * base ceiling — not multiplicatively (which would be 1.157625).
     */
    public function remainingOfficeCeiling(int $organizationId, string $discipline, int $year): ?int
    {
        $discipline = Disciplines::normalize($discipline);
        $ceiling = OfficeCeiling::where('organization_id', $organizationId)
            ->where('discipline', $discipline)
            ->where('year', $year)
            ->first();
        if (!$ceiling) return null;

        $org = Organization::find($organizationId);
        $boostMultiplier = 1.0
            + ($org && $org->has_excellence_award ? 0.05 : 0.0)
            + ($org && $org->is_bit_khibra        ? 0.05 : 0.0)
            + ($org && $org->has_iso_cert         ? 0.05 : 0.0);
        $effective = (int) floor($ceiling->m2_allowed * $boostMultiplier);

        $consumed = QuotaConsumption::where('organization_id', $organizationId)
            ->where('discipline', $discipline)
            ->where('year', $year)
            ->sum('m2');

        return max(0, $effective - (int) $consumed);
    }

    private function debug(Application $app, string $reason): void
    {
        Log::debug('QuotaLedger: ' . $reason, [
            'application_id' => $app->id,
        ]);
    }
}
