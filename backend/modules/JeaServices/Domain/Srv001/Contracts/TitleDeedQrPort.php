<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-05 · Port for extracting registration-deed (قوشان) QR content
 * — FR-SS-087.
 *
 * The port receives an opaque handle identifying an uploaded
 * document (never file bytes — Domain does not touch file bytes;
 * that's a TD-06 concern) and returns a decision carrying either
 * validated QR content or an INVALID_EXTERNAL_RESPONSE outcome
 * if the QR could not be parsed.
 */
interface TitleDeedQrPort
{
    public function extractQr(
        string $documentHandle,
        string $correlationId,
    ): Srv001PortDecision;
}
