<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-05 · Port for verifying the office has no pending correction
 * request (تصحيح بيانات المكتب) attached that must be resolved
 * before further submissions.
 *
 * CORRECTION_REQUIRED = a pending correction exists.
 */
interface OfficeCorrectionStatusPort
{
    public function checkCorrectionStatus(
        int $organizationId,
        string $correlationId,
    ): Srv001PortDecision;
}
