<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Calculators;

use Modules\JeaServices\Governance\ServiceCalculationPolicy;
use Modules\JeaServices\Governance\ServiceCalculationResult;
use Modules\JeaServices\Governance\Srv001\LegacyWellsCountCalculator;

/**
 * TD-01 · Target-domain wells-count calculator.
 *
 * Delegates to LegacyWellsCountCalculator (which itself wraps the
 * static engine class). Preserves numeric outputs. Adds
 * TARGET_DOMAIN_PROVISIONAL classification + expiry-condition
 * open decisions.
 *
 * BUSINESS status: PROVISIONAL — source is meeting minutes
 * 2026-07-26 §X, not JEA-signed. Same as legacy.
 * Legacy source-reference classification carried through
 * unchanged; see RuleVersion table (SG-04) for the persistent
 * BUSINESS_APPROVAL_STATUS.
 */
final class TargetWellsCountCalculator implements ServiceCalculationPolicy
{
    public const RULE_IDENTIFIER = 'SRV001_WELLS_COUNT';

    public const STATUS_CLASSIFICATION = 'TARGET_DOMAIN_PROVISIONAL';

    public function __construct(
        private readonly LegacyWellsCountCalculator $legacy,
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
                // BR-CALC-01 note: legacy uses total floor_area; SRS
                // v1.2 §2.1 BR-002 mandates LARGEST floor area. When
                // the input model supports per-floor collection
                // (RES-TD00-06), swap here without touching legacy.
                'br_calc_01_note' => 'legacy uses `floor_area`; SRS BR-002 mandates largest-floor-area. Per-floor input model deferred (RES-TD00-06).',
            ],
        );

        $openDecisions = array_merge(
            $legacyResult->openDecisions ?? [],
            [
                'TARGET_DOMAIN_PROVISIONAL — inherits legacy PROVISIONAL status (meeting-minute source, not JEA-signed)',
                'OD-22 — >3000m² transition point + credit rule (SRS v1.2 §4.1)',
                'OD-20 — ≥15-floor 1-well-per-200m² priority (SRS v1.2 §4.1)',
                'OD-07 — 801-1000m² band value dispute',
                'BR-CALC-01 — LARGEST-floor input semantics deferred to RES-TD00-06',
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
