<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

/**
 * WellsCountCalculator — required borehole count per building area.
 *
 * Source: [Meeting minutes 2026-07-26](../../../../docs/meetings/2026-07-26-jea-soil-testing.md) §X.
 * Extends `ExplorationRequirementMatrix` (which stops at 1200 m²) into the
 * higher area bands the JEA soil-testing department confirmed in the meeting.
 *
 * Table (§X):
 *   0    – 200   →  2 wells
 *   201  – 600   →  3
 *   601  – 800   →  4
 *   801  – 1000  →  5
 *   1001 – 1200  →  6
 *   1201 – 3000  → +1 well per 300 m² of area ABOVE 1200
 *   >    3000    → +1 well per 400 m² of area ABOVE 3000 (on top of the
 *                   12 wells that 3000 m² already earns)
 *
 * PROVISIONAL — open question #1 in the meeting plan:
 *   The minutes say "بئر إضافي عن كل 300 م²" for 1201-3000. We interpret
 *   this as `6 + ceil((area - 1200) / 300)` — i.e. the +1 lands the moment
 *   you cross the next 300 m² threshold above 1200. Alternative reading
 *   ("floor((area - 1200) / 300)" without the ceil, or "starting from
 *   area/300 not from 1200") is possible; JEA to confirm.
 *
 * Pure computation — no I/O, no state.
 */
final class WellsCountCalculator
{
    public const STATUS_CALCULATED = 'CALCULATED';
    public const STATUS_INVALID    = 'INVALID';

    /**
     * @return array{status: string, wells: int|null, band: string|null, reason?: string}
     */
    public static function compute(float $areaM2): array
    {
        if ($areaM2 <= 0) {
            return self::invalid('AREA_MUST_BE_POSITIVE');
        }

        if ($areaM2 <= 200) {
            return self::ok(2, 'lte_200');
        }
        if ($areaM2 <= 600) {
            return self::ok(3, 'a_201_600');
        }
        if ($areaM2 <= 800) {
            return self::ok(4, 'a_601_800');
        }
        if ($areaM2 <= 1000) {
            return self::ok(5, 'a_801_1000');
        }
        if ($areaM2 <= 1200) {
            return self::ok(6, 'a_1001_1200');
        }

        // 1201–3000: +1 well per 300 m² above 1200. `ceil` matches
        // "cross the threshold, take the +1" reading — see class comment.
        if ($areaM2 <= 3000) {
            $extra = (int) ceil(($areaM2 - 1200) / 300);
            return self::ok(6 + $extra, 'a_1201_3000');
        }

        // >3000: keep the 12 wells that 3000 earns, then +1 per 400 above.
        $wellsAt3000 = 6 + (int) ceil((3000 - 1200) / 300); // = 12
        $extra       = (int) ceil(($areaM2 - 3000) / 400);
        return self::ok($wellsAt3000 + $extra, 'gt_3000');
    }

    /**
     * @return array{status: string, wells: int, band: string}
     */
    private static function ok(int $wells, string $band): array
    {
        return ['status' => self::STATUS_CALCULATED, 'wells' => $wells, 'band' => $band];
    }

    /**
     * @return array{status: string, wells: null, band: null, reason: string}
     */
    private static function invalid(string $reason): array
    {
        return ['status' => self::STATUS_INVALID, 'wells' => null, 'band' => null, 'reason' => $reason];
    }
}
