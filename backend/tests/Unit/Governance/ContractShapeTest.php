<?php

declare(strict_types=1);

namespace Tests\Unit\Governance;

use Modules\JeaServices\Governance\ServiceCalculationPolicy;
use Modules\JeaServices\Governance\ServiceCalculationResult;
use Modules\JeaServices\Governance\ServiceSubmissionDecision;
use Modules\JeaServices\Governance\ServiceSubmissionPolicy;
use Modules\JeaServices\Models\Application;
use Tests\TestCase;

/**
 * SG-05 · Verifies that the ServiceSubmissionPolicy / ServiceCalculationPolicy
 * contracts have a satisfiable shape (a trivial passing implementation
 * demonstrates the contract can be met).
 *
 * SG-06 will add LegacySrv001SubmissionPolicy as the real consumer.
 */
class ContractShapeTest extends TestCase
{
    public function test_submission_decision_accepted_is_immutable_and_typed(): void
    {
        $decision = ServiceSubmissionDecision::accepted(
            derivedValues: ['floor_count' => 5],
            warnings: ['fee is provisional'],
            calculationSnapshots: [
                ['rule_version_id' => 1, 'inputs' => ['x' => 1], 'outputs' => ['y' => 2]],
            ],
        );

        $this->assertTrue($decision->accepted);
        $this->assertSame(['floor_count' => 5], $decision->derivedValues);
        $this->assertSame(['fee is provisional'], $decision->warnings);
        $this->assertCount(1, $decision->calculationSnapshots);
    }

    public function test_submission_decision_rejected_carries_errors(): void
    {
        $decision = ServiceSubmissionDecision::rejected([
            'area_m2' => ['Area must be positive'],
        ]);

        $this->assertFalse($decision->accepted);
        $this->assertSame(['area_m2' => ['Area must be positive']], $decision->errors);
        $this->assertSame([], $decision->derivedValues);
    }

    public function test_submission_policy_contract_is_satisfiable(): void
    {
        $policy = new class implements ServiceSubmissionPolicy {
            public function serviceCode(): string
            {
                return 'TEST-CONTRACT-1';
            }

            public function evaluate(Application $application): ServiceSubmissionDecision
            {
                return ServiceSubmissionDecision::accepted(derivedValues: ['ok' => true]);
            }
        };

        $this->assertSame('TEST-CONTRACT-1', $policy->serviceCode());
        $decision = $policy->evaluate(new Application());
        $this->assertTrue($decision->accepted);
        $this->assertSame(['ok' => true], $decision->derivedValues);
    }

    public function test_calculation_result_snapshot_payload_shape(): void
    {
        $result = new ServiceCalculationResult(
            ruleVersionId: 7,
            inputs: ['area_m2' => 500],
            outputs: ['wells_count' => 3],
            intermediateValues: ['band' => '201-600'],
            warnings: ['source is provisional'],
        );

        $payload = $result->toSnapshotPayload();

        $this->assertSame(7, $payload['rule_version_id']);
        $this->assertSame(['area_m2' => 500], $payload['inputs']);
        $this->assertSame(['wells_count' => 3], $payload['outputs']);
        $this->assertSame(['band' => '201-600'], $payload['intermediate_values']);
        $this->assertSame(['source is provisional'], $payload['warnings']);
        $this->assertArrayNotHasKey('open_decisions', $payload); // not provided
    }

    public function test_calculation_policy_contract_is_satisfiable(): void
    {
        $policy = new class implements ServiceCalculationPolicy {
            public function ruleIdentifier(): string
            {
                return 'TEST_CALC_1';
            }

            /** @param array<string, mixed> $inputs */
            public function compute(array $inputs): ServiceCalculationResult
            {
                return new ServiceCalculationResult(
                    ruleVersionId: 1,
                    inputs: $inputs,
                    outputs: ['echoed' => $inputs['x'] ?? null],
                );
            }
        };

        $result = $policy->compute(['x' => 42]);
        $this->assertSame(1, $result->ruleVersionId);
        $this->assertSame(['echoed' => 42], $result->outputs);
    }
}
