<?php

namespace App\Engine;

use App\Models\ServiceDefinition;

/**
 * FeeCalculator — computes service fee from schema fee config.
 *
 * BR-003: Fees calculated from schema fee config, not hardcoded.
 * Supported types: fixed, tiered, formula.
 */
class FeeCalculator
{
    public function __construct(private readonly ServiceDefinition $service) {}

    public function calculate(array $formData): float
    {
        $fee = $this->service->getFeeConfig();

        if (empty($fee)) {
            return 0.0;
        }

        return match ($fee['type'] ?? 'fixed') {
            'tiered'  => $this->tiered($fee, $formData),
            'formula' => $this->formula($fee, $formData),
            default   => (float) ($fee['amount'] ?? 0),
        };
    }

    private function tiered(array $fee, array $formData): float
    {
        $fieldValue = $formData[$fee['field']] ?? null;
        $tiers      = $fee['tiers'] ?? [];
        $default    = (float) ($fee['default'] ?? 0);

        return (float) ($tiers[$fieldValue] ?? $default);
    }

    private function formula(array $fee, array $formData): float
    {
        // Simple formula: base + (rate * field_value)
        $base  = (float) ($fee['base'] ?? 0);
        $rate  = (float) ($fee['rate'] ?? 0);
        $field = $fee['field'] ?? null;
        $value = $field ? ((float) ($formData[$field] ?? 0)) : 0;

        return max(0.0, $base + ($rate * $value));
    }
}
