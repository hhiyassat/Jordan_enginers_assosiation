<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\ValueObjects;

use InvalidArgumentException;

/**
 * TD-05 · FR-SS-081 quota-increase referral value object.
 *
 * STRUCTURAL only — no migration, no runtime activation. When TD-05+
 * adds the runtime consumer, this VO becomes the payload the
 * `QuotaIncreaseReferralPort` returns AND the anchor for a future
 * Eloquent model.
 *
 * IMMUTABLE. Every mutation returns a new instance.
 */
final class QuotaIncreaseReferral
{
    public const DECISION_PENDING  = 'PENDING';
    public const DECISION_APPROVED = 'APPROVED';
    public const DECISION_REJECTED = 'REJECTED';

    /** @param list<string> $reasonCodes */
    public function __construct(
        public readonly string $referralId,
        public readonly int $applicationId,
        public readonly int $requestedM2Increase,
        public readonly string $justificationText,
        public readonly int $feeAmount,
        public readonly string $decisionStatus = self::DECISION_PENDING,
        public readonly array $reasonCodes = [],
        public readonly ?string $sessionRef = null,
    ) {
        if ($referralId === '') {
            throw new InvalidArgumentException('referralId required');
        }
        if ($requestedM2Increase <= 0) {
            throw new InvalidArgumentException('requestedM2Increase must be positive');
        }
        if (! in_array(
            $decisionStatus,
            [self::DECISION_PENDING, self::DECISION_APPROVED, self::DECISION_REJECTED],
            true,
        )) {
            throw new InvalidArgumentException("Unknown decisionStatus: {$decisionStatus}");
        }
    }

    /** @param list<string> $reasonCodes */
    public function withDecision(string $status, array $reasonCodes = [], ?string $sessionRef = null): self
    {
        return new self(
            referralId:          $this->referralId,
            applicationId:       $this->applicationId,
            requestedM2Increase: $this->requestedM2Increase,
            justificationText:   $this->justificationText,
            feeAmount:           $this->feeAmount,
            decisionStatus:      $status,
            reasonCodes:         $reasonCodes,
            sessionRef:          $sessionRef,
        );
    }
}
