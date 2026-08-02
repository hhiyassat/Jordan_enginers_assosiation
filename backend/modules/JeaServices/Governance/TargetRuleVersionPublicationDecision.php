<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

/**
 * TD-04 · Immutable decision returned by
 * `TargetRuleVersionPublicationPolicy::decide()`.
 *
 * `$allowed=true`  → publisher may proceed with the requested status
 *                    change.
 * `$allowed=false` → publisher must NOT persist the change; `$reasons`
 *                    carries machine-readable codes suitable for
 *                    audit-log extras.
 */
final class TargetRuleVersionPublicationDecision
{
    /**
     * @param bool           $allowed
     * @param list<string>   $reasons  machine-readable reason codes
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly array $reasons,
    ) {
    }

    /** @param list<string> $reasons */
    public static function allow(array $reasons = []): self
    {
        return new self(allowed: true, reasons: $reasons);
    }

    /** @param list<string> $reasons */
    public static function block(array $reasons): self
    {
        return new self(allowed: false, reasons: $reasons);
    }
}
