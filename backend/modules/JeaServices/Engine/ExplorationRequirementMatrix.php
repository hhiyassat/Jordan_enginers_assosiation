<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

/**
 * ExplorationRequirementMatrix — SRV-001 minimum-boreholes calculator.
 *
 * Source: كتاب التعليمات الفنية 2025, جدول رقم (4-1), ص. 230-231.
 * Encodes the ordinary matrix for buildings with <15 floors and area
 * up to 1200 m² per floor. Every cell outside that envelope returns
 * SPECIAL_STUDY_REQUIRED — the calculator never invents a value.
 *
 * Coverage:
 *   • Floors 1..8  × area bands (<200, 200-600, 601-800, 801-1000,
 *     1001-1200) → CALCULATED with (points, total_depth_lm).
 *   • Floors 9..15 (all areas)                       → SPECIAL_STUDY_REQUIRED.
 *     The manual's ordinary table stops at 8 floors; 9..15 lands
 *     between the ordinary table (≤8) and the high-rise table (>15),
 *     which the manual itself does not resolve — hence special study.
 *   • Floors > 15  (any area)                        → SPECIAL_STUDY_REQUIRED.
 *     A separate high-rise depths table exists on p.232 for floor
 *     ranges 16-19, 20-24, … 100-104, but implementing it is out of
 *     scope for this SRV-001 pilot; the guard flags these apps for
 *     JEA technical review instead of computing a value.
 *   • Area > 1200  (any floor count in ordinary matrix) → SPECIAL_STUDY_REQUIRED.
 *   • Transmission towers, solar sites, multi-building sites → out of scope.
 *
 * Pure computation — no I/O, no state. Callers pass floor_count +
 * floor_area (m²) and receive a plain array.
 */
final class ExplorationRequirementMatrix
{
    public const STATUS_CALCULATED            = 'CALCULATED';
    public const STATUS_SPECIAL_STUDY_REQUIRED = 'SPECIAL_STUDY_REQUIRED';

    /**
     * The ordinary pp230 matrix.
     *
     * Structure: [floor_count => [ area_band => [ points, total_depth_lm ] ]]
     * Area bands are the manual's five explicit bands, keyed by upper
     * bound in metres (INT_MAX = >1200 → not present, falls through).
     *
     * @var array<int, array<string, array{int, int}>>
     */
    private const MATRIX = [
        3 => [ // 3 أو أقل — floors 1..3 share the same row
            'lt_200'    => [2, 10],
            'a_200_600' => [3, 18],
            'a_601_800' => [4, 23],
            'a_801_1000'=> [5, 31],
            'a_1001_1200'=> [6, 36],
        ],
        4 => [
            'lt_200'    => [2, 15],
            'a_200_600' => [3, 21],
            'a_601_800' => [4, 27],
            'a_801_1000'=> [5, 36],
            'a_1001_1200'=> [6, 42],
        ],
        5 => [
            'lt_200'    => [2, 18],
            'a_200_600' => [3, 25],
            'a_601_800' => [4, 32],
            'a_801_1000'=> [5, 43],
            'a_1001_1200'=> [6, 50],
        ],
        6 => [
            'lt_200'    => [2, 20],
            'a_200_600' => [3, 28],
            'a_601_800' => [4, 36],
            'a_801_1000'=> [5, 48],
            'a_1001_1200'=> [6, 56],
        ],
        7 => [
            'lt_200'    => [2, 22],
            'a_200_600' => [3, 31],
            'a_601_800' => [4, 40],
            'a_801_1000'=> [5, 53],
            'a_1001_1200'=> [6, 62],
        ],
        8 => [
            'lt_200'    => [2, 23],
            'a_200_600' => [3, 32],
            'a_601_800' => [4, 41],
            'a_801_1000'=> [5, 55],
            'a_1001_1200'=> [6, 64],
        ],
    ];

    /**
     * @return array{
     *   status: string,
     *   minimum_exploration_point_count: int|null,
     *   minimum_total_depth_lm: int|null,
     *   reason?: string
     * }
     */
    public static function compute(int $floorCount, float $floorArea): array
    {
        if ($floorCount < 1 || $floorArea <= 0) {
            return self::specialStudy('INVALID_INPUT');
        }

        if ($floorArea > 1200) {
            return self::specialStudy('AREA_EXCEEDS_ORDINARY_MATRIX');
        }

        // 9..15 sits between the ordinary matrix (≤8) and the high-rise
        // table (>15); 16+ falls under the p.232 high-rise depths table
        // which this pilot deliberately does not implement.
        if ($floorCount > 15) {
            return self::specialStudy('FLOOR_COUNT_HIGH_RISE_OUT_OF_PILOT');
        }
        if ($floorCount >= 9) {
            return self::specialStudy('FLOOR_COUNT_9_TO_15_NO_MANUAL_ROW');
        }

        // Floors 1..3 share the "3 أو أقل" row.
        $rowKey = $floorCount <= 3 ? 3 : $floorCount;

        $band = self::areaBand($floorArea);
        if ($band === null) {
            return self::specialStudy('AREA_BAND_NOT_MAPPED');
        }

        $cell = self::MATRIX[$rowKey][$band] ?? null;
        if ($cell === null) {
            return self::specialStudy('MATRIX_CELL_UNVERIFIED');
        }

        [$points, $depthLm] = $cell;

        return [
            'status'                          => self::STATUS_CALCULATED,
            'minimum_exploration_point_count' => $points,
            'minimum_total_depth_lm'          => $depthLm,
        ];
    }

    private static function areaBand(float $area): ?string
    {
        return match (true) {
            $area < 200                => 'lt_200',
            $area >= 200  && $area <= 600  => 'a_200_600',
            $area >= 601  && $area <= 800  => 'a_601_800',
            $area >= 801  && $area <= 1000 => 'a_801_1000',
            $area >= 1001 && $area <= 1200 => 'a_1001_1200',
            default => null,
        };
    }

    /** @return array{status: string, minimum_exploration_point_count: null, minimum_total_depth_lm: null, reason: string} */
    private static function specialStudy(string $reason): array
    {
        return [
            'status'                          => self::STATUS_SPECIAL_STUDY_REQUIRED,
            'minimum_exploration_point_count' => null,
            'minimum_total_depth_lm'          => null,
            'reason'                          => $reason,
        ];
    }
}
