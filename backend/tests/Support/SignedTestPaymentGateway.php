<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentInitiation;
use App\Services\Payment\PaymentIntent;
use App\Services\Payment\PaymentReceipt;

/**
 * SignedTestPaymentGateway — CS-03.
 *
 * A deterministic, HMAC-signed PaymentGateway implementation used
 * by the callback-flow feature tests. Unlike MockPaymentGateway,
 * this gateway actually validates a signature over the raw payload
 * fields — so unsigned, invalid-signature, tampered-payload, and
 * expired callbacks all fail as they would against a real provider.
 *
 * Signature scheme: HMAC-SHA256 over
 *   reference|amount|currency|settled_at
 * with a shared secret. This is not the exact scheme any real
 * provider uses — it exists so tests can generate valid + invalid
 * callbacks deterministically without inventing eFAWATEERcom or
 * JoMoPay proprietary framing.
 */
final class SignedTestPaymentGateway implements PaymentGateway
{
    /** Skew tolerance for the `settled_at` header, seconds. */
    public const REPLAY_WINDOW_SECONDS = 300;

    public function __construct(
        private readonly string $sharedSecret,
        private readonly ?\Closure $clock = null,
    ) {
    }

    public function initiate(PaymentIntent $intent): PaymentInitiation
    {
        $reference = 'SIGN-' . $intent->reference . '-' . bin2hex(random_bytes(4));
        return new PaymentInitiation(
            reference:   $reference,
            redirectUrl: 'https://sandbox.gateway.test/pay?ref=' . $reference,
            amount:      $intent->amount,
            currency:    $intent->currency,
            meta:        ['gateway' => 'signed-test'],
        );
    }

    /**
     * @param  array<string, mixed>  $callbackPayload
     */
    public function verifyCallback(array $callbackPayload): PaymentReceipt
    {
        foreach (['reference', 'amount', 'currency', 'settled_at', 'signature'] as $required) {
            if (! isset($callbackPayload[$required]) || $callbackPayload[$required] === '') {
                throw new \InvalidArgumentException("Missing required field: {$required}");
            }
        }

        $ref       = (string) $callbackPayload['reference'];
        $amount    = (string) $callbackPayload['amount'];
        $currency  = (string) $callbackPayload['currency'];
        $settledAt = (string) $callbackPayload['settled_at'];
        $signature = (string) $callbackPayload['signature'];

        $expected = self::computeSignature($this->sharedSecret, $ref, $amount, $currency, $settledAt);

        if (! hash_equals($expected, $signature)) {
            throw new \InvalidArgumentException('Invalid signature.');
        }

        $settledTs = strtotime($settledAt) ?: 0;
        $now = ($this->clock !== null) ? ($this->clock)() : time();
        if (abs($now - $settledTs) > self::REPLAY_WINDOW_SECONDS) {
            throw new \InvalidArgumentException('Callback expired: settled_at outside replay window.');
        }

        return new PaymentReceipt(
            reference: $ref,
            amount:    (float) $amount,
            currency:  $currency,
            settledAt: $settledAt,
            meta:      ['gateway' => 'signed-test', 'raw' => $callbackPayload],
        );
    }

    public function refund(string $paymentReference, ?string $reason = null): bool
    {
        return true;
    }

    /**
     * Test helper: mints the signature the way the fake provider would.
     * Kept as a public static so tests can build both valid + invalid
     * callback payloads without coupling to the wire format.
     */
    public static function computeSignature(
        string $secret,
        string $reference,
        string $amount,
        string $currency,
        string $settledAt,
    ): string {
        return hash_hmac('sha256', "{$reference}|{$amount}|{$currency}|{$settledAt}", $secret);
    }
}
