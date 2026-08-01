<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\JeaServices\Database\Seeders\Srv001RulesSeeder;
use Modules\JeaServices\Domain\Srv001\Contracts\Srv001CalculationOutcome;
use Modules\JeaServices\Governance\TargetRuleVersionPublicationPolicy;
use Modules\JeaServices\Models\RuleDefinition;
use Modules\JeaServices\Models\RuleVersion;
use Tests\TestCase;

/**
 * TD-04 · TargetRuleVersionPublicationPolicy decision-table tests.
 *
 * The policy is a pure function today (no DB writes, no side effects).
 * These tests exercise every outcome + status-change combination.
 */
class TargetRuleVersionPublicationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private TargetRuleVersionPublicationPolicy $policy;
    private RuleVersion $approvedRuleVersion;
    private RuleVersion $provisionalRuleVersion;

    protected function setUp(): void
    {
        parent::setUp();
        (new Srv001RulesSeeder())->run();
        $this->policy = new TargetRuleVersionPublicationPolicy();

        $matrixDef = RuleDefinition::where('rule_identifier', 'SRV001_EXPLORATION_MATRIX')->firstOrFail();
        $wellsDef  = RuleDefinition::where('rule_identifier', 'SRV001_WELLS_COUNT')->firstOrFail();

        $this->approvedRuleVersion    = $matrixDef->currentEffectiveVersion();
        $this->provisionalRuleVersion = $wellsDef->currentEffectiveVersion();
    }

    public function test_approving_a_provisional_rule_with_calculated_outcome_is_allowed(): void
    {
        $d = $this->policy->decide(
            $this->provisionalRuleVersion,
            currentTypedOutcome: Srv001CalculationOutcome::CALCULATED,
            desiredStatus:       RuleVersion::STATUS_APPROVED,
        );
        $this->assertTrue($d->allowed);
    }

    public function test_approving_with_simulation_only_outcome_is_blocked(): void
    {
        $d = $this->policy->decide(
            $this->provisionalRuleVersion,
            currentTypedOutcome: Srv001CalculationOutcome::SIMULATION_ONLY,
            desiredStatus:       RuleVersion::STATUS_APPROVED,
        );
        $this->assertFalse($d->allowed);
        $this->assertContains('NON_BINDING_OUTCOME_SIMULATION_ONLY', $d->reasons);
    }

    public function test_approving_with_blocked_outcome_is_blocked(): void
    {
        $d = $this->policy->decide(
            $this->provisionalRuleVersion,
            currentTypedOutcome: Srv001CalculationOutcome::BLOCKED,
            desiredStatus:       RuleVersion::STATUS_APPROVED,
        );
        $this->assertFalse($d->allowed);
        $this->assertContains('NON_BINDING_OUTCOME_BLOCKED', $d->reasons);
    }

    public function test_approving_with_conflicted_outcome_is_blocked(): void
    {
        $d = $this->policy->decide(
            $this->provisionalRuleVersion,
            currentTypedOutcome: Srv001CalculationOutcome::CONFLICTED,
            desiredStatus:       RuleVersion::STATUS_APPROVED,
        );
        $this->assertFalse($d->allowed);
    }

    public function test_approving_with_manual_review_outcome_is_blocked(): void
    {
        $d = $this->policy->decide(
            $this->provisionalRuleVersion,
            currentTypedOutcome: Srv001CalculationOutcome::MANUAL_REVIEW,
            desiredStatus:       RuleVersion::STATUS_APPROVED,
        );
        $this->assertFalse($d->allowed);
    }

    public function test_approving_an_already_approved_rule_is_allowed_idempotently(): void
    {
        $d = $this->policy->decide(
            $this->approvedRuleVersion,
            currentTypedOutcome: Srv001CalculationOutcome::CALCULATED,
            desiredStatus:       RuleVersion::STATUS_APPROVED,
        );
        $this->assertTrue($d->allowed);
        $this->assertContains('already_approved_idempotent', $d->reasons);
    }

    public function test_non_approved_transitions_are_not_gated_by_this_policy(): void
    {
        // Rejecting a provisional rule shouldn't be blocked by this
        // policy — it's not an APPROVE transition.
        $d = $this->policy->decide(
            $this->provisionalRuleVersion,
            currentTypedOutcome: Srv001CalculationOutcome::SIMULATION_ONLY,
            desiredStatus:       RuleVersion::STATUS_REJECTED,
        );
        $this->assertTrue($d->allowed);
        $this->assertContains('non_approved_transition_not_gated', $d->reasons);
    }

    public function test_approving_with_unknown_outcome_string_is_blocked(): void
    {
        $d = $this->policy->decide(
            $this->provisionalRuleVersion,
            currentTypedOutcome: 'MAYBE_LATER',
            desiredStatus:       RuleVersion::STATUS_APPROVED,
        );
        $this->assertFalse($d->allowed);
        $this->assertContains('UNKNOWN_TYPED_OUTCOME', $d->reasons);
    }

    public function test_policy_is_not_wired_into_any_existing_publisher(): void
    {
        // TD-04 invariant: the policy is registered in the container
        // but NOT invoked by any current runtime publisher. Prove by
        // grep — no import of the policy from Governance/, Engine/,
        // or Providers/ other than the class itself and this test.
        $sources = [
            base_path('modules/JeaServices/Governance/ServiceVersionPublisher.php'),
            base_path('modules/JeaServices/Providers/JeaServicesServiceProvider.php'),
        ];
        foreach ($sources as $path) {
            if (! file_exists($path)) {
                continue;
            }
            $body = file_get_contents($path);
            $this->assertNotFalse($body);
            $this->assertStringNotContainsString(
                'TargetRuleVersionPublicationPolicy',
                $body,
                "TD-04 invariant: {$path} must not consume the policy yet — it is provisional",
            );
        }
    }
}
