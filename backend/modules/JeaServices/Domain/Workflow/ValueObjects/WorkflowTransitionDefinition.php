<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Workflow\ValueObjects;

use InvalidArgumentException;

/**
 * TD-07 · Immutable transition definition — bound to a WorkflowVersion.
 *
 * Definitions with `runtimeStatus=BLOCKED_BY_OD` are represented so
 * downstream consumers get a typed BLOCKED decision, not a "not
 * found" error. This distinguishes "the transition exists but is
 * awaiting an OD" from "the transition doesn't exist in this
 * version's graph".
 */
final class WorkflowTransitionDefinition
{
    public const RUNTIME_ALLOWED        = 'ALLOWED';
    public const RUNTIME_BLOCKED_BY_OD  = 'BLOCKED_BY_OD';
    public const RUNTIME_MANUAL_REVIEW  = 'MANUAL_REVIEW';

    /** @param list<string> $blockingOds */
    public function __construct(
        public readonly string $workflowVersionId,
        public readonly string $fromState,
        public readonly string $action,
        public readonly ?string $toState,
        public readonly string $runtimeStatus,
        public readonly array $blockingOds = [],
    ) {
        WorkflowState::assertValid($fromState);
        if ($toState !== null) {
            WorkflowState::assertValid($toState);
        }
        if (! in_array(
            $runtimeStatus,
            [self::RUNTIME_ALLOWED, self::RUNTIME_BLOCKED_BY_OD, self::RUNTIME_MANUAL_REVIEW],
            true,
        )) {
            throw new InvalidArgumentException("Unknown transition runtimeStatus: {$runtimeStatus}");
        }
        if ($runtimeStatus === self::RUNTIME_BLOCKED_BY_OD && $blockingOds === []) {
            throw new InvalidArgumentException('BLOCKED_BY_OD transition requires at least one blocking OD id');
        }
    }
}
