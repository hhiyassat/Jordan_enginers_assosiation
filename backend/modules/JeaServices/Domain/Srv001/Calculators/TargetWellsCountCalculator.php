<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Calculators;

use Modules\JeaServices\Domain\Srv001\Contracts\Srv001WellsCountRule;
use Modules\JeaServices\Governance\ServiceCalculationPolicy;
use Modules\JeaServices\Governance\ServiceCalculationResult;

/**
 * TD-01A · Target-domain wells-count calculator.
 *
 * Depends on the domain port Srv001WellsCountRule only. Adapter
 * (LegacyBridgeWellsCountRule) lives outside Domain per JDG-TD01A-02.
 */
final class TargetWellsCountCalculator implements ServiceCalculationPolicy
{
    public const RULE_IDENTIFIER = 'SRV001_WELLS_COUNT';

    public const STATUS_CLASSIFICATION = 'TARGET_DOMAIN_PROVISIONAL';

    public function __construct(
        private readonly Srv001WellsCountRule $rule,
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
        $area    = (float) ($inputs['floor_area'] ?? 0.0);
        $outputs = $this->rule->compute($area);

        return new ServiceCalculationResult(
            ruleVersionId:      $this->rule->ruleVersionId(),
            inputs:             ['floor_area' => $area],
            outputs:            $outputs,
            intermediateValues: [
                'target_domain_classification' => self::STATUS_CLASSIFICATION,
                'rule_source'                  => $this->rule::class,
                'br_calc_01_note' => 'legacy input semantics use `floor_area`; SRS BR-002 mandates largest-floor-area — deferred per RES-TD00-06.',
            ],
            openDecisions:      $this->rule->openDecisions(),
        );
    }
}
