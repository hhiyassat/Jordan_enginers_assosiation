<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Srv001;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\JeaServices\Database\Seeders\Srv001RulesSeeder;
use Modules\JeaServices\Domain\Srv001\Contracts\Srv001CalculationOutcome;
use Modules\JeaServices\Domain\Srv001\Contracts\Srv001ExplorationStatus;
use Modules\JeaServices\Domain\Srv001\Srv001CalculatorOutcomeClassifier;
use Modules\JeaServices\Governance\ServiceCalculationResult;
use Modules\JeaServices\Models\RuleDefinition;
use Modules\JeaServices\Models\RuleVersion;
use Tests\TestCase;

/**
 * TD-04 · Classifier decision-table tests.
 *
 * The classifier priority order (see class docblock) is exercised
 * one branch per test so a regression is easy to locate.
 */
class Srv001CalculatorOutcomeClassifierTest extends TestCase
{
    use RefreshDatabase;

    private Srv001CalculatorOutcomeClassifier $classifier;
    private int $approvedRuleVersionId;
    private int $provisionalRuleVersionId;
    private int $rejectedRuleVersionId;

    protected function setUp(): void
    {
        parent::setUp();
        (new Srv001RulesSeeder())->run();
        $this->classifier = new Srv001CalculatorOutcomeClassifier();

        // Seeder classifications (verified above): matrix APPROVED,
        // wells PROVISIONAL, netDepth PROVISIONAL. Pick a REJECTED
        // rule by inventing an additional test-scoped RuleVersion.
        $matrixDef  = RuleDefinition::where('rule_identifier', 'SRV001_EXPLORATION_MATRIX')->firstOrFail();
        $wellsDef   = RuleDefinition::where('rule_identifier', 'SRV001_WELLS_COUNT')->firstOrFail();
        $rejectedDef = RuleDefinition::create([
            'rule_identifier' => 'SRV001_TEST_REJECTED',
            'display_name'    => 'test-only rejected rule',
            'description'     => 'test fixture — never used in production seeders',
        ]);

        $this->approvedRuleVersionId    = (int) $matrixDef->currentEffectiveVersion()?->id;
        $this->provisionalRuleVersionId = (int) $wellsDef->currentEffectiveVersion()?->id;
        $this->rejectedRuleVersionId    = (int) RuleVersion::create([
            'rule_definition_id'       => $rejectedDef->id,
            'version_identifier'       => 'test-rejected-v0',
            'implementation_identity'  => 'test-only',
            'source_reference'         => 'test-only',
            'business_approval_status' => RuleVersion::STATUS_REJECTED,
        ])->id;
    }

    public function test_conflict_output_classifies_as_CONFLICTED(): void
    {
        $r = $this->classifier->classify(new ServiceCalculationResult(
            ruleVersionId: $this->approvedRuleVersionId,
            inputs:        ['floor_count' => 5, 'floor_area' => 900],
            outputs:       ['status' => 'CONFLICTED', 'error' => 'floor_count negative'],
            intermediateValues: ['rule_source' => 'TargetExplorationRequirementMatrixCalculator'],
        ));
        $this->assertSame(Srv001CalculationOutcome::CONFLICTED, $r->outcome);
        $this->assertFalse($r->isBinding());
    }

    public function test_missing_matrix_input_classifies_as_INSUFFICIENT_INPUT(): void
    {
        $r = $this->classifier->classify(new ServiceCalculationResult(
            ruleVersionId: $this->approvedRuleVersionId,
            inputs:        ['floor_count' => 5], // floor_area missing
            outputs:       ['status' => 'CALCULATED'],
            intermediateValues: ['rule_source' => 'TargetExplorationRequirementMatrixCalculator'],
        ));
        $this->assertSame(Srv001CalculationOutcome::INSUFFICIENT_INPUT, $r->outcome);
        $this->assertContains('floor_area', $r->classificationEvidence['missing_input_keys']);
    }

