<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Payment\ValueObjects;

use InvalidArgumentException;

/**
 * TD-08 · Immutable financial-correction request.
 *
 * Distinct from a pre-payment return (which uses PartialEditGrant):
 * a financial correction after payment must go through this typed
 * request + a governed audit path. Structural today; no runtime
 * consumer.
 */
final class FinancialCorrectionRequest
{
    public const STATUS_DRAFT      = 'DRAFT';
    public const STATUS_PENDING    = 'PENDING';
    public const STATUS_APPROVED   = 'APPROVED';
    public const STATUS_REJECTED   = 'REJECTED';

    /** @param list<string> $reasonCodes */
    public function __construct(
        public readonly string $requestId,
        public readonly int $applicationId,
        public readonly int $requestingActorId,
        public readonly string $requestingRole,
        public readonly string $reason,
        public readonly string $status = self::STATUS_DRAFT,
        public readonly array $reasonCodes = [],
    ) {
        if ($requestId === '' || $reason === '' || $requestingRole === '') {
            throw new InvalidArgumentException('required string fields missing');
        }
        if (! in_array(
            $status,
            [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED],
            true,
        )) {
            throw new InvalidArgumentException("Unknown status: {$status}");
        }
    }
}
