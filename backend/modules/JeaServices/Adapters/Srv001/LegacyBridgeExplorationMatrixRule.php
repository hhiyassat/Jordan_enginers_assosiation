<?php

declare(strict_types=1);

namespace Modules\JeaServices\Adapters\Srv001;

use Modules\JeaServices\Domain\Srv001\Contracts\Srv001ExplorationMatrixRule;
use Modules\JeaServices\Governance\Srv001\LegacyExplorationRequirementMatrixCalculator;

/**
 * TD-01A · Compatibility adapter — implements the domain rule port
 * using the legacy pilot calculator.
 *
 * Lives OUTSIDE `Modules\JeaServices\Domain\` so the Domain layer
 * does not depend on Legacy* directly (JDG-TD01A-02).
 *
 * STATUS: TARGET_DOMAIN_PROVISIONAL. Delegates to
 * LegacyExplorationRequirementMatrixCalculator to preserve numeric
 * outputs. Retired when an approved target rule replaces it.
 */
final class LegacyBridgeExplorationMatrixRule implements Srv001ExplorationMatrixRule
{
    public function __construct(
        private readonly LegacyExplorationRequirementMatrixCalculator $legacy,
    ) {
    }

    public function compute(int $floorCount, float $floorArea): array
    {
        $result = $this->legacy->compute([
            'floor_count' => $floorCount,
            'floor_area'  => $floorArea,
        ]);
        return $result->outputs;
    }

    public function ruleVersionId(): int
    {
        // Legacy calculator resolves rule_version_id on every compute call
        // via a DB query; we invoke a tiny throwaway compute to obtain it.
        // Cost is negligible and the call is idempotent.
        return $this->legacy->compute(['floor_count' => 3, 'floor_area' => 100.0])->ruleVersionId;
    }

    public function openDecisions(): array
    {
        return [
            'TARGET_DOMAIN_PROVISIONAL — legacy bridge; awaiting OD-Closure for SRS v1.2 §4.1 rows before promotion to BUSINESS_APPROVED',
            'CONF-01 / OD-07 — 801-1000 band value remains disputed',
            'CONF-05 / OD-20 — ≥15-floor priority rule not yet implemented',
            'ADAPTER — Modules\\JeaServices\\Adapters\\Srv001\\LegacyBridgeExplorationMatrixRule (outside Domain layer per JDG-TD01A-02)',
        ];
    }
}
