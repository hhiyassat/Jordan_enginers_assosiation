<?php

declare(strict_types=1);

namespace App\Engine;

use App\Models\ServiceDefinition;

/**
 * FeeCalculator — computes service fee from schema fee config.
 *
 * BR-003: Fees calculated from schema fee config, not hardcoded.
 * Supported types: fixed, tiered, formula.
 *
 * JORD-4: money math runs in bcmath decimal strings so we never eat
 * float-precision surprises like 0.1 + 0.2 != 0.3 when the schema
 * ships fractional-JOD tiers. The public surface still returns float
 * for backwards compatibility, but the intermediate arithmetic never
 * touches PHP floats. Also refuses a mismatched currency at the schema
 * boundary: a fee block that says currency=USD on a service with
 * currency=JOD is a schema-authoring bug and would produce a wrong
 * bill silently — better to abort loudly.
 */
class FeeCalculator
{
    /** Precision used inside bcmath calls before final rounding. */
    private const CALC_SCALE = 6;
    /** Decimals persisted on applications.fee_amount (matches the DB cast). */
    private const STORAGE_SCALE = 2;

    public function __construct(private readonly ServiceDefinition $service) {}

    public function calculate(array $formData): float
    {
        $fee = $this->service->getFeeConfig();

        if (empty($fee)) {
            return 0.0;
        }

        // Currency mismatch is a schema-authoring error, not a runtime
        // condition — the schema-structure validator SHOULD catch it
        // before we ever get here, but layering the check keeps the
        // engine self-defensive if a schema is patched by hand.
        $feeCurrency = isset($fee['currency']) && is_string($fee['currency'])
            ? strtoupper($fee['currency'])
            : null;
        $svcCurrency = $this->service->currency
            ? strtoupper((string) $this->service->currency)
            : null;
        if ($feeCurrency !== null && $svcCurrency !== null && $feeCurrency !== $svcCurrency) {
            throw new \InvalidArgumentException(
                "Fee currency mismatch: schema.fee.currency='{$feeCurrency}' but service.currency='{$svcCurrency}'."
            );
        }

        $amount = match ($fee['type'] ?? 'fixed') {
            'tiered'  => $this->tiered($fee, $formData),
            'formula' => $this->formula($fee, $formData),
            default   => $this->toDecimal($fee['amount'] ?? 0),
        };

        return $this->finalize($amount);
    }

    private function tiered(array $fee, array $formData): string
    {
        $fieldValue = $formData[$fee['field'] ?? ''] ?? null;
        $tiers      = is_array($fee['tiers'] ?? null) ? $fee['tiers'] : [];
        $default    = $this->toDecimal($fee['default'] ?? 0);

        // Only string/int keys are meaningful as tier lookups; anything
        // else silently falls back to default rather than tripping the
        // array_key_exists check with an invalid key type.
        if (is_string($fieldValue) || is_int($fieldValue)) {
            if (array_key_exists($fieldValue, $tiers)) {
                return $this->toDecimal($tiers[$fieldValue]);
            }
        }
        return $default;
    }

    private function formula(array $fee, array $formData): string
    {
        // fee = base + (rate * field_value), floored at 0.
        $base  = $this->toDecimal($fee['base'] ?? 0);
        $rate  = $this->toDecimal($fee['rate'] ?? 0);
        $field = is_string($fee['field'] ?? null) ? $fee['field'] : null;
        $raw   = $field ? ($formData[$field] ?? 0) : 0;
        $value = $this->toDecimal($raw);

        $product = bcmul($rate, $value, self::CALC_SCALE);
        $total   = bcadd($base, $product, self::CALC_SCALE);

        // Floor at zero — a schema-authored negative rate * positive value
        // would otherwise produce a refund-shaped fee, which the
        // certificate-issuance flow can't act on.
        if (bccomp($total, '0', self::CALC_SCALE) < 0) {
            return '0';
        }
        return $total;
    }

    /**
     * Coerce arbitrary schema-authored values into a decimal string
     * suitable for bcmath. Non-numeric input (null, arrays, objects)
     * yields '0' rather than raising — schema authoring errors show up
     * as a suspiciously-cheap fee that the admin can spot on the
     * generated preview.
     */
    private function toDecimal(mixed $value): string
    {
        if (is_int($value))    return (string) $value;
        if (is_float($value))  return number_format($value, self::CALC_SCALE, '.', '');
        if (is_string($value) && is_numeric($value)) return $value;
        return '0';
    }

    private function finalize(string $amount): float
    {
        // Round half-up to the storage scale using bcmath. The offset is
        // 0.5 × 10^-storageScale — e.g. 0.005 at 2-decimal storage — added
        // (or subtracted for negatives) before bcmath's implicit
        // truncation to storage scale. This avoids the banker's-rounding
        // drift a plain (float) cast would introduce.
        $offset     = bcdiv('5', bcpow('10', (string) (self::STORAGE_SCALE + 1), 0), self::CALC_SCALE + 2);
        $withOffset = bccomp($amount, '0', self::CALC_SCALE) >= 0
            ? bcadd($amount, $offset, self::CALC_SCALE + 2)
            : bcsub($amount, $offset, self::CALC_SCALE + 2);
        $truncated  = bcadd($withOffset, '0', self::STORAGE_SCALE);

        return (float) $truncated;
    }
}
