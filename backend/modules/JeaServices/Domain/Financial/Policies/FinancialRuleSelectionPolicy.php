<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Financial\Policies;

use Modules\JeaServices\Domain\Financial\ValueObjects\FinancialRuleVersion;

/**
 * TD-08 · Gate that decides whether a financial rule may be used
 * to produce a binding QUOTED result at runtime.
 *
 * FAIL-CLOSED: only `LIFECYCLE_PUBLISHED` rules pass. Every other
 * lifecycle state — DRAFT / PROVISIONAL / SIMULATION_ONLY /
 * UNPUBLISHED / RETIRED — is refused.
 *
 * Consumers building a fee/tax quote MUST call this policy before
 * classifying a quote as QUOTED. If the policy refuses, the
 * downstream quote outcome MUST become SIMULATION_ONLY (for
 * pilot/dual-run) or BLOCKED (if the caller has no simulation
 * purpose).
 */
final class FinancialRuleSelectionPolicy
{
    public function isRuntimeSelectable(FinancialRuleVersion $rule): bool
    {
        return $rule->isPublished();
    }
}
