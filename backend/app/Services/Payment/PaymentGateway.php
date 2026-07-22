<?php

declare(strict_types=1);

namespace App\Services\Payment;

use Modules\JeaServices\Models\Application;

/**
 * PaymentGateway
 *
 * Contract every payment provider integration implements. The workflow
 * layer talks only to this interface — swapping in eFAWATEERcom /
 * JoMoPay / Stripe later requires implementing the interface + updating
 * the container binding in AppServiceProvider, not touching any
 * business logic in WorkflowEngine or the controllers.
 *
 * Deliberately narrow: three methods that model the three moments a
 * payment surface interacts with the domain. Adding operations here is
 * a design decision; adding them to a concrete implementation is a
 * data-in / data-out detail.
 */
interface PaymentGateway
{
    /**
     * Kick off a payment for the given application.
     *
     * Returns a PaymentInitiation carrying the gateway's transaction
     * reference + a redirect URL the applicant follows to complete
     * payment on the gateway's hosted page. The mock returns a
     * synthetic reference and a local URL so the flow can be exercised
     * end-to-end without hitting an upstream sandbox.
     */
    public function initiate(Application $app): PaymentInitiation;

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
