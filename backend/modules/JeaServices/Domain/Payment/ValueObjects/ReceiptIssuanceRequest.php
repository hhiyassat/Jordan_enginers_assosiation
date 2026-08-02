<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Payment\ValueObjects;

use InvalidArgumentException;
use Modules\JeaServices\Domain\Financial\ValueObjects\FeeQuote;
use Modules\JeaServices\Domain\Financial\ValueObjects\TaxQuote;

/**
 * TD-08 · Immutable receipt-issuance request.
 *
 * FAIL-CLOSED: construction requires:
 *   • a CONFIRMED PaymentConfirmationDecision
 *   • the FeeQuote + TaxQuote snapshots that priced the request
 *
 * The snapshots are FROZEN in the receipt — any later mutation of
 * the fee/tax rules leaves this receipt bound to its original
 * snapshot. Historical evidence never drifts.
 */
final class ReceiptIssuanceRequest
{
    public function __construct(
        public readonly string $receiptId,
        public readonly int $applicationId,
        public readonly PaymentConfirmationDecision $paymentConfirmation,
        public readonly FeeQuote $feeQuoteSnapshot,
        public readonly TaxQuote $taxQuoteSnapshot,
        public readonly string $issuedTimestamp,
    ) {
        if ($receiptId === '' || $issuedTimestamp === '') {
            throw new InvalidArgumentException('receiptId + issuedTimestamp required');
        }
        if (! $paymentConfirmation->unlocksReceiptFlow()) {
            throw new InvalidArgumentException(
                'ReceiptIssuanceRequest requires a CONFIRMED PaymentConfirmationDecision',
            );
        }
    }
}
