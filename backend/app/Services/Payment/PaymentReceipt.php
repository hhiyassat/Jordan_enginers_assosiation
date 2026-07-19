<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Return type from PaymentGateway::verifyCallback().
 *
 * Represents a payment the gateway has confirmed as settled. The
 * workflow layer flips Application.payment_status → 'paid' only when
 * it receives one of these; the value object exists to make sure
 * every gateway returns the same shape and can't slip half a payload
 * past the type system.
 */
final readonly class PaymentReceipt
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        public string $reference,
        public float $amount,
        public string $currency,
        /** ISO-8601 timestamp of settlement per the gateway's clock. */
        public string $settledAt,
        /** Provider-specific extras (fee breakdown, card mask, etc). */
        public array $meta = [],
    ) {}
}
