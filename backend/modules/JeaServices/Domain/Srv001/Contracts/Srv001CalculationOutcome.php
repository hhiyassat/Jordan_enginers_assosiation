<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

/**
 * TD-04 · Typed provenance-classification enum for the outcome of a
 * SRV-001 target calculator invocation.
 *
 * Distinct from `Srv001ExplorationStatus` — that enum categorises the
 * matrix's numeric output branch (CALCULATED / SPECIAL_STUDY_REQUIRED /
 * INELIGIBLE). This enum categorises the calculator invocation itself
 * — WHY the numeric output has (or does not have) evidentiary weight
 * for downstream target-domain decisions:
 *
 *   • CALCULATED         — numeric output produced from approved inputs
 *                          + approved rule version; safe to consume.
 *   • BLOCKED            — rule version exists but publication is
 *                          blocked by an open Ground-Truth § / OD; the
 *                          numeric output must NOT be persisted as a
 *                          binding derived value.
 *   • CONFLICTED         — inputs contradict a constraint (e.g. the
 *                          floor_count is negative); no numeric output
 *                          can be produced.
 *   • INSUFFICIENT_INPUT — required inputs missing; numeric output
 *                          cannot be attempted (distinct from CONFLICTED
 *                          which requires present-but-invalid inputs).
 *   • MANUAL_REVIEW      — rule execution requires human review
 *                          (SPECIAL_STUDY_REQUIRED with routing hint).
 *   • SIMULATION_ONLY    — numeric output computed but rule version is
 *                          DRAFT_TARGET_PROVISIONAL; usable ONLY for
 *                          side-by-side simulation vs legacy pilot.
 *   • NOT_APPLICABLE     — the calculator's preconditions do not apply
 *                          to this application (e.g., wells count on a
 *                          service that does not require wells).
 *
 * Concrete `Target*` calculators do NOT themselves emit an outcome
 * (they return numeric ServiceCalculationResult objects). The outcome
 * is derived by `Srv001CalculatorOutcomeClassifier` based on the
 * calculator's rule status, inputs, and result — keeping the
 * calculators single-responsibility (numeric compute only).
 *
 * Provenance rule: any consumer treating an outcome as CALCULATED must
 * verify the classifier's assertion that the underlying RuleVersion is
 * approved for publication (see `TargetRuleVersionPublicationPolicy`).
 */
final class Srv001CalculationOutcome
{
    public const CALCULATED         = 'CALCULATED';
    public const BLOCKED            = 'BLOCKED';
    public const CONFLICTED         = 'CONFLICTED';
    public const INSUFFICIENT_INPUT = 'INSUFFICIENT_INPUT';
    public const MANUAL_REVIEW      = 'MANUAL_REVIEW';
    public const SIMULATION_ONLY    = 'SIMULATION_ONLY';
    public const NOT_APPLICABLE     = 'NOT_APPLICABLE';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::CALCULATED,
            self::BLOCKED,
            self::CONFLICTED,
            self::INSUFFICIENT_INPUT,
            self::MANUAL_REVIEW,
            self::SIMULATION_ONLY,
            self::NOT_APPLICABLE,
        ];
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::all(), true);
    }

    /**
     * Outcomes that carry a binding numeric result — safe for
     * downstream derived-value persistence.
     *
     * @return list<string>
     */
    public static function bindingOutcomes(): array
    {
        return [self::CALCULATED];
    }

    /**
     * Outcomes that PROVE the calculator ran but must NOT be treated
     * as authoritative for target-domain decisions.
     *
     * @return list<string>
     */
    public static function nonBindingOutcomes(): array
    {
        return [
            self::BLOCKED,
            self::CONFLICTED,
            self::INSUFFICIENT_INPUT,
            self::MANUAL_REVIEW,
            self::SIMULATION_ONLY,
            self::NOT_APPLICABLE,
        ];
    }
}
