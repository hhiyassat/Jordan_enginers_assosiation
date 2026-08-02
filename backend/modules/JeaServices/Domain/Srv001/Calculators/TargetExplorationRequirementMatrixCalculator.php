<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Calculators;

use Modules\JeaServices\Domain\Srv001\Contracts\Srv001ExplorationMatrixRule;
use Modules\JeaServices\Governance\ServiceCalculationPolicy;
use Modules\JeaServices\Governance\ServiceCalculationResult;

/**
 * TD-01A · Target-domain calculator for the exploration-requirement
 * matrix (SRS v1.2 §4.1 / كتاب التعليمات الفنية 2025 ص 230-231).
 *
 * BOUNDARY (JDG-TD01A-02): depends on the domain port
 * Srv001ExplorationMatrixRule only. Legacy* delegation, when needed,
 * flows in through an adapter outside the Domain namespace
 * (Modules\JeaServices\Adapters\Srv001\LegacyBridgeExplorationMatrixRule).
 *
 * STATUS: TARGET_DOMAIN_PROVISIONAL. Runtime path unchanged
 * (Srv001Guard still active). Publication BLOCKED until per-rule
 * OD-Closure attached.
 */
final class TargetExplorationRequirementMatrixCalculator implements ServiceCalculationPolicy
{
    public const RULE_IDENTIFIER = 'SRV001_EXPLORATION_MATRIX';

    public const STATUS_CLASSIFICATION = 'TARGET_DOMAIN_PROVISIONAL';

    public function __construct(
        private readonly Srv001ExplorationMatrixRule $rule,
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
        $floors = (int) ($inputs['floor_count'] ?? 0);
        $area   = (float) ($inputs['floor_area'] ?? 0.0);

        $outputs = $this->rule->compute($floors, $area);

        return new ServiceCalculationResult(
            ruleVersionId:      $this->rule->ruleVersionId(),
            inputs:             ['floor_count' => $floors, 'floor_area' => $area],
            outputs:            $outputs,
            intermediateValues: [
                'target_domain_classification' => self::STATUS_CLASSIFICATION,
                'rule_source'                  => $this->rule::class,
            ],
            openDecisions:      $this->rule->openDecisions(),
        );
    }
}
