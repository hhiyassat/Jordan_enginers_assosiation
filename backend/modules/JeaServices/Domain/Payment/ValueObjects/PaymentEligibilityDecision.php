<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Payment\ValueObjects;

use InvalidArgumentException;

/**
 * TD-07 · Immutable typed decision for the payment eligibility
 * boundary — the workflow gate that determines whether an
 * application may be forwarded to the payment path.
 *
 * PAYMENT_ELIGIBLE is not itself a payment; it is the pre-payment
 * boundary decision. Actual payment orchestration + gateways
 * arrive in TD-08.
 */
final class PaymentEligibilityDecision
{
    public const ELIGIBLE                = 'PAYMENT_ELIGIBLE';
    public const BLOCKED                 = 'PAYMENT_BLOCKED';
    public const RULE_UNAVAILABLE        = 'PAYMENT_RULE_UNAVAILABLE';
    public const MANUAL_REVIEW_REQUIRED  = 'MANUAL_REVIEW_REQUIRED';

    /** @param list<string> $reasonCodes  @param list<string> $blockingOds */
    public function __construct(
        public readonly string $outcome,
        public readonly int $applicationId,
        public readonly array $reasonCodes = [],
        public readonly array $blockingOds = [],
    ) {
        if (! in_array(
            $outcome,
            [self::ELIGIBLE, self::BLOCKED, self::RULE_UNAVAILABLE, self::MANUAL_REVIEW_REQUIRED],
            true,
        )) {
            throw new InvalidArgumentException("Unknown PaymentEligibilityDecision outcome: {$outcome}");
        }
    }

    public function isEligible(): bool
    {
        return $this->outcome === self::ELIGIBLE;
    }
}
