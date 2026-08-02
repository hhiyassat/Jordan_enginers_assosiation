<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Payment\ValueObjects;

use InvalidArgumentException;
use Modules\JeaServices\Domain\Financial\ValueObjects\FeeQuote;

/**
 * TD-08 · Immutable request to initiate a payment intent.
 *
 * FAIL-CLOSED: construction requires an inbound `FeeQuote` where
 * `isBinding()` is true. A simulation-only or blocked quote CANNOT
 * be used to initiate payment — construction throws.
 *
 * The request itself does NOT talk to any payment gateway. It's the
 * envelope a future adapter would consume. Domain never touches
 * production credentials.
 */
final class PaymentInitiationRequest
{
    public function __construct(
        public readonly string $requestId,
        public readonly int $applicationId,
        public readonly FeeQuote $bindingQuote,
        public readonly string $idempotencyKey,
        public readonly string $issuedTimestamp,
    ) {
        if ($requestId === '' || $idempotencyKey === '' || $issuedTimestamp === '') {
            throw new InvalidArgumentException('PaymentInitiationRequest: required string fields missing');
        }
        if (! $bindingQuote->isBinding()) {
            throw new InvalidArgumentException(
                'PaymentInitiationRequest requires a binding FeeQuote — simulation-only or blocked quotes cannot initiate payment',
            );
        }
    }
}
