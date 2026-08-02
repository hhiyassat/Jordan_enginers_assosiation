<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Workflow;

use Modules\JeaServices\Domain\Workflow\ValueObjects\WorkflowTransitionDecision;
use Modules\JeaServices\Domain\Workflow\ValueObjects\WorkflowTransitionDefinition;

/**
 * TD-07 · Pure decision function that evaluates a transition against
 * the current graph version.
 *
 * FAIL-CLOSED. Returns NOT_FOUND when the (fromState, action) pair
 * has no definition — callers MUST NOT infer "transition allowed"
 * from a missing entry.
 */
final class WorkflowTransitionEvaluator
{
    public function __construct(private readonly Srv001WorkflowGraph $graph)
    {
    }

    public function evaluate(string $fromState, string $action): WorkflowTransitionDecision
    {
        $version = $this->graph->currentVersion();
        foreach ($this->graph->transitions() as $t) {
            if ($t->fromState !== $fromState || $t->action !== $action) {
                continue;
            }
            return match ($t->runtimeStatus) {
                WorkflowTransitionDefinition::RUNTIME_ALLOWED => new WorkflowTransitionDecision(
                    outcome:           WorkflowTransitionDecision::ALLOWED,
                    workflowVersionId: $version->versionId,
                    fromState:         $fromState,
                    action:            $action,
                    toState:           $t->toState,
                ),
                WorkflowTransitionDefinition::RUNTIME_BLOCKED_BY_OD => new WorkflowTransitionDecision(
                    outcome:           WorkflowTransitionDecision::BLOCKED_BY_OD,
                    workflowVersionId: $version->versionId,
                    fromState:         $fromState,
                    action:            $action,
                    toState:           $t->toState,
                    blockingOds:       $t->blockingOds,
                    reasonCodes:       ['TRANSITION_AWAITING_OD_CLOSURE'],
                ),
                WorkflowTransitionDefinition::RUNTIME_MANUAL_REVIEW => new WorkflowTransitionDecision(
                    outcome:           WorkflowTransitionDecision::MANUAL_REVIEW,
                    workflowVersionId: $version->versionId,
                    fromState:         $fromState,
                    action:            $action,
                    toState:           $t->toState,
                ),
                default => new WorkflowTransitionDecision(
                    outcome:           WorkflowTransitionDecision::NOT_FOUND,
                    workflowVersionId: $version->versionId,
                    fromState:         $fromState,
                    action:            $action,
                    toState:           null,
                ),
            };
        }
        return new WorkflowTransitionDecision(
            outcome:           WorkflowTransitionDecision::NOT_FOUND,
            workflowVersionId: $version->versionId,
            fromState:         $fromState,
            action:            $action,
            toState:           null,
            reasonCodes:       ['NO_TRANSITION_IN_GRAPH_VERSION'],
        );
    }
}
