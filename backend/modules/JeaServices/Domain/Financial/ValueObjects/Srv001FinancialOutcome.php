<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Financial\ValueObjects;

/**
 * TD-08 · Typed outcome for every SRV-001 financial decision
 * (fee quote, tax quote, exemption, campaign, payment eligibility).
 *
 *   • QUOTED                     — rule ran on approved inputs +
 *                                  published rule version → binding.
 *   • SIMULATION_ONLY            — rule ran but rule version is
 *                                  DRAFT/PROVISIONAL/UNPUBLISHED.
 *   • BLOCKED                    — an Open Decision blocks the rule
 *                                  from producing an authoritative
 *                                  quote (OD-01, OD-19, etc.).
 *   • CONFLICTED                 — inputs contradict a constraint.
 *   • INSUFFICIENT_INPUT         — required numeric input missing.
 *   • EXEMPTION_PENDING          — exemption claim not yet decided.
 *   • EXTERNAL_CONTRACT_MISSING  — required integration contract
 *                                  (Oracle tax collection etc.)
 *                                  not signed → fail closed.
 *   • PAYMENT_NOT_ALLOWED        — the workflow / eligibility path
 *                                  forbids payment (used by the
 *                                  payment-boundary from TD-07).
 *   • MANUAL_REVIEW              — human must decide.
 *   • NOT_APPLICABLE             — precondition does not apply.
 *
 * FAIL-CLOSED INVARIANT: only QUOTED permits production payment
 * initiation. Everything else must block. New outcomes default to
 * blocking (they don't appear in `bindingOutcomes()`).
 */
final class Srv001FinancialOutcome
{
    public const QUOTED                     = 'QUOTED';
    public const SIMULATION_ONLY            = 'SIMULATION_ONLY';
    public const BLOCKED                    = 'BLOCKED';
    public const CONFLICTED                 = 'CONFLICTED';
    public const INSUFFICIENT_INPUT         = 'INSUFFICIENT_INPUT';
    public const EXEMPTION_PENDING          = 'EXEMPTION_PENDING';
    public const EXTERNAL_CONTRACT_MISSING  = 'EXTERNAL_CONTRACT_MISSING';
    public const PAYMENT_NOT_ALLOWED        = 'PAYMENT_NOT_ALLOWED';
    public const MANUAL_REVIEW              = 'MANUAL_REVIEW';
    public const NOT_APPLICABLE             = 'NOT_APPLICABLE';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::QUOTED, self::SIMULATION_ONLY, self::BLOCKED,
            self::CONFLICTED, self::INSUFFICIENT_INPUT,
            self::EXEMPTION_PENDING, self::EXTERNAL_CONTRACT_MISSING,
            self::PAYMENT_NOT_ALLOWED, self::MANUAL_REVIEW,
            self::NOT_APPLICABLE,
        ];
    }

    public static function isValid(string $v): bool
    {
        return in_array($v, self::all(), true);
    }

    /**
     * Outcomes that carry a binding quote — safe for production
     * payment initiation. Today: only QUOTED.
     * @return list<string>
     */
    public static function bindingOutcomes(): array
    {
        return [self::QUOTED];
    }
}
