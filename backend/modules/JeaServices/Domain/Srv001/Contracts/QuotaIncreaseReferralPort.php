<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-05 · Port for the FR-SS-081 quota-increase referral flow —
 * when an office has hit its yearly quota, it may submit a referral
 * requesting additional capacity.
 *
 * The port creates the referral (structural today; no runtime
 * activation) and returns a decision describing whether the referral
 * was accepted for processing.
 */
interface QuotaIncreaseReferralPort
{
    public function createReferral(
        int $organizationId,
        int $requestedM2Increase,
        string $justificationText,
        string $correlationId,
    ): Srv001PortDecision;
}
