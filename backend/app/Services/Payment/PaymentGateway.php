<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * PaymentGateway
 *
 * Contract every payment provider integration implements. The workflow
 * layer talks only to this interface — swapping in eFAWATEERcom /
 * JoMoPay / Stripe later requires implementing the interface + updating
 * the container binding in AppServiceProvider, not touching any
 * business logic in WorkflowEngine or the controllers.
 *
 * H-07: `initiate()` takes a provider-neutral `PaymentIntent` DTO
 * rather than a JEA `Application` model. The JEA-side adapter builds
 * the intent from an Application and hands it across the Platform /
 * gateway boundary — Platform no longer imports any JEA class.
 *
 * Deliberately narrow: three methods that model the three moments a
 * payment surface interacts with the domain.
 */
interface PaymentGateway
{
    /**
     * Kick off a payment for the given intent.
     *
     * Returns a PaymentInitiation carrying the gateway's transaction
     * reference + a redirect URL the applicant follows to complete
     * payment on the gateway's hosted page. The mock returns a
     * synthetic reference and a local URL so the flow can be exercised
     * end-to-end without hitting an upstream sandbox.
     */
    public function initiate(PaymentIntent $intent): PaymentInitiation;

    /**
     * Verify + persist a gateway callback.
     *
     * Every gateway posts back to a webhook with proof-of-payment
     * (signed HMAC, JWT, or provider-specific token). Implementations
     * validate the signature, cross-check the amount against the
     * application, and return a PaymentReceipt. Any tamper or amount
     * mismatch MUST throw — do not silently mark half-paid.
     *
     * @param array<string, mixed> $callbackPayload
     */
    public function verifyCallback(array $callbackPayload): PaymentReceipt;

    /**
     * Refund a completed payment. Optional — some gateways only refund
     * within the same day, some require a support ticket. Contract
     * returns bool so failures surface without an exception cascade;
     * implementations should log the reason to the security channel.
     */
    public function refund(string $paymentReference, ?string $reason = null): bool;
}
