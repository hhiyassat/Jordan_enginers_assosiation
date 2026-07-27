<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

/**
 * NetDepthTable — net depth per borehole by floor count.
 *
 * Source: [Meeting minutes 2026-07-26](../../../../docs/meetings/2026-07-26-jea-soil-testing.md) §XI.
 * Net depth (العمق الصافي) is the minimum depth per well BEFORE any
 * additive corrections. The minutes give three columns per floor row
 * (third / two-thirds / total). We store all three, but the meeting is
 * ambiguous on how third+two-thirds interact with total.
 *
 * PROVISIONAL — open question #2 in the meeting plan:
 *   For floors ≥ 4, `third + two_thirds ≠ total` (e.g. 4+7=11 but total=10).
 *   So the columns are NOT a simple partition. Two plausible readings:
 *     (a) "Third" and "two-thirds" refer to partial-depth checkpoints
 *         mid-drill (report samples at 1/3 and 2/3 of total), and total
 *         is an independent minimum.
 *     (b) The rows are transcription errors and should be reconciled by
 *         JEA.
 *   We expose all three values; downstream code should read `total_m`
 *   (which is what §XIII financial equations reference as "net depth").
 *
 * Minimum floor count for soil-test eligibility is 3 (§XI). Anything less
 * → INELIGIBLE.
 *
 * Pure computation — no I/O, no state.
 */
final class NetDepthTable
{
    public const STATUS_CALCULATED = 'CALCULATED';
    public const STATUS_INELIGIBLE = 'INELIGIBLE';

    /**
     * @var array<int, array{third: int, two_thirds: int, total: int}>
     */
    private const TABLE = [
        3 => ['third' => 3, 'two_thirds' => 6,  'total' => 9],
        4 => ['third' => 4, 'two_thirds' => 7,  'total' => 10],
        5 => ['third' => 5, 'two_thirds' => 8,  'total' => 12],
        6 => ['third' => 6, 'two_thirds' => 9,  'total' => 13],
        7 => ['third' => 7, 'two_thirds' => 10, 'total' => 14],
        8 => ['third' => 8, 'two_thirds' => 10, 'total' => 15],
        9 => ['third' => 9, 'two_thirds' => 11, 'total' => 16],
    ];

    /**
     * @return array{
     *   status: string,
     *   floors: int,
     *   third_m: int|null,
     *   two_thirds_m: int|null,
     *   total_m: int|null,
     *   reason?: string
     * }
     */
    public static function compute(int $floorCount): array
    {
        if ($floorCount < 3) {
            return self::ineligible($floorCount, 'FLOORS_BELOW_MIN_3');
        }

        // The meeting table stops at 9 floors. Higher floor counts inherit
        // the 9-floor row (safe minimum) but callers should still route
        // high-rise to special study — see ExplorationRequirementMatrix
        // for the >8-floor policy already codified in the pilot.
        $row = self::TABLE[$floorCount] ?? self::TABLE[9];

        return [
            'status'       => self::STATUS_CALCULATED,
            'floors'       => $floorCount,
            'third_m'      => $row['third'],
            'two_thirds_m' => $row['two_thirds'],
            'total_m'      => $row['total'],
        ];
    }

    /**
     * @return array{status: string, floors: int, third_m: null, two_thirds_m: null, total_m: null, reason: string}
     */
    private static function ineligible(int $floors, string $reason): array
    {
        return [
            'status'       => self::STATUS_INELIGIBLE,
            'floors'       => $floors,
            'third_m'      => null,
            'two_thirds_m' => null,
            'total_m'      => null,
            'reason'       => $reason,
        ];
    }
}
