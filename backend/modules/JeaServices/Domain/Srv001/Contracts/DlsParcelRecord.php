<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

/**
 * TD-01 · Immutable record returned by DlsLookupPort.
 *
 * Fields deliberately narrow — captures what the target domain needs
 * for auto-fill + conflict-verification, not the whole DLS record.
 */
final class DlsParcelRecord
{
    public function __construct(
        public readonly string $governorate,
        public readonly string $directorate,
        public readonly string $village,
        public readonly string $basinNumber,
        public readonly string $basinName,
        public readonly string $parcelNumber,
        public readonly ?string $ownerNationalId,
        public readonly ?string $ownerName,
    ) {
    }
}
