<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-05 · Port for office yearly quota checks.
 *
 * Returns QUOTA_EXHAUSTED when the office has consumed its yearly
 * ceiling. Fail-closed on provider failure.
 */
interface OfficeQuotaPort
{
    public function checkYearlyQuota(
        int $organizationId,
        int $requestedM2,
        string $correlationId,
    ): Srv001PortDecision;
}
