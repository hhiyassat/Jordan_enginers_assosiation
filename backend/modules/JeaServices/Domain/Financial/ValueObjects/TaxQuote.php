<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Financial\ValueObjects;

use InvalidArgumentException;

/**
 * TD-08 · Immutable tax quote.
 *
 * Structurally parallel to FeeQuote. Tax computations that depend
 * on OD-01 (income-tax basis), OD-10 (tax-collection model), or
 * OD-35 (regional exemption effective date) MUST return outcome=
 * BLOCKED with the OD id in blockingOds — never a fabricated tax
 * amount.
 */
final class TaxQuote
{
    /**
     * @param array<string, mixed>       $inputs
     * @param list<array<string, mixed>> $taxLines
     * @param array<string, mixed>       $exemptionEvidence
     * @param list<string>               $blockingOds
     */
    public function __construct(
        public readonly string $outcome,
        public readonly FinancialRuleVersion $ruleVersion,
        public readonly array $inputs,
        public readonly string $unit,
        public readonly string $currency,
        public readonly string $roundingRule,
        public readonly array $taxLines,
        public readonly array $exemptionEvidence,
        public readonly array $blockingOds,
        public readonly string $generatedTimestamp,
    ) {
        if (! Srv001FinancialOutcome::isValid($outcome)) {
            throw new InvalidArgumentException("Unknown outcome: {$outcome}");
        }
        if ($outcome === Srv001FinancialOutcome::QUOTED) {
            if ($unit === '' || $currency === '' || $roundingRule === '') {
                throw new InvalidArgumentException(
                    'QUOTED tax requires non-empty unit + currency + roundingRule (OD-19 gate)',
                );
            }
        }
    }

    public function isBinding(): bool
    {
        return $this->outcome === Srv001FinancialOutcome::QUOTED
            && $this->ruleVersion->isPublished();
    }
}
