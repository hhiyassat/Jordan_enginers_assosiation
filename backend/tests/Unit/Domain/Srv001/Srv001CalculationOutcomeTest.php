<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Srv001;

use InvalidArgumentException;
use Modules\JeaServices\Domain\Srv001\Contracts\Srv001CalculationOutcome;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001TypedCalculationResult;
use Modules\JeaServices\Governance\ServiceCalculationResult;
use PHPUnit\Framework\TestCase;

/**
 * TD-04 · Unit tests for the outcome enum + typed result wrapper.
 *
 * These are pure Domain-layer tests — no DB, no HTTP, no container.
 */
class Srv001CalculationOutcomeTest extends TestCase
{
    public function test_enum_exposes_the_seven_documented_states(): void
    {
        $this->assertSame(
            [
                'CALCULATED',
                'BLOCKED',
                'CONFLICTED',
                'INSUFFICIENT_INPUT',
                'MANUAL_REVIEW',
                'SIMULATION_ONLY',
                'NOT_APPLICABLE',
            ],
            Srv001CalculationOutcome::all(),
        );
    }

    public function test_isValid_accepts_known_states_and_rejects_unknowns(): void
    {
        foreach (Srv001CalculationOutcome::all() as $value) {
            $this->assertTrue(Srv001CalculationOutcome::isValid($value));
        }
        $this->assertFalse(Srv001CalculationOutcome::isValid('IN_FLIGHT'));
        $this->assertFalse(Srv001CalculationOutcome::isValid(''));
        $this->assertFalse(Srv001CalculationOutcome::isValid('calculated'));
    }

    public function test_binding_outcomes_are_only_CALCULATED(): void
    {
        // Any change here needs a signed decision — every downstream
        // persistence gate depends on this list.
        $this->assertSame(['CALCULATED'], Srv001CalculationOutcome::bindingOutcomes());
    }

    public function test_non_binding_outcomes_cover_every_other_state(): void
    {
        $union = array_merge(
            Srv001CalculationOutcome::bindingOutcomes(),
            Srv001CalculationOutcome::nonBindingOutcomes(),
        );
        sort($union);
        $all = Srv001CalculationOutcome::all();
        sort($all);
        $this->assertSame($all, $union, 'binding + non-binding must partition the enum');
    }

    public function test_typed_result_construction_rejects_unknown_outcome(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Srv001TypedCalculationResult(
            outcome:                'NOT_A_STATE',
            numeric:                $this->stubNumeric(),
            classifierReason:       'test',
        );
    }

    public function test_typed_result_isBinding_true_only_when_outcome_is_CALCULATED(): void
    {
        foreach (Srv001CalculationOutcome::all() as $outcome) {
            $r = new Srv001TypedCalculationResult(
                outcome:          $outcome,
                numeric:          $this->stubNumeric(),
                classifierReason: 'test',
            );
            if ($outcome === Srv001CalculationOutcome::CALCULATED) {
                $this->assertTrue($r->isBinding(), "$outcome must be binding");
                $this->assertFalse($r->isNonBinding());
            } else {
                $this->assertFalse($r->isBinding(), "$outcome must NOT be binding");
                $this->assertTrue($r->isNonBinding(), "$outcome must be non-binding");
            }
        }
    }

    public function test_typed_result_snapshot_extension_carries_outcome_reason_and_evidence(): void
    {
        $r = new Srv001TypedCalculationResult(
            outcome:                Srv001CalculationOutcome::SIMULATION_ONLY,
            numeric:                $this->stubNumeric(),
            classifierReason:       'draft rule',
            classificationEvidence: ['rule_version_status' => 'PROVISIONAL'],
        );

        $ext = $r->toSnapshotIntermediateValuesExtension();

        $this->assertSame('SIMULATION_ONLY', $ext['srv001_calculation_outcome']);
        $this->assertSame('draft rule',      $ext['srv001_calculation_outcome_reason']);
        $this->assertSame(
            ['rule_version_status' => 'PROVISIONAL'],
            $ext['srv001_calculation_outcome_evidence'],
        );
    }

    private function stubNumeric(): ServiceCalculationResult
    {
        return new ServiceCalculationResult(
            ruleVersionId: 0,
            inputs:        [],
            outputs:       ['status' => 'CALCULATED'],
        );
    }
}
