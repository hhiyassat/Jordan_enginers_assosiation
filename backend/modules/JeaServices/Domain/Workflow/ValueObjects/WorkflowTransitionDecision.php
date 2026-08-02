<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Workflow\ValueObjects;

use InvalidArgumentException;

/**
 * TD-07 · Immutable decision returned by
 * `WorkflowTransitionEvaluator::evaluate()`.
 *
 * Outcomes:
 *   • ALLOWED                     — transition permitted
 *   • BLOCKED_BY_OD               — transition exists in graph but
 *                                    is awaiting an Open Decision
 *   • BLOCKED_BY_MISSING_EVIDENCE — transition permitted by graph
 *                                    but a precondition is unmet
 *   • MANUAL_REVIEW               — human must decide
 *   • NOT_FOUND                   — no transition exists for this
 *                                    (fromState, action) in the
 *                                    graph version
 *
 * FAIL-CLOSED: `isAllowed()` returns true ONLY for ALLOWED.
 */
final class WorkflowTransitionDecision
{
    public const ALLOWED                     = 'ALLOWED';
    public const BLOCKED_BY_OD               = 'BLOCKED_BY_OD';
    public const BLOCKED_BY_MISSING_EVIDENCE = 'BLOCKED_BY_MISSING_EVIDENCE';
    public const MANUAL_REVIEW               = 'MANUAL_REVIEW';
    public const NOT_FOUND                   = 'NOT_FOUND';

    /** @param list<string> $blockingOds  @param list<string> $reasonCodes */
    public function __construct(
        public readonly string $outcome,
        public readonly string $workflowVersionId,
        public readonly string $fromState,
        public readonly string $action,
        public readonly ?string $toState,
        public readonly array $blockingOds = [],
        public readonly array $reasonCodes = [],
    ) {
        if (! in_array(
            $outcome,
            [self::ALLOWED, self::BLOCKED_BY_OD, self::BLOCKED_BY_MISSING_EVIDENCE, self::MANUAL_REVIEW, self::NOT_FOUND],
            true,
        )) {
            throw new InvalidArgumentException("Unknown WorkflowTransitionDecision outcome: {$outcome}");
        }
    }

    public function isAllowed(): bool
    {
        return $this->outcome === self::ALLOWED;
    }
}
