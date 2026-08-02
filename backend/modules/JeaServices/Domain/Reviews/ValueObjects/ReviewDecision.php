<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Reviews\ValueObjects;

use InvalidArgumentException;

/**
 * TD-07 · Immutable review decision.
 *
 * INVARIANT: `RETURN` or `REJECT` outcomes MUST carry at least one
 * mandatory-rejection note. Construction-time guard enforces this;
 * a bad decision cannot be constructed.
 */
final class ReviewDecision
{
    public const OUTCOME_APPROVE = 'APPROVE';
    public const OUTCOME_RETURN  = 'RETURN';
    public const OUTCOME_REJECT  = 'REJECT';

    /** @param list<ReviewNote> $notes */
    public function __construct(
        public readonly string $outcome,
        public readonly int $applicationId,
        public readonly int $reviewerUserId,
        public readonly string $reviewerRole,
        public readonly string $timestamp,
        public readonly array $notes = [],
    ) {
        if (! in_array($outcome, [self::OUTCOME_APPROVE, self::OUTCOME_RETURN, self::OUTCOME_REJECT], true)) {
            throw new InvalidArgumentException("Unknown ReviewDecision outcome: {$outcome}");
        }
        if ($outcome !== self::OUTCOME_APPROVE) {
            $hasRejection = false;
            foreach ($notes as $n) {
                if ($n->category === ReviewNote::CATEGORY_MANDATORY_REJECTION) {
                    $hasRejection = true;
                    break;
                }
            }
            if (! $hasRejection) {
                throw new InvalidArgumentException(
                    'RETURN / REJECT decisions require at least one MANDATORY_REJECTION note',
                );
            }
        }
    }
}
