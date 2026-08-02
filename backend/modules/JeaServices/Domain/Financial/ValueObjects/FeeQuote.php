<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Financial\ValueObjects;

use InvalidArgumentException;

/**
 * TD-08 · Immutable fee quote.
 *
 * Carries EVERY field the mandate lists — no shortcuts. Callers
 * MUST NOT pluck individual fields and re-persist without the
 * complete envelope: audit history depends on the full snapshot.
 *
 * INVARIANT: unit + currency + rounding rule are required strings.
 * If the caller does not know them (OD-19 unresolved), the outcome
 * MUST be `INSUFFICIENT_INPUT` or `BLOCKED` — never QUOTED with
 * silently defaulted currency/unit/rounding.
 *
 * FAIL-CLOSED: `isBinding()` returns true ONLY when outcome=QUOTED
 * AND ruleVersion is published.
 */
final class FeeQuote
{
    /**
     * @param array<string, mixed>          $inputs
     * @param list<array<string, mixed>>    $lineItems       [{label, amount, currency}]
     * @param list<array<string, mixed>>    $taxLines        [{label, base, rate, amount, currency}]
     * @param array<string, mixed>          $exemptionEvidence
     * @param list<string>                  $blockingOds
     * @param string                        $unit            e.g. 'lm', 'm2', 'unit'
     * @param string                        $currency        ISO 4217 (e.g. 'JOD')
     * @param string                        $roundingRule    e.g. 'HALF_UP_2', 'FLOOR_0', 'CUSTOM_JOD_100_FILS'
     */
    public function __construct(
        public readonly string $outcome,
        public readonly FinancialRuleVersion $ruleVersion,
        public readonly array $inputs,
        public readonly string $unit,
        public readonly string $currency,
        public readonly string $roundingRule,
        public readonly array $lineItems,
        public readonly array $taxLines,
        public readonly array $exemptionEvidence,
        public readonly array $blockingOds,
        public readonly string $generatedTimestamp,
    ) {
        if (! Srv001FinancialOutcome::isValid($outcome)) {
            throw new InvalidArgumentException("Unknown outcome: {$outcome}");
        }
        if ($generatedTimestamp === '') {
            throw new InvalidArgumentException('generatedTimestamp required');
        }
        // If outcome is QUOTED, unit + currency + rounding MUST be
        // non-empty. This is where OD-19 blocks — quotes without a
        // signed currency/unit/rounding decision cannot be QUOTED.
        if ($outcome === Srv001FinancialOutcome::QUOTED) {
            if ($unit === '' || $currency === '' || $roundingRule === '') {
                throw new InvalidArgumentException(
                    'QUOTED fee requires non-empty unit + currency + roundingRule (OD-19 gate)',
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
