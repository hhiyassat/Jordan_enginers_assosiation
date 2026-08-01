<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

/**
 * JeaMembershipResult — verifier response value object.
 *
 * Immutable pair (isValid, reason). Reason is Arabic and safe to surface
 * to the applicant when isValid is false.
 */
final class JeaMembershipResult
{
    private function __construct(
        public readonly bool $isValid,
        public readonly string $reasonAr = '',
    ) {}

    public static function valid(): self
    {
        return new self(true);
    }

    public static function invalid(string $reasonAr): self
    {
        return new self(false, $reasonAr);
    }
}
