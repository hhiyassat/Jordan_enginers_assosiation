<?php

declare(strict_types=1);

namespace Modules\JeaServices\Adapters\Srv001;

use Modules\JeaServices\Domain\Srv001\Contracts\Srv001WellsCountRule;
use Modules\JeaServices\Governance\Srv001\LegacyWellsCountCalculator;

/**
 * TD-01A · Compatibility adapter — legacy wells-count via domain port.
 *
 * See JDG-TD01A-02. Legacy source is meeting-minute-only
 * (PROVISIONAL); adapter surfaces that + all associated OD blockers
 * so consumers of the Target* calculator see them in every snapshot.
 */
final class LegacyBridgeWellsCountRule implements Srv001WellsCountRule
{
    public function __construct(
        private readonly LegacyWellsCountCalculator $legacy,
    ) {
    }

    public function compute(float $floorArea): array
    {
        return $this->legacy->compute(['floor_area' => $floorArea])->outputs;
    }

    public function ruleVersionId(): int
    {
        return $this->legacy->compute(['floor_area' => 100.0])->ruleVersionId;
    }

    public function openDecisions(): array
    {
        return [
            'TARGET_DOMAIN_PROVISIONAL — inherits legacy PROVISIONAL status (meeting-minute source, not JEA-signed)',
            'OD-22 — >3000m² transition point + credit rule (SRS v1.2 §4.1)',
            'OD-20 — ≥15-floor 1-well-per-200m² priority (SRS v1.2 §4.1)',
            'OD-07 — 801-1000m² band value dispute',
            'BR-CALC-01 — LARGEST-floor input semantics deferred to RES-TD00-06',
            'ADAPTER — Modules\\JeaServices\\Adapters\\Srv001\\LegacyBridgeWellsCountRule (outside Domain layer per JDG-TD01A-02)',
        ];
    }
}
