<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

/**
 * Typed outcome returned by ServicePublicationPolicy::evaluate.
 *
 * A policy MUST NOT mutate or save any Eloquent model
 * (see JDG-SG00-04). It returns this value object; the calling use case
 * decides how to persist the transition.
 */
final class PublicationDecision
{
    // OK
    public const PUB_OK = 'PUB_OK';

    // Blockers
    public const PUB_BLOCKED_PLACEHOLDER_FEE       = 'PUB_BLOCKED_PLACEHOLDER_FEE';
    public const PUB_BLOCKED_PLACEHOLDER_WORKFLOW  = 'PUB_BLOCKED_PLACEHOLDER_WORKFLOW';
    public const PUB_BLOCKED_MISSING_UAT           = 'PUB_BLOCKED_MISSING_UAT';
    public const PUB_BLOCKED_MISSING_UAT_REFERENCE = 'PUB_BLOCKED_MISSING_UAT_REFERENCE';
    public const PUB_BLOCKED_SCHEMA_STRUCTURE      = 'PUB_BLOCKED_SCHEMA_STRUCTURE';
    public const PUB_BLOCKED_EFFECTIVE_FROM_FUTURE = 'PUB_BLOCKED_EFFECTIVE_FROM_FUTURE';
    public const PUB_BLOCKED_MISSING_REASON        = 'PUB_BLOCKED_MISSING_REASON';
    public const PUB_BLOCKED_MAKER_CHECKER         = 'PUB_BLOCKED_MAKER_CHECKER';

    /**
     * @param  list<string>                 $reasonCodes
     * @param  array<string, string>        $messages    reason code → human-readable message
     */
    private function __construct(
        public readonly bool $allowed,
        public readonly array $reasonCodes,
        public readonly array $messages,
    ) {
    }

    public static function ok(): self
    {
        return new self(true, [self::PUB_OK], []);
    }

    /**
     * @param  list<string>                 $reasonCodes
     * @param  array<string, string>        $messages
     */
    public static function blocked(array $reasonCodes, array $messages): self
    {
        return new self(false, $reasonCodes, $messages);
    }
}