    public function test_not_applicable_output_classifies_as_NOT_APPLICABLE(): void
    {
        $r = $this->classifier->classify(new ServiceCalculationResult(
            ruleVersionId: $this->approvedRuleVersionId,
            inputs:        ['floor_count' => 5, 'floor_area' => 900],
            outputs:       ['status' => 'NOT_APPLICABLE'],
            intermediateValues: ['rule_source' => 'TargetExplorationRequirementMatrixCalculator'],
        ));
        $this->assertSame(Srv001CalculationOutcome::NOT_APPLICABLE, $r->outcome);
    }

    public function test_special_study_required_classifies_as_MANUAL_REVIEW(): void
    {
        $r = $this->classifier->classify(new ServiceCalculationResult(
            ruleVersionId: $this->approvedRuleVersionId,
            inputs:        ['floor_count' => 10, 'floor_area' => 900],
            outputs:       ['status' => Srv001ExplorationStatus::SPECIAL_STUDY_REQUIRED, 'reason' => 'floors > 9'],
            intermediateValues: ['rule_source' => 'TargetExplorationRequirementMatrixCalculator'],
        ));
        $this->assertSame(Srv001CalculationOutcome::MANUAL_REVIEW, $r->outcome);
    }

    public function test_rejected_rule_version_classifies_as_BLOCKED(): void
    {
        $r = $this->classifier->classify(new ServiceCalculationResult(
            ruleVersionId: $this->rejectedRuleVersionId,
            inputs:        ['floor_count' => 5, 'floor_area' => 900],
            outputs:       ['status' => 'CALCULATED', 'minimum_exploration_point_count' => 5],
            intermediateValues: ['rule_source' => 'TargetExplorationRequirementMatrixCalculator'],
        ));
        $this->assertSame(Srv001CalculationOutcome::BLOCKED, $r->outcome);
    }

    public function test_provisional_rule_version_classifies_as_SIMULATION_ONLY(): void
    {
        $r = $this->classifier->classify(new ServiceCalculationResult(
            ruleVersionId: $this->provisionalRuleVersionId,
            inputs:        ['floor_area' => 900],
            outputs:       ['status' => 'CALCULATED', 'wells' => 3],
            intermediateValues: ['rule_source' => 'TargetWellsCountCalculator'],
        ));
        $this->assertSame(Srv001CalculationOutcome::SIMULATION_ONLY, $r->outcome);
        $this->assertFalse($r->isBinding(),
            'PROVISIONAL rule outputs must never be treated as authoritative');
    }

    public function test_approved_rule_with_complete_inputs_and_calculated_status_classifies_as_CALCULATED(): void
    {
        $r = $this->classifier->classify(new ServiceCalculationResult(
            ruleVersionId: $this->approvedRuleVersionId,
            inputs:        ['floor_count' => 5, 'floor_area' => 900],
            outputs:       ['status' => 'CALCULATED', 'minimum_exploration_point_count' => 5],
            intermediateValues: ['rule_source' => 'TargetExplorationRequirementMatrixCalculator'],
        ));
        $this->assertSame(Srv001CalculationOutcome::CALCULATED, $r->outcome);
        $this->assertTrue($r->isBinding());
    }

    public function test_synthetic_result_without_rule_version_classifies_as_SIMULATION_ONLY(): void
    {
        // ruleVersionId=0 signals "no DB row" — classifier stays safe
        // and treats it as SIMULATION_ONLY rather than assuming APPROVED.
        $r = $this->classifier->classify(new ServiceCalculationResult(
            ruleVersionId: 0,
            inputs:        ['floor_count' => 5, 'floor_area' => 900],
            outputs:       ['status' => 'CALCULATED'],
            intermediateValues: ['rule_source' => 'TargetExplorationRequirementMatrixCalculator'],
        ));
        $this->assertSame(Srv001CalculationOutcome::SIMULATION_ONLY, $r->outcome);
    }
}
