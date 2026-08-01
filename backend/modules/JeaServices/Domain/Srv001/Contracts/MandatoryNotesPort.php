<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-05 · Port for retrieving mandatory notes (ملاحظات إلزامية) that
 * apply to the office / parcel — FR-SS-088.
 *
 * MANDATORY_NOTE_BLOCK = an active note with `effect=Block` scopes
 * this office or parcel and must be surfaced before submission.
 */
interface MandatoryNotesPort
{
    public function checkMandatoryNotes(
        int $organizationId,
        ?string $basinNumber,
        ?string $parcelNumber,
        string $correlationId,
    ): Srv001PortDecision;
}
