<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents\ValueObjects;

/**
 * TD-06 · Immutable decision returned by `AttachmentLimitPolicy::
 * resolveLimit()`. `allowed()` returns a decision carrying the
 * published limit in bytes; `configurationBlocked()` returns a
 * decision carrying only the blocking reason codes.
 */
final class AttachmentLimitDecision
{
    /** @param list<string> $reasonCodes */
    public function __construct(
        public readonly bool $allowed,
        public readonly ?int $limitBytes,
        public readonly array $reasonCodes,
    ) {
    }

    public static function allowed(int $limitBytes): self
    {
        return new self(allowed: true, limitBytes: $limitBytes, reasonCodes: []);
    }

    /** @param list<string> $reasons */
    public static function configurationBlocked(array $reasons): self
    {
        return new self(allowed: false, limitBytes: null, reasonCodes: $reasons);
    }
}
