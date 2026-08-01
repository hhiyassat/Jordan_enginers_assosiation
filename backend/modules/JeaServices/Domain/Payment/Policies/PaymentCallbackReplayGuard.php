<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Payment\Policies;

use Modules\JeaServices\Domain\Payment\ValueObjects\PaymentConfirmationDecision;

/**
 * TD-08 · Callback-replay guard.
 *
 * Structural in-memory implementation for testability. When the
 * real payment adapter lands, this guard's `hasSeen()` /
 * `recordSeen()` methods MUST be backed by a persistent store with
 * a unique constraint on `(payment_intent_id, callback_signature)`
 * so concurrent duplicate callbacks fail atomically at the DB
 * layer.
 *
 * INVARIANT: `evaluate()` returns `REPLAY_DETECTED` when the same
 * (intent, signature) pair has been seen before — a security-
 * critical check the caller MUST honour.
 */
final class PaymentCallbackReplayGuard
{
    /** @var array<string, true> */
    private array $seen = [];

    public function evaluate(
        string $paymentIntentId,
        int $applicationId,
        string $callbackSignature,
        string $successOutcome,
    ): PaymentConfirmationDecision {
        $key = $paymentIntentId . '|' . $callbackSignature;
        if (isset($this->seen[$key])) {
            return new PaymentConfirmationDecision(
                outcome:         PaymentConfirmationDecision::REPLAY_DETECTED,
                paymentIntentId: $paymentIntentId,
                applicationId:   $applicationId,
                reasonCodes:     ['DUPLICATE_CALLBACK_SIGNATURE'],
            );
        }
        $this->seen[$key] = true;
        return new PaymentConfirmationDecision(
            outcome:         $successOutcome,
            paymentIntentId: $paymentIntentId,
            applicationId:   $applicationId,
        );
    }
}
