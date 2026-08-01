<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-05 · Port for looking up prior transactions against a parcel /
 * office pair, used by the cadastral-conflict guard extension.
 *
 * Returns NOT_APPLICABLE when no prior transactions exist and
 * INELIGIBLE when a prior blocking transaction is found.
 */
interface PriorTransactionPort
{
    public function findPriorTransactions(
        string $basinNumber,
        string $parcelNumber,
        string $correlationId,
    ): Srv001PortDecision;
}
