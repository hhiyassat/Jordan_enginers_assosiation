<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-05 · Port for verifying that the applying office is eligible to
 * submit an SRV-001 application. Concerns include: office is active,
 * office registration is not suspended, office is registered with JEA.
 *
 * FAIL-CLOSED: every non-ELIGIBLE outcome (including provider errors)
 * must block submission. Callers MUST use `Srv001PortDecision::
 * isPermissive()` — never inspect the outcome string directly.
 */
interface OfficeEligibilityPort
{
    public function evaluateOffice(int $organizationId, string $correlationId): Srv001PortDecision;
}
