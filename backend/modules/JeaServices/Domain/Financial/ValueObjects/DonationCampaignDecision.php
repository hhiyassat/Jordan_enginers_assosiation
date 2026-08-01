<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Financial\ValueObjects;

use InvalidArgumentException;

/**
 * TD-08 · Immutable donation-campaign decision.
 *
 * A mandatory campaign MUST have a signed legal authority reference
 * before it may be activated. Construction with mandatory=true +
 * missing legalAuthorityReference is forbidden.
 */
final class DonationCampaignDecision
{
    public const AMOUNT_TYPE_FIXED       = 'FIXED';
    public const AMOUNT_TYPE_PERCENTAGE  = 'PERCENTAGE';

    public const OUTCOME_ACTIVE_OPTIONAL       = 'ACTIVE_OPTIONAL';
    public const OUTCOME_ACTIVE_MANDATORY      = 'ACTIVE_MANDATORY';
    public const OUTCOME_BLOCKED_NO_AUTHORITY  = 'BLOCKED_NO_AUTHORITY';
    public const OUTCOME_NOT_APPLICABLE        = 'NOT_APPLICABLE';

    public function __construct(
        public readonly string $campaignId,
        public readonly string $scope,
        public readonly string $amountType,
        public readonly float $amount,
        public readonly bool $mandatory,
        public readonly ?string $startTimestamp,
        public readonly ?string $endTimestamp,
        public readonly string $ruleVersionId,
        public readonly ?string $legalAuthorityReference,
        public readonly string $publicationStatus,
        public readonly string $outcome,
    ) {
        if ($campaignId === '' || $ruleVersionId === '') {
            throw new InvalidArgumentException('campaignId + ruleVersionId required');
        }
        if (! in_array($amountType, [self::AMOUNT_TYPE_FIXED, self::AMOUNT_TYPE_PERCENTAGE], true)) {
            throw new InvalidArgumentException("Unknown amountType: {$amountType}");
        }
        if (! in_array(
            $outcome,
            [self::OUTCOME_ACTIVE_OPTIONAL, self::OUTCOME_ACTIVE_MANDATORY, self::OUTCOME_BLOCKED_NO_AUTHORITY, self::OUTCOME_NOT_APPLICABLE],
            true,
        )) {
            throw new InvalidArgumentException("Unknown outcome: {$outcome}");
        }
        // Construction-time fail-closed: mandatory campaigns must
        // have a legal authority reference AND be published.
        if ($mandatory
            && ($legalAuthorityReference === null || $legalAuthorityReference === '')
            && $outcome === self::OUTCOME_ACTIVE_MANDATORY
        ) {
            throw new InvalidArgumentException(
                'Mandatory campaign cannot be ACTIVE_MANDATORY without a legalAuthorityReference',
            );
        }
    }
}
