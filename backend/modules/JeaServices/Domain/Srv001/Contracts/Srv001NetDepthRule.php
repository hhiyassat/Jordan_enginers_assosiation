<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

/**
 * TD-01A · Domain-owned port for the SRV-001 net-depth rule.
 *
 * See JDG-TD01A-02 for rationale.
 */
interface Srv001NetDepthRule
{
    /**
     * Compute net depth for a given floor count.
     *
     * @return array<string, mixed>  raw domain output — shape follows
     *   legacy `NetDepthTable::compute`:
     *     - status: CALCULATED | INELIGIBLE
     *     - third_m / two_thirds_m / total_m on CALCULATED paths
     */
    public function compute(int $floorCount): array;

    public function ruleVersionId(): int;

    /** @return list<string> */
    public function openDecisions(): array;
}
