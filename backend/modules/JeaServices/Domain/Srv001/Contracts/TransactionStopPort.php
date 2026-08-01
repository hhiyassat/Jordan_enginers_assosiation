<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-07 · Port for the BURA235 "stop transaction" responsibility.
 *
 * FAIL-CLOSED. Absent adapter returns CONTRACT_MISSING.
 */
interface TransactionStopPort
{
    public function stopTransaction(
        int $applicationId,
        string $reasonCode,
        string $correlationId,
    ): Srv001PortDecision;
}
