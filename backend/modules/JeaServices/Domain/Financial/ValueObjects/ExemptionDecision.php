<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Financial\ValueObjects;

use InvalidArgumentException;

/**
 * TD-08 · Immutable exemption decision.
 *
 * Exemption kinds:
 *   ENGINEER              — lifetime engineer exemption (must be
 *                            concurrency-safe unique per engineer
 *                            when persisted)
 *   EMPLOYEE              — JEA employee
 *   ASSOCIATION           — professional association
 *   PLACE_OF_WORSHIP      — religious buildings (OD-05 or similar)
 *   REGIONAL              — governorate-based (OD-35, currently unresolved)
 *
 * OUTCOMES:
 *   APPROVED_PENDING_EFFECT  — approved but effect on tax/quota
 *                              cannot land until rules publish
 *   REJECTED
 *   PENDING_EVIDENCE
 *   BLOCKED_BY_OD            — e.g. OD-35 for REGIONAL
 */
final class ExemptionDecision
{
    public const KIND_ENGINEER          = 'ENGINEER';
    public const KIND_EMPLOYEE          = 'EMPLOYEE';
    public const KIND_ASSOCIATION       = 'ASSOCIATION';
    public const KIND_PLACE_OF_WORSHIP  = 'PLACE_OF_WORSHIP';
    public const KIND_REGIONAL          = 'REGIONAL';

    public const OUTCOME_APPROVED_PENDING_EFFECT = 'APPROVED_PENDING_EFFECT';
    public const OUTCOME_REJECTED                = 'REJECTED';
    public const OUTCOME_PENDING_EVIDENCE        = 'PENDING_EVIDENCE';
    public const OUTCOME_BLOCKED_BY_OD           = 'BLOCKED_BY_OD';

    /** @param list<string> $blockingOds  @param list<string> $reasonCodes */
    public function __construct(
        public readonly string $kind,
        public readonly string $outcome,
        public readonly int $applicantUserId,
        public readonly array $blockingOds = [],
        public readonly array $reasonCodes = [],
    ) {
        if (! in_array(
            $kind,
            [self::KIND_ENGINEER, self::KIND_EMPLOYEE, self::KIND_ASSOCIATION, self::KIND_PLACE_OF_WORSHIP, self::KIND_REGIONAL],
            true,
        )) {
            throw new InvalidArgumentException("Unknown exemption kind: {$kind}");
        }
        if (! in_array(
            $outcome,
            [self::OUTCOME_APPROVED_PENDING_EFFECT, self::OUTCOME_REJECTED, self::OUTCOME_PENDING_EVIDENCE, self::OUTCOME_BLOCKED_BY_OD],
            true,
        )) {
            throw new InvalidArgumentException("Unknown exemption outcome: {$outcome}");
        }
        if ($outcome === self::OUTCOME_BLOCKED_BY_OD && $blockingOds === []) {
            throw new InvalidArgumentException('BLOCKED_BY_OD requires at least one blocking OD id');
        }
    }

    /**
     * INVARIANT: an exemption decision does NOT silently reduce tax
     * or quota. Downstream effects only apply when approved
     * financial rules exist AND publish.
     */
    public function hasRuntimeFinancialEffect(): bool
    {
        return false;
    }
}
