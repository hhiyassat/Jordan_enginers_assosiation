<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Certificates\ValueObjects;

use InvalidArgumentException;

/**
 * TD-07 · Immutable draft certificate-issuance request.
 *
 * Structural only. `PRODUCTION_CERTIFICATE_ISSUANCE=BLOCKED` —
 * no request is dispatched to the certificate rendering / signing
 * ports at runtime today. The VO exists so an approved workflow
 * can enqueue a request when publication + production authorization
 * land in a later phase.
 */
final class CertificateIssuanceRequest
{
    public const STATUS_DRAFT             = 'DRAFT';
    public const STATUS_PENDING_ISSUANCE  = 'PENDING_ISSUANCE';
    public const STATUS_ISSUED            = 'ISSUED';
    public const STATUS_BLOCKED           = 'BLOCKED';

    /** @param list<string> $reasonCodes */
    public function __construct(
        public readonly string $requestId,
        public readonly int $applicationId,
        public readonly string $certificateRuleVersionId,
        public readonly string $status = self::STATUS_DRAFT,
        public readonly array $reasonCodes = [],
    ) {
        if ($requestId === '' || $certificateRuleVersionId === '') {
            throw new InvalidArgumentException('requestId + certificateRuleVersionId required');
        }
        if (! in_array(
            $status,
            [self::STATUS_DRAFT, self::STATUS_PENDING_ISSUANCE, self::STATUS_ISSUED, self::STATUS_BLOCKED],
            true,
        )) {
            throw new InvalidArgumentException("Unknown status: {$status}");
        }
    }
}
