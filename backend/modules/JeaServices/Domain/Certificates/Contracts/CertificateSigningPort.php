<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Certificates\Contracts;

/**
 * TD-07 · Port for cryptographic signing of a rendered certificate.
 *
 * NOT_WIRED. Production signing blocked.
 */
interface CertificateSigningPort
{
    /** Returns a signature handle (never the private key). */
    public function sign(string $renderHandle): string;

    public function operationalState(): string;
}
