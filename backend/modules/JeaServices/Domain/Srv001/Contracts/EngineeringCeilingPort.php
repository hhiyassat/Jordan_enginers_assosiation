<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-05 · Port enforcing the engineering ceiling — the aggregate
 * per-office cap across all disciplines for the calendar year.
 *
 * ENGINEERING_CEILING_EXCEEDED = summing the requested area to the
 * year-to-date total would exceed the cap.
 */
interface EngineeringCeilingPort
{
    public function checkCeiling(
        int $organizationId,
        int $requestedM2,
        string $correlationId,
    ): Srv001PortDecision;
}
