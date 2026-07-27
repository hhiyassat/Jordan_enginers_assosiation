<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

/**
 * MultiBuildingResolver — decide how to compute wells when a plot has more
 * than one building.
 *
 * Source: [Meeting minutes 2026-07-26](../../../../docs/meetings/2026-07-26-jea-soil-testing.md) §XII.
 *
 * The applicant answers the question "هل المباني متلاصقة أو المسافة بينها
 * أقل من ضعف مسافة الارتداد؟" (yes/no). We take that answer as the
 * authoritative signal:
 *   yes → treat as a single building, wells calculated on the summed area.
 *   no  → each building calculated separately, wells summed.
 *
 * The engine does NOT infer adjacency from coordinates — that inference
 * lives on the frontend where the surveyor confirms it. This mirrors the
 * meeting's wording: "يُسأل: ... (نعم / لا)".
 *
 * Pure computation — no I/O, no state.
 */
final class MultiBuildingResolver
{
    public const MODE_MERGED   = 'MERGED';
    public const MODE_SEPARATE = 'SEPARATE';

    /**
     * @param  bool                             $mergeAsOne
     * @param  array<int, array{area_m2: float}> $buildings
     * @return array{
     *   mode: string,
     *   buildings: array<int, array{area_m2: float, wells: int}>,
     *   total_wells: int
     * }
     */
    public static function resolve(bool $mergeAsOne, array $buildings): array
    {
        if ($buildings === []) {
            return ['mode' => self::MODE_SEPARATE, 'buildings' => [], 'total_wells' => 0];
        }

        if ($mergeAsOne) {
            $totalArea = array_sum(array_map(static fn (array $b): float => (float) $b['area_m2'], $buildings));
            $result    = WellsCountCalculator::compute($totalArea);
            $wells     = (int) ($result['wells'] ?? 0);

            return [
                'mode'      => self::MODE_MERGED,
                'buildings' => [['area_m2' => $totalArea, 'wells' => $wells]],
                'total_wells' => $wells,
            ];
        }

        $rows  = [];
        $total = 0;
        foreach ($buildings as $b) {
            $r     = WellsCountCalculator::compute((float) $b['area_m2']);
            $wells = (int) ($r['wells'] ?? 0);
            $rows[] = ['area_m2' => (float) $b['area_m2'], 'wells' => $wells];
            $total += $wells;
        }

        return ['mode' => self::MODE_SEPARATE, 'buildings' => $rows, 'total_wells' => $total];
    }
}
