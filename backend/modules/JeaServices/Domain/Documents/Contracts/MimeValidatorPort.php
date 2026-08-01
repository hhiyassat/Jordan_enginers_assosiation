<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents\Contracts;

/**
 * TD-06 · Declared vs detected MIME validation port.
 *
 * The adapter reads the leading bytes of the uploaded file (magic
 * bytes) via storage port + reports the detected MIME. Domain
 * compares detected vs declared and decides whether the mismatch is
 * a rejection (e.g., .pdf declared but detected as .exe).
 */
interface MimeValidatorPort
{
    public function detectMime(string $storageKey): ?string;

    public function magicBytesMatch(string $storageKey, string $declaredMime): bool;
}
