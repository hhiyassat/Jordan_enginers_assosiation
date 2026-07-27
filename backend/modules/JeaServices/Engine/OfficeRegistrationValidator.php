<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

use Modules\JeaProjects\Engine\Disciplines;

/**
 * OfficeRegistrationValidator — enforces the business rules for office
 * registration that are not already covered by the FormRequest's
 * per-field shape validation.
 *
 * Rules (per stakeholder input 2026-07-27):
 *   1. At least 1 engineer must be submitted.
 *   2. If EXACTLY 1 engineer is submitted, its discipline (after
 *      Disciplines::normalize() folds `civil` → `structural`) MUST be
 *      `structural` OR `architectural`. Two or more engineers may hold
 *      any mix of disciplines.
 *   3. Every submitted engineer's (name, membership_number) is verified
 *      against JeaMembershipVerifier. Any failure yields a per-engineer
 *      error keyed by position (engineers.{index}.membership_number).
 *
 * Returns [] on pass; associative dotted-path keyed error array on fail
 * — matches Laravel FormRequest error shape so it can be merged into
 * the standard 422 response.
 */
final class OfficeRegistrationValidator
{
    public function __construct(
        private readonly JeaMembershipVerifier $membershipVerifier,
    ) {}

    /**
     * @param  list<array{name_ar?: string, name_en?: string, membership_number?: string, specialization?: string}>  $engineers
     * @return array<string, string>
     */
    public function validate(array $engineers): array
    {
        $errors = [];

        // Rule 1 — min 1 engineer
        if (empty($engineers)) {
            $errors['engineers'] = 'يجب إضافة مهندس واحد على الأقل للمكتب.';
            return $errors;
        }

        // Rule 2 — single-engineer discipline constraint
        if (count($engineers) === 1) {
            $spec = $engineers[0]['specialization'] ?? '';
            $normalized = Disciplines::normalize($spec);
            $allowed = [Disciplines::STRUCTURAL, Disciplines::ARCHITECTURAL];
            if (! in_array($normalized, $allowed, true)) {
                $errors['engineers.0.specialization'] =
                    'إذا كان المكتب يضم مهندساً واحداً فقط، يجب أن يكون تخصصه مدني (إنشائي) أو عمارة.';
            }
        }

        // Rule 3 — every engineer verified against JEA membership registry.
        // Do this even when the discipline rule failed above — a JEA-side
        // failure is a separate concern and the applicant should see both.
        foreach ($engineers as $i => $engineer) {
            $name = trim((string) ($engineer['name_ar'] ?? $engineer['name_en'] ?? ''));
            $membershipNo = trim((string) ($engineer['membership_number'] ?? ''));

            if ($name === '' || $membershipNo === '') {
                // FormRequest per-field validation should have caught these;
                // guard defensively without adding a duplicate error here.
                continue;
            }

            $result = $this->membershipVerifier->verify($name, $membershipNo);
            if (! $result->isValid) {
                $errors["engineers.{$i}.membership_number"] =
                    "المهندس رقم " . ($i + 1) . ": " . $result->reasonAr;
            }
        }

        return $errors;
    }
}
