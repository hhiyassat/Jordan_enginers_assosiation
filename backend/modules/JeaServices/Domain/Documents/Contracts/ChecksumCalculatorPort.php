<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents\Contracts;

/**
 * TD-06 · Checksum calculator port.
 *
 * Adapter reads the bytes from storage and returns a 64-hex-char
 * SHA-256 hash. Domain persists only the hash + never the bytes.
 */
interface ChecksumCalculatorPort
{
    /** Returns 64-character lowercase hex SHA-256. */
    public function sha256(string $storageKey): string;
}
