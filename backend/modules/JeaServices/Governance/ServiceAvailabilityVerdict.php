<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

use DateTimeImmutable;
use Modules\JeaServices\Models\ServiceDefinition;

/**
 * SG-02 · Typed availability verdict.
 *
 * A verdict is a snapshot of what the caller may do with a service at the
 * moment of evaluation. It carries booleans for each capability plus reason
 * codes that explain the outcome. Callers MUST honour the booleans; the
 * reason codes are for observability and error reporting.
 */
final class ServiceAvailabilityVerdict
{
    // OK codes
    public const AVAIL_OK                          = 'AVAIL_OK';
    public const AVAIL_LEGACY_STATUS_FALLBACK      = 'AVAIL_LEGACY_STATUS_FALLBACK';
    public const AVAIL_ALLOWED_ADMIN_INSPECTION    = 'AVAIL_ALLOWED_ADMIN_INSPECTION';
    public const AVAIL_ALLOWED_HISTORICAL_ONLY     = 'AVAIL_ALLOWED_HISTORICAL_ONLY';

    // Blocker / hidden codes
    public const AVAIL_HIDDEN_NOT_PUBLISHED        = 'AVAIL_HIDDEN_NOT_PUBLISHED';
    public const AVAIL_HIDDEN_RETIRED              = 'AVAIL_HIDDEN_RETIRED';
    public const AVAIL_HIDDEN_SUSPENDED_FOR_APPLICANT = 'AVAIL_HIDDEN_SUSPENDED_FOR_APPLICANT';
    public const AVAIL_BLOCKED_PLACEHOLDER_FEE     = 'AVAIL_BLOCKED_PLACEHOLDER_FEE';
    public const AVAIL_BLOCKED_PLACEHOLDER_WORKFLOW = 'AVAIL_BLOCKED_PLACEHOLDER_WORKFLOW';
    public const AVAIL_BLOCKED_EFFECTIVE_FROM_FUTURE = 'AVAIL_BLOCKED_EFFECTIVE_FROM_FUTURE';
    public const AVAIL_BLOCKED_LEGACY_STATUS_INACTIVE = 'AVAIL_BLOCKED_LEGACY_STATUS_INACTIVE';

    /**
     * @param  list<string>            $reasonCodes
     */
    public function __construct(
        public readonly string $serviceCode,
        public readonly ?string $serviceVersion,
        public readonly bool $catalogVisible,
        public readonly bool $applicationCreationAllowed,
        public readonly bool $submissionAllowed,
        public readonly bool $paymentAllowed,
        public readonly bool $certificateAllowed,
        public readonly array $reasonCodes,
        public readonly DateTimeImmutable $evaluatedAt,
    ) {
    }

    /**
     * @param  list<string>  $reasonCodes
     */
    public static function forService(
        ServiceDefinition $service,
        bool $catalogVisible,
        bool $applicationCreationAllowed,
        bool $submissionAllowed,
        bool $paymentAllowed,
        bool $certificateAllowed,
        array $reasonCodes,
    ): self {
        return new self(
            serviceCode: $service->code,
            serviceVersion: null, // SG-03 populates this
            catalogVisible: $catalogVisible,
            applicationCreationAllowed: $applicationCreationAllowed,
            submissionAllowed: $submissionAllowed,
            paymentAllowed: $paymentAllowed,
            certificateAllowed: $certificateAllowed,
            reasonCodes: $reasonCodes,
            evaluatedAt: new DateTimeImmutable(),
        );
    }
}
