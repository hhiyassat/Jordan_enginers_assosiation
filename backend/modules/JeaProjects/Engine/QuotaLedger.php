<?php

declare(strict_types=1);

namespace Modules\JeaProjects\Engine;

use App\Models\Application;
use Modules\JeaProjects\Models\Engineer;
use Modules\JeaProjects\Models\EngineerDisciplineQuota;
use Modules\JeaProjects\Models\OfficeCeiling;
use Modules\JeaProjects\Models\OfficeCoalition;
use Modules\JeaProjects\Models\QuotaConsumption;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * QuotaLedger — JORD-68 + JORD-77 (per-office refactor)
 *
 * Owns the quota consumption/reversal write path and the "remaining"
 * read helpers. As of JORD-77 every quota / ceiling / boost is keyed
 * on the OFFICE USER (a User with role='applicant'), not the
 * enclosing Organization — matching how JEA's real data model works
 * (one Org can house many offices).
 *
 * The relevant office_user_id for an Application is the applicant_id
 * on the row (offices submit as themselves). Legacy organization_id
 * columns are kept on the tables as a denormalization + safety net
 * but are no longer authoritative — this class reads office_user_id.
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

        // JORD-77: office_user_id is the applicant on this app.
        // Fall back to organization_id lookup if applicant_id is not
        // set (edge case, e.g. system-generated applications).
        $officeUserId = $app->applicant_id ?: null;

        QuotaConsumption::updateOrCreate(
            [
                'application_id' => $app->id,
                'engineer_id'    => $engineer->id,
                'discipline'     => $discipline,
            ],
            [
                'organization_id' => $app->organization_id,
                'office_user_id'  => $officeUserId,
                'year'            => (int) now()->year,
                'm2'              => (int) $quantity,
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
     * Applies the JORD-70 engineer-level +20% spec-head boost.
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
     * JORD-77: office-level ceiling keyed on office_user_id (a User).
     *
     * Applies:
     *   • JORD-70 boosts read from the office user's own flags
     *     (has_excellence_award, is_bit_khibra, has_iso_cert), each
     *     +5% additive. Per-office, not per-organization.
     *   • JORD-71 governorate-scoped +10% overflow at 90% consumption.
     *   • JORD-73 coalition aggregation: if the office is in an
     *     active coalition, the ceiling is ((n-0.5)/n) × Σ(member
     *     ceilings) and consumption sums across all coalition members.
     *
     * Returns null when no OfficeCeiling row exists — callers treat
     * that as "no cap configured" (allow), not "0 cap" (block).
     */
    public function remainingOfficeCeiling(
        int $officeUserId,
        string $discipline,
        int $year,
        ?string $governorate = null,
    ): ?int {
        $discipline = Disciplines::normalize($discipline);

        // Coalition-aware branch — coalitions are keyed on office_user_id
        // post-JORD-77 too (see OfficeCoalitionMember + User::activeCoalition).
        $officeUser = User::find($officeUserId);
        $coalition  = $officeUser?->activeCoalition();
        if ($coalition !== null) {
            return $this->remainingCoalitionCeiling($coalition, $discipline, $year, $governorate);
        }

        $ceiling = OfficeCeiling::where('office_user_id', $officeUserId)
            ->where('discipline', $discipline)
            ->where('year', $year)
            ->first();
        if (!$ceiling) return null;

        $boostMultiplier = 1.0
            + ($officeUser && $officeUser->has_excellence_award ? 0.05 : 0.0)
            + ($officeUser && $officeUser->is_bit_khibra        ? 0.05 : 0.0)
            + ($officeUser && $officeUser->has_iso_cert         ? 0.05 : 0.0);

        // JORD-71: governorate-scoped 90% → +10% overflow.
        if ($governorate !== null) {
            $governorateConsumed = (int) QuotaConsumption::where('office_user_id', $officeUserId)
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

        $consumed = QuotaConsumption::where('office_user_id', $officeUserId)
            ->where('discipline', $discipline)
            ->where('year', $year)
            ->sum('m2');

        return max(0, $effective - (int) $consumed);
    }

    /**
     * Aggregated coalition ceiling per manual p.136:
     *   coalition_ceiling = ((n-0.5)/n) × Σ(member_ceilings)
     *
     * Coalitions are between OFFICES post-JORD-77, so member ids are
     * office_user_ids. Consumption sums across all active member
     * office users so quota use anywhere in the coalition counts.
     */
    private function remainingCoalitionCeiling(
        OfficeCoalition $coalition,
        string $discipline,
        int $year,
        ?string $governorate,
    ): ?int {
        $memberOfficeIds = $coalition->activeMembers()->pluck('office_user_id')->filter()->all();
        if (empty($memberOfficeIds)) return null;

        $memberCeilings = OfficeCeiling::whereIn('office_user_id', $memberOfficeIds)
            ->where('discipline', $discipline)
            ->where('year', $year)
            ->pluck('m2_allowed')
            ->all();
        if (empty($memberCeilings)) return null;

        $n   = count($memberOfficeIds);
        $sum = array_sum($memberCeilings);
        $effective = (int) floor((($n - 0.5) / $n) * $sum);

        $consumed = (int) QuotaConsumption::whereIn('office_user_id', $memberOfficeIds)
            ->where('discipline', $discipline)
            ->where('year', $year)
            ->sum('m2');

        return max(0, $effective - $consumed);
    }

    /**
     * JORD-72 + JORD-73: overflow surcharge when this application's
     * area exceeds the office's (or coalition's) per-project cap.
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
        $officeUserId = $app->applicant_id;

        // JORD-73 + JORD-77: coalition aggregation keyed on office_user_id.
        $officeUser = User::find($officeUserId);
        $coalition  = $officeUser?->activeCoalition();
        if ($coalition !== null) {
            $memberOfficeIds = $coalition->activeMembers()->pluck('office_user_id')->filter()->all();
            $caps = OfficeCeiling::whereIn('office_user_id', $memberOfficeIds)
                ->where('discipline', $discipline)
                ->where('year', $year)
                ->whereNotNull('per_project_cap_m2')
                ->pluck('per_project_cap_m2')
                ->all();
            if (empty($caps)) return null;
            $perProjectCap = (int) floor(1.5 * (array_sum($caps) / count($caps)));
        } else {
            $ceiling = OfficeCeiling::where('office_user_id', $officeUserId)
                ->where('discipline', $discipline)
                ->where('year', $year)
                ->first();
            if (!$ceiling || $ceiling->per_project_cap_m2 === null) return null;
            $perProjectCap = (int) $ceiling->per_project_cap_m2;
        }

        $areaI = (int) $area;
        if ($areaI <= $perProjectCap) return null;

        $excess = $areaI - $perProjectCap;

        $fee = data_get($svc->schema, 'fee', []);
        $baseRate = $this->resolveBaseRatePerM2($fee, $data);
        if ($baseRate === null || $baseRate <= 0) return null;

        $amount = round(0.25 * $excess * $baseRate, 2);

        return [
            'code'     => 'per_project_cap_overflow_25pct',
            'kind'     => 'percent_of_excess',
            'label_ar' => sprintf('رسم تجاوز سقف المشروع الواحد (25%% × %d م² زائدة)', $excess),
            'label_en' => sprintf('Per-project cap overflow (25%% × %d m² excess)', $excess),
            'amount'   => (float) $amount,
            'source'   => 'كتاب التعليمات الفنية 2025 ص 129',
        ];
    }

    private function resolveBaseRatePerM2(array $fee, array $formData): ?float
    {
        $type = $fee['type'] ?? null;

        if ($type === 'per_unit' && ($fee['basis'] ?? null) === 'area_m2' && is_numeric($fee['rate'] ?? null)) {
            return (float) $fee['rate'];
        }

        if ($type === 'matrix' && ($fee['basis'] ?? null) === 'area_m2') {
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
