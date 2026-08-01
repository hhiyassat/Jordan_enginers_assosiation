<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents\Contracts;

use Modules\JeaServices\Domain\Documents\ValueObjects\DocumentMetadata;

/**
 * TD-06 · Quarantine port.
 *
 * Places a document into `QUARANTINE_HELD` when a scan is pending
 * or a mismatch is detected; releases it to `QUARANTINE_RELEASED`
 * after a clean scan. Domain-layer decision; adapter enforces via
 * storage-side moves / signed-URL revocation.
 */
interface QuarantinePort
{
    public function hold(DocumentMetadata $metadata, string $reason): DocumentMetadata;

    public function release(DocumentMetadata $metadata): DocumentMetadata;
}
