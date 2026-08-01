<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-07 · Port for the BURA256 "add inspection" responsibility.
 *
 * FAIL-CLOSED. Every non-permissive outcome (EXTERNAL_UNAVAILABLE,
 * CONTRACT_MISSING, INVALID_EXTERNAL_RESPONSE, MANUAL_REVIEW,
 * INELIGIBLE) must block workflow progression. Production adapter
 * remains absent.
 */
interface InspectionAdditionPort
{
    public function addInspection(
        int $applicationId,
        string $inspectionKind,
        string $correlationId,
    ): Srv001PortDecision;
}
