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
        $svc  = $app->serviceDefinition;

        // JORD-75: basis field defaults to 'area_m2'; SRV-006 overrides
        // to 'length_lm'. The `m2` column on quota_consumptions stores
        // whatever unit the schema declared — reads elsewhere never
        // mix units because both consumption + ceiling for the same
        // (org, discipline, year) use the same unit by construction.
        $basisField = $svc ? data_get($svc->schema, 'quota_basis_field', 'area_m2') : 'area_m2';
        if (!is_string($basisField) || $basisField === '') {
            $basisField = 'area_m2';
        }

        $engineerId = $data['engineer_id']  ?? null;
        $quantity   = $data[$basisField]    ?? null;

        if (!is_int($engineerId) && !(is_numeric($engineerId) && (int) $engineerId > 0)) {
            $this->debug($app, 'no engineer_id in form data — skipping consumption');
            return;
        }
        if (!is_numeric($quantity) || (int) $quantity <= 0) {
            $this->debug($app, "no {$basisField} in form data — skipping consumption");
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
        //
        // JORD-74: services like SRV-008/009 (materials testing) still
        // have an engineer picker but are quota'd against a service-
        // wide bucket ('materials_testing'), not the engineer's own
        // discipline. schema.quota_discipline_override lets a service
        // opt into that redirect without duplicating the whole engine.
        $override = $svc ? data_get($svc->schema, 'quota_discipline_override') : null;
        $discipline = is_string($override) && $override !== ''
            ? $override
            : Disciplines::normalize((string) ($engineer->specialization ?? ''));
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
                // The `m2` column stores the quantity in whatever unit
                // the schema.quota_basis_field declared — see the
                // "one unit per (org, discipline, year)" invariant note
                // on remainingOfficeCeiling.
                'm2'              => (int) $quantity,
                // JORD-71: governorate lets the 90%→+10% overflow rule
                // scope by governorate. Nullable when the form doesn't
                // ask (older services or non-drawings) — those rows
                // don't count toward any governorate's 90% trigger,
                // which is the intentionally-conservative default.
                'governorate'     => is_string($data['governorate'] ?? null)
                    ? $data['governorate']
                    : null,
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
     * Applies:
     *   • JORD-70 office boost stack (award / bit-khibra / ISO,
     *     additive +5% each per manual quotes).
     *   • JORD-71 governorate-scoped +10% overflow when the office
     *     has already consumed ≥90% of its ceiling in the passed
     *     governorate. Applied ONLY to the governorate that hit
     *     90% — other governorates keep the base ceiling.
     *
     * When `$governorate` is null the JORD-71 overflow does not fire
     * (whole-org remaining, no per-governorate accounting). Pass
     * governorate from CapacityGuard's data.governorate to activate it.
     */
    public function remainingOfficeCeiling(
        int $organizationId,
        string $discipline,
        int $year,
        ?string $governorate = null,
    ): ?int {
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

        // JORD-71: governorate-scoped 90% → +10% overflow.
        if ($governorate !== null) {
            $governorateConsumed = (int) QuotaConsumption::where('organization_id', $organizationId)
                ->where('discipline', $discipline)
                ->where('year', $year)
                ->where('governorate', $governorate)
                ->sum('m2');
            $baseForGovTrigger = (int) floor($ceiling->m2_allowed * $boostMultiplier);
            if ($baseForGovTrigger > 0 && $governorateConsumed >= (int) ceil($baseForGovTrigger * 0.90)) {
                $boostMultiplier += 0.10;
            }
        }

        $effective = (int) floor($ceiling->m2_allowed * $boostMultiplier);

        $consumed = QuotaConsumption::where('organization_id', $organizationId)
            ->where('discipline', $discipline)
            ->where('year', $year)
            ->sum('m2');

        return max(0, $effective - (int) $consumed);
    }

    /**
     * JORD-72: overflow surcharge when this application's area exceeds
     * the office's per-project cap for the engineer's discipline.
     *
     * The manual (p. 129) allows the excess with a 25% overflow fee
     * on the excess m² × base rate. We compute the amount live from
     * the application's current form data + the office's ceiling
     * row — no persisted state, so the calculation stays in sync when
     * the office's cap or the applicant's area changes.
     *
     * Returns null when the rule doesn't apply:
     *   • The service isn't quota-tracked (no area_m2 field).
     *   • The office has no per_project_cap_m2 configured for the
     *     engineer's discipline (null = pass-through).
     *   • Area is within the cap.
     *   • Missing engineer_id / area_m2 (edge — submit gate catches).
     *
     * Returns a surcharge-shaped array (same shape as
     * schema.fee.surcharges entries) when it does apply. Caller
     * appends to breakdown['surcharges'].
     *
     * @return array<string, mixed>|null
     */
    public function overflowSurchargeFor(Application $app): ?array
    {
        $svc = $app->serviceDefinition;
        if (!$svc) return null;

        $fields = data_get($svc->schema, 'fields', []);
        $hasArea = collect($fields)->contains(fn ($f) => ($f['id'] ?? null) === 'area_m2');
        if (!$hasArea) return null;

        $data = is_array($app->data) ? $app->data : [];
        $engineerId = $data['engineer_id'] ?? null;
        $area       = $data['area_m2']     ?? null;
        if (!is_numeric($engineerId) || !is_numeric($area)) return null;

        $engineer = Engineer::find((int) $engineerId);
        if (!$engineer) return null;

        $discipline = Disciplines::normalize((string) ($engineer->specialization ?? ''));
        if ($discipline === '') return null;

        $year = (int) now()->year;
        $ceiling = OfficeCeiling::where('organization_id', $app->organization_id)
            ->where('discipline', $discipline)
            ->where('year', $year)
            ->first();
        if (!$ceiling || $ceiling->per_project_cap_m2 === null) return null;

        $areaI = (int) $area;
        if ($areaI <= $ceiling->per_project_cap_m2) return null;

        $excess = $areaI - $ceiling->per_project_cap_m2;

        // Base rate lookup — reuse the service's fee block. For matrix
        // fees we need the applicant's governorate + building_class to
        // find their per-m² rate; for per_unit we take the fee.rate.
        // Any other fee type has no reliable "per m² rate" so we
        // conservatively return null (no surcharge — better than an
        // arbitrary guess).
        $fee = data_get($svc->schema, 'fee', []);
        $baseRate = $this->resolveBaseRatePerM2($fee, $data);
        if ($baseRate === null || $baseRate <= 0) return null;

        // 25% × excess × base rate. Option A per the JORD-72 product
        // decision (literal 25%, no discipline weighting). If JEA
        // later publishes discipline weights, extend this line only.
        $amount = round(0.25 * $excess * $baseRate, 2);

        return [
            'code'     => 'per_project_cap_overflow_25pct',
            'kind'     => 'percent_of_excess',
            'label_ar' => sprintf(
                'رسم تجاوز سقف المشروع الواحد (25%% × %d م² زائدة)',
                $excess,
            ),
            'label_en' => sprintf(
                'Per-project cap overflow (25%% × %d m² excess)',
                $excess,
            ),
            'amount'   => (float) $amount,
            'source'   => 'كتاب التعليمات الفنية 2025 ص 129',
        ];
    }

    /**
     * Extract the effective per-m² rate from a service's fee config
     * for the applicant's specific form values. Returns null when the
     * fee shape doesn't have an area-scaled rate we can decompose
     * cleanly (fixed / tiered / formula fees don't cleanly map to
     * "JOD per m²" so we skip rather than approximate).
     */
    private function resolveBaseRatePerM2(array $fee, array $formData): ?float
    {
        $type = $fee['type'] ?? null;

        if ($type === 'per_unit' && ($fee['basis'] ?? null) === 'area_m2' && is_numeric($fee['rate'] ?? null)) {
            return (float) $fee['rate'];
        }

        if ($type === 'matrix' && ($fee['basis'] ?? null) === 'area_m2') {
            // Reproduce matrix lookup: compose bucketized key, look up rate.
            $keys    = is_array($fee['keys'] ?? null)    ? $fee['keys']    : [];
            $rates   = is_array($fee['rates'] ?? null)   ? $fee['rates']   : [];
            $buckets = is_array($fee['buckets'] ?? null) ? $fee['buckets'] : [];
            $parts = [];
            foreach ($keys as $key) {
                if (!is_string($key)) return null;
                $raw = $formData[$key] ?? null;
                if (!is_string($raw) && !is_int($raw)) return null;
                $bucketMap = is_array($buckets[$key] ?? null) ? $buckets[$key] : [];
                $parts[] = (string) ($bucketMap[$raw] ?? $raw);
            }
            $lookup = implode('|', $parts);
            if (isset($rates[$lookup]) && is_numeric($rates[$lookup])) {
                return (float) $rates[$lookup];
            }
        }

        return null;
    }

    private function debug(Application $app, string $reason): void
    {
        Log::debug('QuotaLedger: ' . $reason, [
            'application_id' => $app->id,
        ]);
    }
}
