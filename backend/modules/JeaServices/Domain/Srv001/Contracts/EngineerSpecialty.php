<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

/**
 * TD-01 · Immutable record returned by EngineerRegistryPort.
 *
 * Field set kept deliberately small — the port returns only what the
 * SRV-001 target domain needs, not the full engineer record.
 */
final class EngineerSpecialty
{
    public function __construct(
        public readonly string $engineerNumber,
        public readonly string $fullName,
        public readonly string $specialtyCode,   // e.g. SOIL_MECHANICS / CIVIL / ARCHITECT
        public readonly bool $isHeadOfSpecialization,
    ) {
    }
}
