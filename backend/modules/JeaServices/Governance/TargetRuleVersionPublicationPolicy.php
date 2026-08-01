<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

use Modules\JeaServices\Domain\Srv001\Contracts\Srv001CalculationOutcome;
use Modules\JeaServices\Models\RuleVersion;

/**
 * TD-04 · Guard that prevents promotion of a `RuleVersion` to APPROVED
 * status when its target-domain outcome is not `CALCULATED`.
 *
 * The typed outcome for a rule can only be `CALCULATED` when:
 *   • rule_version.business_approval_status = APPROVED
 *   • rule inputs are complete
 *   • rule outputs are not CONFLICTED / NOT_APPLICABLE
 *   • rule does not require manual review
 *
 * This guard is invoked BEFORE a publisher (SG-03 ServiceVersionPublisher
 * or an equivalent RuleVersion publisher) commits a rule-version status
 * change from PROVISIONAL/PENDING to APPROVED. If the check fails, the
 * publisher must not persist the promotion.
 *
 * The guard does NOT perform the promotion itself, does NOT open a
 * transaction, and does NOT mutate any row. It is a pure decision
 * function returning `PublicationDecision::allow()` or
 * `PublicationDecision::block($reasons)`.
 *
 * INVARIANT: this guard IS NOT wired into any existing publisher today.
 * TD-04 registers it in the container so future publishers (or a
 * future TargetRuleVersionPublisher) can consume it. RuleVersion
 * promotion remains a manual, human-approved workflow.
 */
final class TargetRuleVersionPublicationPolicy
{
    /**
     * @param  string  $currentTypedOutcome  one of Srv001CalculationOutcome::*
     *   — the outcome the target-domain classifier produced for the
     *   rule's most recent representative invocation.
     * @param  string  $desiredStatus  the status the publisher wants to
     *   apply (typically RuleVersion::STATUS_APPROVED).
     */
    public function decide(
        RuleVersion $ruleVersion,
        string $currentTypedOutcome,
        string $desiredStatus,
    ): TargetRuleVersionPublicationDecision {
        $reasons = [];

        // Only APPROVED promotion is subject to the target-domain
        // outcome check. Any other status transition (approve to
        // reject, provisional to pending) is out of scope.
        if ($desiredStatus !== RuleVersion::STATUS_APPROVED) {
            return TargetRuleVersionPublicationDecision::allow(
                ['non_approved_transition_not_gated'],
            );
        }

        if (! Srv001CalculationOutcome::isValid($currentTypedOutcome)) {
            $reasons[] = 'UNKNOWN_TYPED_OUTCOME';
        } elseif (! in_array(
            $currentTypedOutcome,
            Srv001CalculationOutcome::bindingOutcomes(),
            true,
        )) {
            $reasons[] = 'NON_BINDING_OUTCOME_' . $currentTypedOutcome;
        }

        if ($ruleVersion->business_approval_status === RuleVersion::STATUS_APPROVED) {
            // Idempotent re-approve — allow (no-op from publisher's
            // perspective). This matches the SG-03 already-bound
            // semantics.
            return TargetRuleVersionPublicationDecision::allow(
                ['already_approved_idempotent'],
            );
        }

        return $reasons === []
            ? TargetRuleVersionPublicationDecision::allow(['binding_outcome_and_status_change_permitted'])
            : TargetRuleVersionPublicationDecision::block($reasons);
    }
}
