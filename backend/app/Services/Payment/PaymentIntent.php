<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * PaymentIntent — provider-neutral DTO passed to
 * PaymentGateway::initiate().
 *
 * H-07: previously PaymentGateway::initiate() took the JeaServices
 * `Application` Eloquent model, which forced Platform's Payment
 * abstraction to import a JEA module. That coupling made the gateway
 * un-reusable for any future non-JEA payment (e.g. dues renewals,
 * fine settlement) and violated the review's PLATFORM_JEA_BOUNDARY_GATE.
 *
 * Now: any caller that wants to initiate a payment builds a
 * PaymentIntent from whichever domain object it owns, and passes only
 * this DTO across the Platform / gateway boundary.
 *
 * The DTO carries the minimum a gateway ever needs — an amount, a
 * currency, a callback context reference, and a free-form metadata
 * bag for provider-specific extensions.
 */
final readonly class PaymentIntent
{
    /**
     * @param  non-empty-string  $reference  Caller-supplied reference (e.g. "app-{id}"). Gateways
     *                                       return this on the callback so the caller can join.
     * @param  int  $organizationId
     * @param  float  $amount
     * @param  non-empty-string  $currency  ISO-4217 (e.g. "JOD").
     * @param  string|null  $description  Human-readable label surfaced on the payment page.
     * @param  string|null  $callbackUrl  Optional per-intent callback override; providers that
     *                                    accept a URL per request use this.
     * @param  array<string, mixed>  $meta  Provider-specific extension bag (never inspected by
     *                                      Platform; opaque round-trip through the gateway).
     */
    public function __construct(
        public string $reference,
        public int $organizationId,
        public float $amount,
        public string $currency,
        public ?string $description = null,
        public ?string $callbackUrl = null,
        public array $meta = [],
    ) {}
}
