<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Certificates\ValueObjects;

use InvalidArgumentException;

/**
 * TD-07 · Immutable certificate-eligibility decision.
 *
 * ELIGIBLE requires ALL of:
 *   • workflow path complete + approved
 *   • payment completion evidence exists
 *   • all mandatory inspections/clearances exist
 *   • no blocking note active
 *   • certificate rule version is valid
 *   • application still bound to correct service/workflow/rule versions
 *
 * If any check fails, decision is BLOCKED with reason codes.
 *
 * ISSUANCE (as opposed to eligibility) also requires publication
 * authorization + production configuration — see
 * `PRODUCTION_CERTIFICATE_STATUS=BLOCKED` today.
 */
final class CertificateEligibilityDecision
{
    public const ELIGIBLE = 'ELIGIBLE';
    public const BLOCKED  = 'BLOCKED';

    /** @param list<string> $reasonCodes  @param list<string> $blockingOds */
    public function __construct(
        public readonly string $outcome,
        public readonly int $applicationId,
        public readonly array $reasonCodes = [],
        public readonly array $blockingOds = [],
    ) {
        if (! in_array($outcome, [self::ELIGIBLE, self::BLOCKED], true)) {
            throw new InvalidArgumentException("Unknown outcome: {$outcome}");
        }
    }

    public function isEligible(): bool
    {
        return $this->outcome === self::ELIGIBLE;
    }
}
