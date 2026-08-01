<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Workflow;

use Modules\JeaServices\Domain\Workflow\ValueObjects\WorkflowAction;
use Modules\JeaServices\Domain\Workflow\ValueObjects\WorkflowState;
use Modules\JeaServices\Domain\Workflow\ValueObjects\WorkflowTransitionDefinition;
use Modules\JeaServices\Domain\Workflow\ValueObjects\WorkflowVersion;

/**
 * TD-07 · Canonical SRV-001 workflow graph builder.
 *
 * Returns the structural sequence supported by evidence:
 *
 *   draft
 *     → submitted (submit_application)
 *     → offices_dept_review (offices_dept_approve)
 *     → first_technical_review (first_review_approve)
 *     → second_technical_review (second_review_approve) [BLOCKED_BY_OD_34]
 *     → payment_eligible (mark_payment_eligible)
 *     → payment_confirmed (record_payment_confirmed)
 *     → certificate_eligible (mark_certificate_eligible)
 *     → completed (complete_application)
 *
 * Plus return + reject side transitions.
 *
 * OD-blocked transitions (represented as BLOCKED_BY_OD, NOT omitted
 * from the graph):
 *
 *   • OD-34 — second_technical_review → first_review final approval loop
 *   • OD-31 — offices_dept_review → committee_review (substitute for first reviewer)
 *   • OD-32 — first_technical_review → alternate_second_reviewer
 *   • OD-33 — first_technical_review → sensory_inspection_gate
 *   • OD-18 — special path resumption + reinforcement effects
 *   • OD-29 — final action/state dictionary + service-path model
 *
 * Structural graph, NOT final approval of transitions.
 *
 * The graph is versioned. Existing applications remain bound to
 * their original workflow_version_id via the transition-definition
 * carrier. Mutations produce a NEW version.
 */
final class Srv001WorkflowGraph
{
    public const CURRENT_VERSION_ID = 'srv001-workflow-td07-provisional-v1';

    public function currentVersion(): WorkflowVersion
    {
        // Runtime status stays INACTIVE — TD-07 is structural.
        return new WorkflowVersion(
            versionId:                    self::CURRENT_VERSION_ID,
            serviceCode:                  'SRV-001',
            sourceStatus:                 'DRAFT_SRS_V1_2',
            businessApprovalStatus:       'UNVERIFIED',
            implementationAuthorization:  'PROVISIONAL',
            publicationAuthorization:     'NOT_AUTHORIZED',
            runtimeStatus:                WorkflowVersion::RUNTIME_INACTIVE,
            effectiveFrom:                null,
            effectiveTo:                  null,
            blockingOds:                  ['OD-18', 'OD-29', 'OD-30', 'OD-31', 'OD-32', 'OD-33', 'OD-34'],
        );
    }

    /**
     * @return list<WorkflowTransitionDefinition>
     */
    public function transitions(): array
    {
        $v = self::CURRENT_VERSION_ID;

        return [
            // Confirmed structural path
            new WorkflowTransitionDefinition($v, WorkflowState::DRAFT,                 WorkflowAction::SUBMIT_APPLICATION,       WorkflowState::SUBMITTED,                    WorkflowTransitionDefinition::RUNTIME_ALLOWED),
            new WorkflowTransitionDefinition($v, WorkflowState::SUBMITTED,             WorkflowAction::OFFICES_DEPT_APPROVE,     WorkflowState::OFFICES_DEPT_REVIEW,          WorkflowTransitionDefinition::RUNTIME_ALLOWED),
            new WorkflowTransitionDefinition($v, WorkflowState::OFFICES_DEPT_REVIEW,   WorkflowAction::FIRST_REVIEW_APPROVE,     WorkflowState::FIRST_TECHNICAL_REVIEW,       WorkflowTransitionDefinition::RUNTIME_ALLOWED),
            // OD-34 blocked: which transition wins after second review is unresolved
            new WorkflowTransitionDefinition($v, WorkflowState::FIRST_TECHNICAL_REVIEW, WorkflowAction::SECOND_REVIEW_APPROVE,   WorkflowState::SECOND_TECHNICAL_REVIEW,      WorkflowTransitionDefinition::RUNTIME_BLOCKED_BY_OD, ['OD-34']),
            new WorkflowTransitionDefinition($v, WorkflowState::SECOND_TECHNICAL_REVIEW, WorkflowAction::MARK_PAYMENT_ELIGIBLE,  WorkflowState::PAYMENT_ELIGIBLE,             WorkflowTransitionDefinition::RUNTIME_BLOCKED_BY_OD, ['OD-34']),
            // Downstream (structural — depends on OD-34 resolution)
            new WorkflowTransitionDefinition($v, WorkflowState::PAYMENT_ELIGIBLE,      WorkflowAction::RECORD_PAYMENT_CONFIRMED, WorkflowState::PAYMENT_CONFIRMED,            WorkflowTransitionDefinition::RUNTIME_ALLOWED),
            new WorkflowTransitionDefinition($v, WorkflowState::PAYMENT_CONFIRMED,     WorkflowAction::MARK_CERTIFICATE_ELIGIBLE, WorkflowState::CERTIFICATE_ELIGIBLE,        WorkflowTransitionDefinition::RUNTIME_ALLOWED),
            new WorkflowTransitionDefinition($v, WorkflowState::CERTIFICATE_ELIGIBLE,  WorkflowAction::COMPLETE_APPLICATION,     WorkflowState::COMPLETED,                    WorkflowTransitionDefinition::RUNTIME_ALLOWED),
            // Return + reject side paths (structural)
            new WorkflowTransitionDefinition($v, WorkflowState::OFFICES_DEPT_REVIEW,   WorkflowAction::OFFICES_DEPT_RETURN,      WorkflowState::RETURNED_TO_APPLICANT,        WorkflowTransitionDefinition::RUNTIME_ALLOWED),
            new WorkflowTransitionDefinition($v, WorkflowState::FIRST_TECHNICAL_REVIEW, WorkflowAction::FIRST_REVIEW_REQUEST_CORRECTION, WorkflowState::RETURNED_TO_APPLICANT, WorkflowTransitionDefinition::RUNTIME_ALLOWED),
            new WorkflowTransitionDefinition($v, WorkflowState::SECOND_TECHNICAL_REVIEW, WorkflowAction::SECOND_REVIEW_REQUEST_CORRECTION, WorkflowState::RETURNED_TO_APPLICANT, WorkflowTransitionDefinition::RUNTIME_ALLOWED),
            new WorkflowTransitionDefinition($v, WorkflowState::OFFICES_DEPT_REVIEW,   WorkflowAction::REJECT_APPLICATION,       WorkflowState::REJECTED,                     WorkflowTransitionDefinition::RUNTIME_ALLOWED),
            new WorkflowTransitionDefinition($v, WorkflowState::FIRST_TECHNICAL_REVIEW, WorkflowAction::REJECT_APPLICATION,      WorkflowState::REJECTED,                     WorkflowTransitionDefinition::RUNTIME_ALLOWED),
        ];
    }
}
