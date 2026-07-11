<?php

namespace Tests\Unit\Engine;

use App\Engine\WorkflowEngine;
use App\Models\Application;
use PHPUnit\Framework\TestCase;

/**
 * WorkflowEngineTest
 *
 * §11.3 Criterion 2: Automated tests written alongside code.
 * Tests verify EDA B-5 (ALLOWED_TRANSITIONS enforcement).
 */
class WorkflowEngineTest extends TestCase
{
    /** @test */
    public function allowed_transitions_constant_is_complete(): void
    {
        $transitions = WorkflowEngine::ALLOWED_TRANSITIONS;

        // Every Application status must be a key
        $allStatuses = [
            Application::STATUS_DRAFT,
            Application::STATUS_SUBMITTED,
            Application::STATUS_UNDER_REVIEW,
            Application::STATUS_MODIFICATIONS_REQUESTED,
            Application::STATUS_APPROVED,
            Application::STATUS_REJECTED,
            Application::STATUS_CERTIFICATE_ISSUED,
        ];

        foreach ($allStatuses as $status) {
            $this->assertArrayHasKey(
                $status,
                $transitions,
                "ALLOWED_TRANSITIONS missing key: {$status}"
            );
        }
    }

    /** @test */
    public function terminal_statuses_have_no_outgoing_transitions(): void
    {
        $transitions = WorkflowEngine::ALLOWED_TRANSITIONS;

        foreach (Application::TERMINAL_STATUSES as $terminal) {
            $this->assertEmpty(
                $transitions[$terminal],
                "Terminal status '{$terminal}' should have no outgoing transitions."
            );
        }
    }

    /** @test */
    public function draft_can_only_transition_to_submitted(): void
    {
        $allowed = WorkflowEngine::ALLOWED_TRANSITIONS[Application::STATUS_DRAFT];

        $this->assertSame([Application::STATUS_SUBMITTED], $allowed);
    }

    /** @test */
    public function under_review_can_transition_to_all_decision_outcomes(): void
    {
        $allowed = WorkflowEngine::ALLOWED_TRANSITIONS[Application::STATUS_UNDER_REVIEW];

        $this->assertContains(Application::STATUS_APPROVED, $allowed);
        $this->assertContains(Application::STATUS_REJECTED, $allowed);
        $this->assertContains(Application::STATUS_MODIFICATIONS_REQUESTED, $allowed);
    }

    /** @test */
    public function modifications_requested_returns_to_submitted(): void
    {
        $allowed = WorkflowEngine::ALLOWED_TRANSITIONS[Application::STATUS_MODIFICATIONS_REQUESTED];

        $this->assertSame([Application::STATUS_SUBMITTED], $allowed);
    }

    /** @test */
    public function approved_can_only_issue_certificate(): void
    {
        $allowed = WorkflowEngine::ALLOWED_TRANSITIONS[Application::STATUS_APPROVED];

        $this->assertSame([Application::STATUS_CERTIFICATE_ISSUED], $allowed);
    }

    /** @test */
    public function no_transition_skips_states(): void
    {
        // draft cannot jump directly to approved or certificate_issued
        $draftTransitions = WorkflowEngine::ALLOWED_TRANSITIONS[Application::STATUS_DRAFT];

        $this->assertNotContains(Application::STATUS_APPROVED, $draftTransitions);
        $this->assertNotContains(Application::STATUS_CERTIFICATE_ISSUED, $draftTransitions);
        $this->assertNotContains(Application::STATUS_UNDER_REVIEW, $draftTransitions);
    }
}
