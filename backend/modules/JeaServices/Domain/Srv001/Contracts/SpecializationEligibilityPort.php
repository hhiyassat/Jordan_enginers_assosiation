<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-05 · Port checking that the engineer's specialization is not
 * suspended for SRV-001 (FR-SS-082).
 *
 * SPECIALIZATION_BLOCKED = suspension record present.
 */
interface SpecializationEligibilityPort
{
    public function checkEngineerSpecialization(
        int $userId,
        EngineerSpecialty $specialty,
        string $correlationId,
    ): Srv001PortDecision;
}
