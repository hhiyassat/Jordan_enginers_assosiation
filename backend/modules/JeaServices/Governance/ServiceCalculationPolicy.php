<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

/**
 * SG-05 · Contract for versioned business calculators.
 *
 * An implementation is a pure function of its input array. It MUST NOT
 * hold state, touch the DB, invoke jobs, or emit events. It MUST return
 * a ServiceCalculationResult that references its rule_version_id so
 * downstream snapshot writers can persist the correct provenance.
 *
 * SRV-001 wraps its three legacy calculators via this contract in SG-06.
 */
interface ServiceCalculationPolicy
{
    public function ruleIdentifier(): string;

    /**
     * @param  array<string, mixed>  $inputs
     */
    public function compute(array $inputs): ServiceCalculationResult;
}
