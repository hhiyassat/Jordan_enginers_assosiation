<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-07 · Non-critical port for producing a map/location link
 * for the parcel.
 *
 * MAP_ADAPTER_STATUS may remain UNAVAILABLE without blocking the
 * core workflow — callers must be able to proceed with a typed
 * NOT_APPLICABLE / EXTERNAL_UNAVAILABLE decision when this port
 * cannot produce a link.
 */
interface LocationLinkPort
{
    public function generateLocationLink(
        string $basinNumber,
        string $parcelNumber,
        string $correlationId,
    ): Srv001PortDecision;
}
