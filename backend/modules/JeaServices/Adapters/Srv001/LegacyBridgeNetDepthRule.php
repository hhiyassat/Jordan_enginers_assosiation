<?php

declare(strict_types=1);

namespace Modules\JeaServices\Adapters\Srv001;

use Modules\JeaServices\Domain\Srv001\Contracts\Srv001NetDepthRule;
use Modules\JeaServices\Governance\Srv001\LegacyNetDepthTableCalculator;

/**
 * TD-01A · Compatibility adapter — legacy net-depth via domain port.
 *
 * See JDG-TD01A-02. Legacy covers floors 3-9. SRS v1.2 §4.3 supplies
 * explicit rows for floors 10-14 and aggregated ranges 15-34 — NOT
 * integrated here (BLOCKED on selection rule; JDG-TD00-02 forbids
 * legacy-numeric-output changes).
 */
final class LegacyBridgeNetDepthRule implements Srv001NetDepthRule
{
    public function __construct(
        private readonly LegacyNetDepthTableCalculator $legacy,
    ) {
    }

    public function compute(int $floorCount): array
    {
        return $this->legacy->compute(['floor_count' => $floorCount])->outputs;
    }

    public function ruleVersionId(): int
    {
        return $this->legacy->compute(['floor_count' => 3])->ruleVersionId;
    }

    public function openDecisions(): array
    {
        return [
            'TARGET_DOMAIN_PROVISIONAL — inherits legacy PROVISIONAL status',
            'SRS v1.2 §4.3 explicit note: selection rule (third vs two_thirds), combination across differing-area floors, and definition of final "net depth" are all BLOCKED pending signed examples',
            'Invariant "third + two_thirds ≠ total" unresolved',
            'ADAPTER — Modules\\JeaServices\\Adapters\\Srv001\\LegacyBridgeNetDepthRule (outside Domain layer per JDG-TD01A-02)',
        ];
    }
}
