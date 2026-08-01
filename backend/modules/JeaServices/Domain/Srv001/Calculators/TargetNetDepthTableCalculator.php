<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Calculators;

use Modules\JeaServices\Governance\ServiceCalculationPolicy;
use Modules\JeaServices\Governance\ServiceCalculationResult;
use Modules\JeaServices\Governance\Srv001\LegacyNetDepthTableCalculator;

/**
 * TD-01 · Target-domain net-depth calculator.
 *
 * Delegates to LegacyNetDepthTableCalculator. Numeric outputs
 * unchanged. Legacy covers floors 3-9; SRS v1.2 §4.3 adds explicit
 * rows for floors 10-14 and aggregated ranges 15-34 — extension is
 * documented as an open decision but NOT implemented here (blocked
 * on JDG-TD00-02 rules: no legacy-numeric-output changes).
 *
 * BR-CALC-09 remains PROVISIONAL — meeting-minute source with the
 * acknowledged invariant `third + two_thirds ≠ total` unresolved.
 */
final class TargetNetDepthTableCalculator implements ServiceCalculationPolicy
{
    public const RULE_IDENTIFIER = 'SRV001_NET_DEPTH';

    public const STATUS_CLASSIFICATION = 'TARGET_DOMAIN_PROVISIONAL';

    public function __construct(
        private readonly LegacyNetDepthTableCalculator $legacy,
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

        $intermediate = array_merge(
            $legacyResult->intermediateValues ?? [],
            [
                'target_domain_classification' => self::STATUS_CLASSIFICATION,
                'delegated_to_legacy'          => true,
                'srs_v12_ref'                  => 'SRS v1.2 §4.3 depth-table rows for floors 10-14 explicit + 15-34 aggregated ranges available but NOT integrated (BLOCKED — selection rule for third vs two-thirds unresolved).',
            ],
        );

        $openDecisions = array_merge(
            $legacyResult->openDecisions ?? [],
            [
                'TARGET_DOMAIN_PROVISIONAL — inherits legacy PROVISIONAL status',
                'SRS v1.2 §4.3 explicit note: selection rule (third vs two_thirds), combination across differing-area floors, and definition of final "net depth" are all BLOCKED pending signed examples',
                'Invariant "third + two_thirds ≠ total" unresolved',
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
