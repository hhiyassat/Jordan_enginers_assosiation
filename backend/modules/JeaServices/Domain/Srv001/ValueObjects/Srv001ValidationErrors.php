<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\ValueObjects;

/**
 * TD-01 · Field-id-keyed validation errors for a SRV-001 submission.
 *
 * Immutable. Same shape ServiceSubmissionDecision::rejected expects.
 */
final class Srv001ValidationErrors
{
    /** @param array<string, list<string>> $byField */
    public function __construct(public readonly array $byField)
    {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function isEmpty(): bool
    {
        return $this->byField === [];
    }

    /**
     * Field-id → list of Arabic error messages.
     *
     * @return array<string, list<string>>
     */
    public function toArray(): array
    {
        return $this->byField;
    }

    public function withError(string $fieldId, string $message): self
    {
        $next = $this->byField;
        $next[$fieldId] = array_merge($next[$fieldId] ?? [], [$message]);
        return new self($next);
    }
}
