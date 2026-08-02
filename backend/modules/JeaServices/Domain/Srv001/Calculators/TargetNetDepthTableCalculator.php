<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Calculators;

use Modules\JeaServices\Domain\Srv001\Contracts\Srv001NetDepthRule;
use Modules\JeaServices\Governance\ServiceCalculationPolicy;
use Modules\JeaServices\Governance\ServiceCalculationResult;

/**
 * TD-01A · Target-domain net-depth calculator.
 *
 * Depends on domain port only. Adapter outside Domain per JDG-TD01A-02.
 */
final class TargetNetDepthTableCalculator implements ServiceCalculationPolicy
{
    public const RULE_IDENTIFIER = 'SRV001_NET_DEPTH';

    public const STATUS_CLASSIFICATION = 'TARGET_DOMAIN_PROVISIONAL';

    public function __construct(
        private readonly Srv001NetDepthRule $rule,
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
        $floors  = (int) ($inputs['floor_count'] ?? 0);
        $outputs = $this->rule->compute($floors);

        return new ServiceCalculationResult(
            ruleVersionId:      $this->rule->ruleVersionId(),
            inputs:             ['floor_count' => $floors],
            outputs:            $outputs,
            intermediateValues: [
                'target_domain_classification' => self::STATUS_CLASSIFICATION,
                'rule_source'                  => $this->rule::class,
                'srs_v12_ref'                  => 'SRS v1.2 §4.3 depth-table rows for floors 10-14 explicit + 15-34 aggregated ranges available but NOT integrated (BLOCKED — selection rule for third vs two-thirds unresolved).',
            ],
            openDecisions:      $this->rule->openDecisions(),
        );
    }
}
