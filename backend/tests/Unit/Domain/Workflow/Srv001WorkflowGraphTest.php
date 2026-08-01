<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Workflow;

use Modules\JeaServices\Domain\Workflow\Srv001WorkflowGraph;
use Modules\JeaServices\Domain\Workflow\ValueObjects\WorkflowAction;
use Modules\JeaServices\Domain\Workflow\ValueObjects\WorkflowState;
use Modules\JeaServices\Domain\Workflow\ValueObjects\WorkflowTransitionDecision;
use Modules\JeaServices\Domain\Workflow\ValueObjects\WorkflowTransitionDefinition;
use Modules\JeaServices\Domain\Workflow\ValueObjects\WorkflowVersion;
use Modules\JeaServices\Domain\Workflow\WorkflowTransitionEvaluator;
use PHPUnit\Framework\TestCase;

class Srv001WorkflowGraphTest extends TestCase
{
    private WorkflowTransitionEvaluator $eval;

    protected function setUp(): void
    {
        $this->eval = new WorkflowTransitionEvaluator(new Srv001WorkflowGraph());
    }

    // (1) confirmed structural transition succeeds.
    public function test_confirmed_submit_transition_is_ALLOWED(): void
    {
        $d = $this->eval->evaluate(WorkflowState::DRAFT, WorkflowAction::SUBMIT_APPLICATION);
        $this->assertSame(WorkflowTransitionDecision::ALLOWED, $d->outcome);
        $this->assertSame(WorkflowState::SUBMITTED, $d->toState);
    }

    // (2) invalid transition rejected.
    public function test_invalid_transition_returns_NOT_FOUND(): void
    {
        $d = $this->eval->evaluate(WorkflowState::DRAFT, WorkflowAction::COMPLETE_APPLICATION);
        $this->assertSame(WorkflowTransitionDecision::NOT_FOUND, $d->outcome);
        $this->assertFalse($d->isAllowed());
    }

    // (3) OD-34 transition returns typed BLOCKED result.
    public function test_od34_second_review_approve_returns_BLOCKED_BY_OD(): void
    {
        $d = $this->eval->evaluate(WorkflowState::FIRST_TECHNICAL_REVIEW, WorkflowAction::SECOND_REVIEW_APPROVE);
        $this->assertSame(WorkflowTransitionDecision::BLOCKED_BY_OD, $d->outcome);
        $this->assertContains('OD-34', $d->blockingOds);
    }

    // (4) OD-31 committee substitution — NOT in graph → NOT_FOUND
    //     (representing "no committee action is runtime-eligible" vs
    //     "blocked awaiting OD").
    public function test_od31_committee_substitution_action_is_not_in_graph(): void
    {
        // No 'committee_review_approve' action enumerated — a caller
        // trying to route through a committee gets NOT_FOUND from
        // the graph. This is the correct fail-closed shape: the
        // action does not exist in this version of the graph.
        $d = $this->eval->evaluate(
            WorkflowState::OFFICES_DEPT_REVIEW,
            'committee_review_approve', // non-enumerated action
        );
        $this->assertSame(WorkflowTransitionDecision::NOT_FOUND, $d->outcome);
    }

    // (5) OD-32 alternate second reviewer — NOT in graph.
    public function test_od32_alternate_second_reviewer_action_is_not_in_graph(): void
    {
        $d = $this->eval->evaluate(
            WorkflowState::FIRST_TECHNICAL_REVIEW,
            'alternate_second_reviewer_approve',
        );
        $this->assertSame(WorkflowTransitionDecision::NOT_FOUND, $d->outcome);
    }

    // (6) OD-33 sensory-inspection gate — NOT in graph.
    public function test_od33_sensory_inspection_gate_action_is_not_in_graph(): void
    {
        $d = $this->eval->evaluate(
            WorkflowState::FIRST_TECHNICAL_REVIEW,
            'sensory_inspection_gate_open',
        );
        $this->assertSame(WorkflowTransitionDecision::NOT_FOUND, $d->outcome);
    }

    // (7) transition remains bound to WorkflowVersion.
    public function test_every_decision_carries_workflow_version_id(): void
    {
        $d = $this->eval->evaluate(WorkflowState::DRAFT, WorkflowAction::SUBMIT_APPLICATION);
        $this->assertSame(Srv001WorkflowGraph::CURRENT_VERSION_ID, $d->workflowVersionId);
    }

    // (8) historical transition version remains immutable.
    public function test_transition_definitions_are_immutable_readonly_properties(): void
    {
        $graph  = new Srv001WorkflowGraph();
        $transitions = $graph->transitions();
        $this->assertNotEmpty($transitions);
        foreach ($transitions as $t) {
            $refl = new \ReflectionClass($t);
            foreach ($refl->getProperties() as $p) {
                $this->assertTrue($p->isReadOnly(),
                    "WorkflowTransitionDefinition::\${$p->getName()} must be readonly");
            }
        }
    }

    // Version metadata invariants
    public function test_current_workflow_version_is_INACTIVE_and_lists_blocking_ods(): void
    {
        $v = (new Srv001WorkflowGraph())->currentVersion();
        $this->assertFalse($v->isRuntimeActive());
        $this->assertSame(WorkflowVersion::RUNTIME_INACTIVE, $v->runtimeStatus);
        foreach (['OD-18', 'OD-29', 'OD-30', 'OD-31', 'OD-32', 'OD-33', 'OD-34'] as $od) {
            $this->assertContains($od, $v->blockingOds);
        }
    }

    // BLOCKED_BY_OD without any blocking OD id is a construction error.
    public function test_blocked_transition_definition_requires_blocking_od(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WorkflowTransitionDefinition(
            workflowVersionId: 'v',
            fromState:         WorkflowState::DRAFT,
            action:            WorkflowAction::SUBMIT_APPLICATION,
            toState:           WorkflowState::SUBMITTED,
            runtimeStatus:     WorkflowTransitionDefinition::RUNTIME_BLOCKED_BY_OD,
            blockingOds:       [],
        );
    }
}
