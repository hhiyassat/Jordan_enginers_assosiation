<?php

declare(strict_types=1);

namespace App\Engine;

use App\Models\Application;
use App\Models\Engineer;

/**
 * CapacityGuard — JORD-69
 *
 * Submit-time enforcement of the JEA Ch.9 quotas + ceilings. Runs
 * inside ApplicationController::submit() alongside SchemaValidator's
 * field + document checks; produces a 422-shaped error map if the
 * office / engineer would go over cap.
 *
 * Semantics
 * ---------
 *   • Only fires on services that carry an `area_m2` form field.
 *     Other services (CERT-*, ENG-*, FIN-*, etc.) don't consume
 *     m² — the guard is a no-op for them, returns [].
 *   • Requires `engineer_id` in the app data. Missing → hard error.
 *   • Requires area_m2 > 0. Missing / zero → hard error.
 *   • Both the engineer's remaining quota AND the office's remaining
 *     ceiling must accommodate the area. Either short → error.
 *   • Null (no cap configured) always passes — see QuotaLedger
 *     comment for the "no cap → allow" rationale.
 *
 * Error map shape matches SchemaValidator::validateData so the
 * controller can return them the same way.
 */
class CapacityGuard
{
    public function __construct(private readonly QuotaLedger $ledger) {}

    /**
     * @return array<string, string>  Empty array = OK.
     */
    public function validate(Application $app): array
    {
        $service = $app->serviceDefinition;
        if (!$service) return [];

        $fields = data_get($service->schema, 'fields', []);
        $hasArea = collect($fields)->contains(fn ($f) => ($f['id'] ?? null) === 'area_m2');
        if (!$hasArea) {
            // Non-quota-tracked service — no gate to enforce.
            return [];
        }

        $data = is_array($app->data) ? $app->data : [];
        $errors = [];

        $engineerId = $data['engineer_id'] ?? null;
        $area       = $data['area_m2']     ?? null;

        if (!is_numeric($engineerId) || (int) $engineerId <= 0) {
            $errors['engineer_id'] = 'يجب اختيار المهندس المسؤول لهذه الخدمة.';
            // No point checking capacity if we don't know the engineer.
            return $errors;
        }
        if (!is_numeric($area) || (int) $area <= 0) {
            $errors['area_m2'] = 'مساحة المشروع (م²) مطلوبة وأكبر من صفر.';
        }

        $engineer = Engineer::where('organization_id', $app->organization_id)
            ->where('id', (int) $engineerId)
            ->first();
        if (!$engineer) {
            $errors['engineer_id'] = 'المهندس المحدد غير مسجل تحت هذا المكتب.';
            return $errors;
        }

        // If area was invalid we've already recorded that error; skip
        // the quota math so the applicant sees BOTH problems at once.
        if (isset($errors['area_m2'])) return $errors;

        $discipline = Disciplines::normalize((string) ($engineer->specialization ?? ''));
        if ($discipline === '') {
            $errors['engineer_id'] = 'المهندس المحدد بدون اختصاص هندسي — يجب تحديث بياناته.';
            return $errors;
        }

        $year   = (int) now()->year;
        $areaI  = (int) $area;

        $engineerRem = $this->ledger->remainingEngineerQuota($engineer, $discipline, $year);
        if ($engineerRem !== null && $engineerRem < $areaI) {
            $errors['engineer_id'] = sprintf(
                'حصة المهندس %s السنوية لاختصاص %s غير كافية (المتبقي %d م²، والمطلوب %d م²).',
                $engineer->name_ar ?? $engineer->membership_number,
                Disciplines::labels()[$discipline]['ar'] ?? $discipline,
                $engineerRem,
                $areaI,
            );
        }

        // JORD-71: pass governorate so the ledger can apply the +10%
        // overflow when this office has hit 90% in that governorate.
        // Absent governorate → no overflow (whole-org check only).
        $governorate = is_string($data['governorate'] ?? null) ? $data['governorate'] : null;
        $officeRem = $this->ledger->remainingOfficeCeiling(
            $app->organization_id, $discipline, $year, $governorate,
        );
        if ($officeRem !== null && $officeRem < $areaI) {
            $errors['office_ceiling'] = sprintf(
                'سقف المكتب السنوي لاختصاص %s غير كافٍ (المتبقي %d م²، والمطلوب %d م²).',
                Disciplines::labels()[$discipline]['ar'] ?? $discipline,
                $officeRem,
                $areaI,
            );
        }

        return $errors;
    }
}
