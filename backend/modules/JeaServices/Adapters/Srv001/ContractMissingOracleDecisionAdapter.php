<?php

declare(strict_types=1);

namespace Modules\JeaServices\Adapters\Srv001;

use Modules\JeaServices\Domain\Srv001\Contracts\OracleDecisionPort;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortAuditEnvelope;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-05 · Default adapter for `OracleDecisionPort` when no signed
 * Oracle integration contract exists.
 *
 * BLOCKED_UNTIL: OD-30 (Oracle integration contract).
 *
 * This adapter deliberately returns `CONTRACT_MISSING` on every
 * call — proving the fail-closed contract at the port boundary.
 * It's registered as the DEFAULT container binding so any accidental
 * runtime invocation of the port (there is none today) yields a
 * blocking decision, never a fabricated positive one.
 */
final class ContractMissingOracleDecisionAdapter implements OracleDecisionPort
{
    public const PROVIDER_ID = 'contract-missing-oracle';

    public function retrieveDecision(
        int $organizationId,
        int $userId,
        string $correlationId,
    ): Srv001PortDecision {
        return Srv001PortDecision::contractMissing(
            new Srv001PortAuditEnvelope(
                correlationId:          $correlationId,
                providerId:             self::PROVIDER_ID,
                sourceKind:             Srv001PortAuditEnvelope::KIND_FAKE,
                responseClassification: 'no_signed_contract',
                timestamp:              date('c'),
                sourceStatus:           'UNKNOWN',
                blockingOd:             'OD-30',
                reasonCodes:            ['ORACLE_INTEGRATION_CONTRACT_UNSIGNED'],
            ),
            'OD-30',
        );
    }
}
