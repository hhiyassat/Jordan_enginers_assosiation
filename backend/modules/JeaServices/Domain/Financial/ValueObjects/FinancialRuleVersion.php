<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Financial\ValueObjects;

use InvalidArgumentException;

/**
 * TD-08 · Immutable financial-rule version identifier.
 *
 * Every FeeQuote and TaxQuote is bound to one of these. Runtime
 * selection is gated by `FinancialRuleSelectionPolicy` — DRAFT /
 * PROVISIONAL / UNPUBLISHED rules are simulation-only.
 *
 * Runtime lifecycles:
 *   DRAFT               — early, not yet reviewed
 *   PROVISIONAL         — evidence exists but authorisation pending
 *   SIMULATION_ONLY     — pilot for dual-run only
 *   UNPUBLISHED         — approved but not yet published
 *   PUBLISHED           — the ONLY status that permits runtime selection
 *   RETIRED             — historically bound; not selectable for new quotes
 */
final class FinancialRuleVersion
{
    public const LIFECYCLE_DRAFT            = 'DRAFT';
    public const LIFECYCLE_PROVISIONAL      = 'PROVISIONAL';
    public const LIFECYCLE_SIMULATION_ONLY  = 'SIMULATION_ONLY';
    public const LIFECYCLE_UNPUBLISHED      = 'UNPUBLISHED';
    public const LIFECYCLE_PUBLISHED        = 'PUBLISHED';
    public const LIFECYCLE_RETIRED          = 'RETIRED';

    /** @param list<string> $blockingOds */
    public function __construct(
        public readonly string $ruleVersionId,
        public readonly string $formulaIdentifier,
        public readonly string $sourceReference,
        public readonly string $sourceStatus,
        public readonly string $businessApprovalStatus,
        public readonly string $implementationAuthorization,
        public readonly string $publicationAuthorization,
        public readonly string $lifecycleStatus,
        public readonly array $blockingOds = [],
        public readonly ?string $effectiveFrom = null,
        public readonly ?string $effectiveTo = null,
    ) {
        if ($ruleVersionId === '' || $formulaIdentifier === '' || $sourceReference === '') {
            throw new InvalidArgumentException('FinancialRuleVersion: required string fields missing');
        }
        if (! in_array(
            $lifecycleStatus,
            [self::LIFECYCLE_DRAFT, self::LIFECYCLE_PROVISIONAL, self::LIFECYCLE_SIMULATION_ONLY, self::LIFECYCLE_UNPUBLISHED, self::LIFECYCLE_PUBLISHED, self::LIFECYCLE_RETIRED],
            true,
        )) {
            throw new InvalidArgumentException("Unknown lifecycleStatus: {$lifecycleStatus}");
        }
    }

    public function isPublished(): bool
    {
        return $this->lifecycleStatus === self::LIFECYCLE_PUBLISHED;
    }
}
