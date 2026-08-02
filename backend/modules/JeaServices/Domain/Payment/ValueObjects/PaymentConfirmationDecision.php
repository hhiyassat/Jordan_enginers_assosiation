<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Payment\ValueObjects;

use InvalidArgumentException;

/**
 * TD-08 · Immutable typed decision after a payment-gateway callback
 * has been processed.
 *
 * FAIL-CLOSED: only CONFIRMED unlocks the receipt / certificate
 * flow. REPLAY_DETECTED is treated as a security event and MUST
 * NOT unlock anything.
 */
final class PaymentConfirmationDecision
{
    public const CONFIRMED         = 'CONFIRMED';
    public const REJECTED          = 'REJECTED';
    public const REPLAY_DETECTED   = 'REPLAY_DETECTED';
    public const CALLBACK_INVALID  = 'CALLBACK_INVALID';
    public const PENDING           = 'PENDING';

    /** @param list<string> $reasonCodes */
    public function __construct(
        public readonly string $outcome,
        public readonly string $paymentIntentId,
        public readonly int $applicationId,
        public readonly array $reasonCodes = [],
    ) {
        if (! in_array(
            $outcome,
            [self::CONFIRMED, self::REJECTED, self::REPLAY_DETECTED, self::CALLBACK_INVALID, self::PENDING],
            true,
        )) {
            throw new InvalidArgumentException("Unknown outcome: {$outcome}");
        }
    }

    public function unlocksReceiptFlow(): bool
    {
        return $this->outcome === self::CONFIRMED;
    }
}
