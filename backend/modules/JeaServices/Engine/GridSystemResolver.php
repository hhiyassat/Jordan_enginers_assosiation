<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

/**
 * GridSystemResolver — soil-testing for plots divided into a grid of squares.
 *
 * Source: [Meeting minutes 2026-07-26](../../../../docs/meetings/2026-07-26-jea-soil-testing.md) §XII.
 *
 * Rule: the plot is partitioned into grid squares; each square's wells
 * count is computed independently via WellsCountCalculator, then summed.
 * This is the coarsest-grained special-case rule — it does NOT combine
 * with the multi-building merge rule (a plot either uses the grid system
 * OR the multi-building rule, not both).
 *
 * Pure computation — no I/O, no state.
 */
final class GridSystemResolver
{
    /**
     * @param  array<int, array{area_m2: float}> $grids
     * @return array{
     *   grids: array<int, array{area_m2: float, wells: int, band: string|null}>,
     *   total_wells: int
     * }
     */
    public static function resolve(array $grids): array
    {
        $rows  = [];
        $total = 0;

        foreach ($grids as $g) {
            $r = WellsCountCalculator::compute((float) $g['area_m2']);
            $w = (int) ($r['wells'] ?? 0);
            $rows[] = [
                'area_m2' => (float) $g['area_m2'],
                'wells'   => $w,
                'band'    => $r['band'] ?? null,
            ];
            $total += $w;
        }

        return ['grids' => $rows, 'total_wells' => $total];
    }
}
