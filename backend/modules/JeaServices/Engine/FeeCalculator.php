<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

use Modules\JeaServices\Models\ServiceDefinition;

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
        // Backwards-compatible entry: return the total only. Callers
        // that need per-line breakdown use calculateBreakdown() below.
        return $this->calculateBreakdown($formData)['total'];
    }

    /**
     * JORD-65: itemized calculation for the UI's fee preview.
     *
     * Returns:
     *   [
     *     'base'       => 500.00,                 // primary fee (type-driven)
     *     'surcharges' => [
     *       ['code' => 'syndicate_1pct',  'label_ar' => '...', 'label_en' => '...', 'amount' => 5.00],
     *       ['code' => 'drawing_review',  'label_ar' => '...', 'label_en' => '...', 'amount' => 12.00],
     *     ],
     *     'total'      => 517.00,                 // base + sum(surcharges)
     *     'currency'   => 'JOD',
     *   ]
     *
     * When schema.fee.surcharges is missing / empty the shape stays the
     * same but the surcharges array is empty. The old calculate()
     * behaviour is preserved verbatim — no caller change forced.
     *
     * @return array{base: float, surcharges: list<array<string, mixed>>, total: float, currency: string}
     */
    public function calculateBreakdown(array $formData): array
    {
        $fee = $this->service->getFeeConfig();
        $currency = strtoupper((string) ($this->service->currency ?? 'JOD'));

        if (empty($fee)) {
            return ['base' => 0.0, 'surcharges' => [], 'total' => 0.0, 'currency' => $currency];
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

        $baseDecimal = match ($fee['type'] ?? 'fixed') {
            'tiered'   => $this->tiered($fee, $formData),
            'formula'  => $this->formula($fee, $formData),
            'matrix'   => $this->matrix($fee, $formData),
            'per_unit' => $this->perUnit($fee, $formData),
            default    => $this->toDecimal($fee['amount'] ?? 0),
        };
        $base = $this->finalize($baseDecimal);

        // Surcharges are optional; a service without any collapses to
        // total=base, matching the old single-line behavior exactly.
        $surcharges = [];
        $totalDecimal = $baseDecimal;
        foreach ($fee['surcharges'] ?? [] as $s) {
            if (!is_array($s)) continue;
            $amountDecimal = $this->surchargeAmount($s, $baseDecimal, $formData);
            if ($amountDecimal === null) continue;

            $surcharges[] = [
                'code'     => (string) ($s['code'] ?? ''),
                'kind'     => (string) ($s['kind'] ?? ''),
                'label_ar' => (string) ($s['label_ar'] ?? ''),
                'label_en' => (string) ($s['label_en'] ?? ''),
                'amount'   => $this->finalize($amountDecimal),
            ];
            $totalDecimal = bcadd($totalDecimal, $amountDecimal, self::CALC_SCALE);
        }

        return [
            'base'       => $base,
            'surcharges' => $surcharges,
            'total'      => $this->finalize($totalDecimal),
            'currency'   => $currency,
        ];
    }

    /**
     * Compute the amount for a single surcharge entry.
     * Returns the decimal string, or null to skip the entry entirely
     * (unknown kind / malformed shape → not counted, no throw).
     *
     * Supported kinds:
     *   percent_of_base — amount = base × rate  (rate as fraction, 0.01 = 1%)
     *   per_unit        — amount = form[basis] × rate  (with optional min/max)
     */
    private function surchargeAmount(array $s, string $baseDecimal, array $formData): ?string
    {
        $kind = $s['kind'] ?? null;

        if ($kind === 'percent_of_base') {
            $rate = $this->toDecimal($s['rate'] ?? 0);
            return bcmul($baseDecimal, $rate, self::CALC_SCALE);
        }

        if ($kind === 'per_unit') {
            // Reuse perUnit() by synthesizing a fee-shaped array. Keeps the
            // capping / floor logic in one place.
            $synthetic = [
                'basis' => $s['basis'] ?? null,
                'rate'  => $s['rate']  ?? 0,
            ];
            if (array_key_exists('min', $s)) $synthetic['min'] = $s['min'];
            if (array_key_exists('max', $s)) $synthetic['max'] = $s['max'];
            return $this->perUnit($synthetic, $formData);
        }

        // Unknown kind — silently skip. Schema validator refuses these
        // at save time; this branch only fires if a schema was patched
        // by hand outside the validation path.
        return null;
    }

    /**
     * JORD-63: matrix lookup fee — rate = table[key1|key2|...] × basis.
     *
     * Handles the JEA 2025 manual p. 92 fee grid (governorate × building
     * class → JOD/m²). The applicant fills their actual governorate (one
     * of 12) plus building_class; the schema declares a `buckets` map
     * that reduces those to the 2 rate zones the manual actually pins
     * (Amman greater municipality vs. rest of country) before the
     * table lookup.
     *
     * Schema shape:
     *   fee: {
     *     type:    "matrix",
     *     keys:    ["governorate", "building_class"],   // form field ids
     *     buckets: { governorate: { amman: "amman", irbid: "other", ... } },
     *     rates:   { "amman|green_commercial": 3.5, ..., "other|rural_shaabi": 1.5 },
     *     basis:   "area_m2",                            // multiplier field
     *     default: 0                                     // rate if lookup misses
     *   }
     *
     * Missing keys / unknown values fall back to `default` (not to
     * a partial match) — an incomplete form must not silently produce
     * a wrong bill. The submit path enforces `required` on the form
     * fields separately, so this branch only runs on well-formed input.
     */
    private function matrix(array $fee, array $formData): string
    {
        $keys    = is_array($fee['keys'] ?? null)    ? $fee['keys']    : [];
        $rates   = is_array($fee['rates'] ?? null)   ? $fee['rates']   : [];
        $buckets = is_array($fee['buckets'] ?? null) ? $fee['buckets'] : [];
        $basis   = is_string($fee['basis'] ?? null)  ? $fee['basis']   : null;
        $default = $this->toDecimal($fee['default'] ?? 0);

        // Compose the lookup key by joining the (optionally-bucketed) form
        // values with `|`. Any missing / non-scalar value collapses the
        // whole lookup to default — safer than partial-key matching.
        $parts = [];
        foreach ($keys as $key) {
            if (!is_string($key)) return $default;
            $raw = $formData[$key] ?? null;
            if (!is_string($raw) && !is_int($raw)) return $default;
            $bucketMap = is_array($buckets[$key] ?? null) ? $buckets[$key] : [];
            $parts[] = (string) ($bucketMap[$raw] ?? $raw);
        }
        $lookup = implode('|', $parts);

        $rate = array_key_exists($lookup, $rates)
            ? $this->toDecimal($rates[$lookup])
            : $default;

        // Multiply by the basis field (typically area_m2). Missing basis
        // field → 0 fee (design intent: "matrix rate × 0 area = 0", not
        // an error, because a project with 0 area is either a draft or
        // an admin previewing before entering size).
        $basisValue = $basis && (is_numeric($formData[$basis] ?? null))
            ? $this->toDecimal($formData[$basis])
            : '0';

        $total = bcmul($rate, $basisValue, self::CALC_SCALE);

        // Same negative-floor guard as formula() — a schema-authored
        // negative rate * positive area would otherwise refund-shape.
        if (bccomp($total, '0', self::CALC_SCALE) < 0) {
            return '0';
        }
        return $total;
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
     * JORD-64: per-unit metric fee — rate × form_value with optional
     * min/max caps.
     *
     * Handles JEA 2025 manual rules F-02 (solar 4 JOD/kW) and F-03
     * (excavation shoring 3.5 JOD/m² with the review-fee cap of
     * 5000 JOD at 500 fils/m²). Simpler than matrix (single-axis),
     * gains min/max caps which the manual uses for review fees to
     * prevent runaway pricing on very large projects.
     *
     * Schema shape:
     *   fee: {
     *     type:  "per_unit",
     *     basis: "capacity_kw",   // form field id (any numeric)
     *     rate:  4.0,              // JOD per unit
     *     min:   0,                // optional lower cap
     *     max:   null              // optional upper cap (null = uncapped)
     *   }
     *
     * Missing / non-numeric basis → 0 (same rationale as matrix()).
     */
    private function perUnit(array $fee, array $formData): string
    {
        $basis = is_string($fee['basis'] ?? null) ? $fee['basis'] : null;
        $rate  = $this->toDecimal($fee['rate'] ?? 0);

        $basisValue = $basis && is_numeric($formData[$basis] ?? null)
            ? $this->toDecimal($formData[$basis])
            : '0';

        $total = bcmul($rate, $basisValue, self::CALC_SCALE);

        // min/max caps AFTER the rate multiplication. Skipping when
        // the key is absent (not merely null-valued) lets an author
        // omit the cap without accidentally setting it to 0.
        if (array_key_exists('max', $fee) && is_numeric($fee['max'])) {
            $max = $this->toDecimal($fee['max']);
            if (bccomp($total, $max, self::CALC_SCALE) > 0) {
                $total = $max;
            }
        }
        if (array_key_exists('min', $fee) && is_numeric($fee['min'])) {
            $min = $this->toDecimal($fee['min']);
            if (bccomp($total, $min, self::CALC_SCALE) < 0) {
                $total = $min;
            }
        }

        // Negative-floor guard, same as formula() / matrix().
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
