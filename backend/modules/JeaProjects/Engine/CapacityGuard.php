<?php

declare(strict_types=1);

namespace Modules\JeaProjects\Engine;

use Modules\JeaServices\Models\Application;
use Modules\JeaProjects\Models\Engineer;

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

        // JORD-75: schema-declared basis field. Default 'area_m2' for
        // backwards compat (DRW-P-*, SRV-008/009). Services with a
        // different quantity concept (SRV-006 government surveys →
        // 'length_lm') set schema.quota_basis_field to override.
        $basisField = data_get($service->schema, 'quota_basis_field', 'area_m2');
        if (!is_string($basisField) || $basisField === '') {
            $basisField = 'area_m2';
        }

        $fields = data_get($service->schema, 'fields', []);
        $hasBasis = collect($fields)->contains(fn ($f) => ($f['id'] ?? null) === $basisField);
        if (!$hasBasis) {
            // Service isn't quota-tracked — no basis field declared.
            return [];
        }

        $data = is_array($app->data) ? $app->data : [];
        $errors = [];

        $engineerId = $data['engineer_id']    ?? null;
        $quantity   = $data[$basisField]      ?? null;

        if (!is_numeric($engineerId) || (int) $engineerId <= 0) {
            $errors['engineer_id'] = 'يجب اختيار المهندس المسؤول لهذه الخدمة.';
            // No point checking capacity if we don't know the engineer.
            return $errors;
        }
        if (!is_numeric($quantity) || (int) $quantity <= 0) {
            $unitLabel = $basisField === 'length_lm' ? 'م.ط' : 'م²';
            $errors[$basisField] = "الكمية ({$unitLabel}) مطلوبة وأكبر من صفر.";
        }

        $engineer = Engineer::where('organization_id', $app->organization_id)
            ->where('id', (int) $engineerId)
            ->first();
        if (!$engineer) {
            $errors['engineer_id'] = 'المهندس المحدد غير مسجل تحت هذا المكتب.';
            return $errors;
        }

        // If quantity was invalid we've already recorded that error; skip
        // the quota math so the applicant sees BOTH problems at once.
        if (isset($errors[$basisField])) return $errors;

        // JORD-74: schema.quota_discipline_override redirects the
        // capacity check to a service-wide bucket (e.g. 'materials_testing'
        // on SRV-008/009). The engineer picker still runs — the office
        // needs to attribute the work — but their own specialization
        // doesn't gate the check.
        $override = data_get($service->schema, 'quota_discipline_override');
        if (is_string($override) && $override !== '') {
            $discipline = $override;
        } else {
            $discipline = Disciplines::normalize((string) ($engineer->specialization ?? ''));
            if ($discipline === '') {
                $errors['engineer_id'] = 'المهندس المحدد بدون اختصاص هندسي — يجب تحديث بياناته.';
                return $errors;
            }
        }

        $year      = (int) now()->year;
        $quantityI = (int) $quantity;
        $unit      = $basisField === 'length_lm' ? 'م.ط' : 'م²';

        $engineerRem = $this->ledger->remainingEngineerQuota($engineer, $discipline, $year);
        if ($engineerRem !== null && $engineerRem < $quantityI) {
            $errors['engineer_id'] = sprintf(
                'حصة المهندس %s السنوية لاختصاص %s غير كافية (المتبقي %d %s، والمطلوب %d %s).',
                $engineer->name_ar ?? $engineer->membership_number,
                Disciplines::labels()[$discipline]['ar'] ?? $discipline,
                $engineerRem, $unit, $quantityI, $unit,
            );
        }

        // JORD-71 + JORD-77: pass governorate so the ledger can apply
        // the +10% overflow when this office has hit 90% in that
        // governorate. Absent governorate → no overflow.
        // Ceiling is keyed on office_user_id (= applicant on this app),
        // not the enclosing organization.
        $governorate = is_string($data['governorate'] ?? null) ? $data['governorate'] : null;
        $officeRem = $this->ledger->remainingOfficeCeiling(
            (int) $app->applicant_id, $discipline, $year, $governorate,
        );
        if ($officeRem !== null && $officeRem < $quantityI) {
            $errors['office_ceiling'] = sprintf(
                'سقف المكتب السنوي لاختصاص %s غير كافٍ (المتبقي %d %s، والمطلوب %d %s).',
                Disciplines::labels()[$discipline]['ar'] ?? $discipline,
                $officeRem, $unit, $quantityI, $unit,
            );
        }

        return $errors;
    }
}
