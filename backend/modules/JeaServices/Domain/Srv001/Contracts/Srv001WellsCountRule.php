<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

/**
 * TD-01A · Domain-owned port for the SRV-001 wells-count rule.
 *
 * See JDG-TD01A-02 for rationale. Domain layer must not import
 * Legacy* directly; this port is the abstraction.
 */
interface Srv001WellsCountRule
{
    /**
     * Compute wells count for a given largest floor area (m²).
     *
     * @return array<string, mixed>  raw domain output — shape follows
     *   legacy `WellsCountCalculator::compute`:
     *     - status: CALCULATED | (fallbacks)
     *     - wells: int (present when CALCULATED)
     *     - band:  string label
     */
    public function compute(float $floorArea): array;

    public function ruleVersionId(): int;

    /** @return list<string> */
    public function openDecisions(): array;
}
