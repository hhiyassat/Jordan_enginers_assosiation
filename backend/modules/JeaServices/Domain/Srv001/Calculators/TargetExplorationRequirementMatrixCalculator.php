<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Calculators;

use Modules\JeaServices\Governance\ServiceCalculationPolicy;
use Modules\JeaServices\Governance\ServiceCalculationResult;
use Modules\JeaServices\Governance\Srv001\LegacyExplorationRequirementMatrixCalculator;

/**
 * TD-01 · Target-domain calculator for the exploration-requirement
 * matrix (SRS v1.2 §4.1 / كتاب التعليمات الفنية 2025 ص 230-231).
 *
 * SCOPE: parallel-implementation skeleton per JDG-TD00-02. Delegates
 * to the Legacy* calculator so numeric outputs remain identical.
 * NOT wired to runtime. Consumers are TargetSrv001SubmissionPolicy
 * + unit tests.
 *
 * STATUS: TARGET_DOMAIN_PROVISIONAL. Advances beyond delegation only
 * once (a) the SRS §4.1 rows are BUSINESS_APPROVED via OD-Closure,
 * (b) the CONFLICTED 801-1000 band is resolved by OD-07, (c) the
 * ≥15-floor priority rule is resolved by OD-20.
 */
final class TargetExplorationRequirementMatrixCalculator implements ServiceCalculationPolicy
{
    public const RULE_IDENTIFIER = 'SRV001_EXPLORATION_MATRIX';

    public const STATUS_CLASSIFICATION = 'TARGET_DOMAIN_PROVISIONAL';

    public function __construct(
        private readonly LegacyExplorationRequirementMatrixCalculator $legacy,
    ) {
    }

    public function ruleIdentifier(): string
    {
        return self::RULE_IDENTIFIER;
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public function compute(array $inputs): ServiceCalculationResult
    {
        $legacyResult = $this->legacy->compute($inputs);

        // Preserve numeric outputs verbatim. Add target-domain markers so
        // downstream snapshot consumers can identify the source and warn
        // about publication status.
        $intermediate = array_merge(
            $legacyResult->intermediateValues ?? [],
            [
                'target_domain_classification' => self::STATUS_CLASSIFICATION,
                'delegated_to_legacy'          => true,
            ],
        );

        $openDecisions = array_merge(
            $legacyResult->openDecisions ?? [],
            [
                'TARGET_DOMAIN_PROVISIONAL — awaiting OD-Closure for SRS v1.2 §4.1 rows before promotion to BUSINESS_APPROVED',
                'CONF-01 / OD-07 — 801-1000 band value remains disputed',
                'CONF-05 / OD-20 — ≥15-floor priority rule not yet implemented',
            ],
        );

        return new ServiceCalculationResult(
            ruleVersionId:       $legacyResult->ruleVersionId,
            inputs:              $legacyResult->inputs,
            outputs:             $legacyResult->outputs,
            intermediateValues:  $intermediate,
            warnings:            $legacyResult->warnings,
            openDecisions:       $openDecisions,
        );
    }
}
