<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Reviews\ValueObjects;

use InvalidArgumentException;

/**
 * TD-07 · Immutable review note attached to a review decision.
 *
 * Categories:
 *   • MANDATORY_REJECTION — required when returning / rejecting;
 *     downstream MUST refuse to persist a rejection without one.
 *   • OPTIONAL_ACCEPTANCE — free-form staff note on approval.
 *   • COMMUNITY_OBSERVATION — not automatically blocking; surfaced
 *     as advisory. Requires explicit further decision to block.
 *   • INTERNAL_MANDATORY  — office/parcel-scoped; may block only
 *     with an authorized decision.
 */
final class ReviewNote
{
    public const CATEGORY_MANDATORY_REJECTION = 'MANDATORY_REJECTION';
    public const CATEGORY_OPTIONAL_ACCEPTANCE = 'OPTIONAL_ACCEPTANCE';
    public const CATEGORY_COMMUNITY_OBSERVATION = 'COMMUNITY_OBSERVATION';
    public const CATEGORY_INTERNAL_MANDATORY  = 'INTERNAL_MANDATORY';

    public function __construct(
        public readonly string $category,
        public readonly string $noteTextAr,
        public readonly int $authorUserId,
        public readonly string $timestamp,
    ) {
        if (! in_array(
            $category,
            [self::CATEGORY_MANDATORY_REJECTION, self::CATEGORY_OPTIONAL_ACCEPTANCE, self::CATEGORY_COMMUNITY_OBSERVATION, self::CATEGORY_INTERNAL_MANDATORY],
            true,
        )) {
            throw new InvalidArgumentException("Unknown ReviewNote category: {$category}");
        }
        if ($noteTextAr === '' || $timestamp === '') {
            throw new InvalidArgumentException('noteTextAr + timestamp required');
        }
    }

    public function isAutomaticallyBlocking(): bool
    {
        // Only mandatory rejection notes block automatically.
        // Community observations require additional authorized
        // decision (returned via MandatoryNoteDecision + review
        // decision), not automatic propagation.
        return $this->category === self::CATEGORY_MANDATORY_REJECTION;
    }
}
