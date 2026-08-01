<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents;

/**
 * TD-06 · Immutable decision returned by
 * `PartialEditGrantEnforcementPolicy::decide()`.
 */
final class EditPermissionDecision
{
    /** @param list<string> $reasonCodes */
    public function __construct(
        public readonly bool $permitted,
        public readonly array $reasonCodes,
    ) {
    }

    public static function allowed(): self
    {
        return new self(permitted: true, reasonCodes: []);
    }

    /** @param list<string> $reasons */
    public static function denied(array $reasons): self
    {
        return new self(permitted: false, reasonCodes: $reasons);
    }
}
