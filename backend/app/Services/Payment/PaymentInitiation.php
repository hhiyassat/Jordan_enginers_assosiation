<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Return type from PaymentGateway::initiate().
 *
 * Immutable value object — no setters, no state. Whatever caller kicked
 * off the payment stores the reference and hands the redirect URL to
 * the applicant's browser.
 */
final readonly class PaymentInitiation
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        /** Gateway's transaction id — persisted on Application.payment_reference. */
        public string $reference,
        /** Hosted-page URL the applicant follows to complete payment. */
        public string $redirectUrl,
        /** Amount + currency echoed so callers can log/verify. */
        public float $amount,
        public string $currency,
        /** Free-form gateway metadata. Kept generic so future gateways
         *  can attach whatever they want (session token, expiry, etc). */
        public array $meta = [],
    ) {}
}
