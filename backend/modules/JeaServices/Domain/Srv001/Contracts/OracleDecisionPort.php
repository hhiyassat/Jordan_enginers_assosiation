<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-05 · Port for retrieving an Oracle-persisted decision related to
 * the applying office / engineer / discipline (e.g., disciplinary
 * hold, quota override).
 *
 * BLOCKED_UNTIL: OD-30 (Oracle integration contract).
 *
 * When no signed Oracle integration contract exists, the port MUST
 * return `CONTRACT_MISSING` — never a fabricated positive decision.
 */
interface OracleDecisionPort
{
    public function retrieveDecision(
        int $organizationId,
        int $userId,
        string $correlationId,
    ): Srv001PortDecision;
}
