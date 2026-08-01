<?php

declare(strict_types=1);

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MockPaymentGateway
 *
 * Local-dev + test-suite implementation of PaymentGateway. Never talks
 * to the network — every response is synthetic. Concrete behaviours:
 *
 *   • initiate() mints a MOCK-{intent.reference}-{ulid} identifier and
 *     returns a local redirect URL the frontend can navigate to.
 *   • verifyCallback() accepts any payload carrying `reference` and
 *     `amount` — no signature check. In-line comment marks this as
 *     the exact spot a real implementation MUST verify HMAC.
 *   • refund() logs to the security channel and returns true.
 *
 * H-07: takes a provider-neutral PaymentIntent DTO — no JEA imports.
 * ProductionSafety aborts boot if this Mock is bound in production.
 */
final class MockPaymentGateway implements PaymentGateway
{
    public function initiate(PaymentIntent $intent): PaymentInitiation
    {
        $reference = 'MOCK-' . $intent->reference . '-' . Str::ulid();

        return new PaymentInitiation(
            reference:   $reference,
            redirectUrl: url('/payment/mock?reference=' . $reference),
            amount:      $intent->amount,
            currency:    $intent->currency,
            meta:        array_merge(['gateway' => 'mock', 'sandbox' => true], $intent->meta),
        );
    }

    /**
     * Real gateways would verify a signed callback here. The mock only
     * checks that the required fields are present so business-logic
     * tests can drive the code path without generating signatures.
     *
     * REPLACE-ME: a real gateway MUST validate the HMAC/JWT/signature
     * on this payload before returning. Failing to do so lets any
     * attacker POST to the webhook and mark applications paid.
     */
    /** @param array<string, mixed> $callbackPayload */
    public function verifyCallback(array $callbackPayload): PaymentReceipt
    {
        foreach (['reference', 'amount', 'currency'] as $required) {
            if (empty($callbackPayload[$required])) {
                throw new \InvalidArgumentException(
                    "Mock callback missing required field: {$required}"
                );
            }
        }

        return new PaymentReceipt(
            reference: (string) $callbackPayload['reference'],
            amount:    (float) $callbackPayload['amount'],
            currency:  (string) $callbackPayload['currency'],
            settledAt: (string) ($callbackPayload['settled_at'] ?? now()->toIso8601String()),
            meta:      ['gateway' => 'mock', 'raw' => $callbackPayload],
        );
    }

    public function refund(string $paymentReference, ?string $reason = null): bool
    {
        Log::channel('security')->info('mock_payment_refund', [
            'reference' => $paymentReference,
            'reason'    => $reason,
            'ts'        => now()->toIso8601String(),
        ]);
        return true;
    }
}
