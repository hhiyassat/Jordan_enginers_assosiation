<?php

namespace Tests\Unit;

use Modules\JeaServices\Engine\GridSystemResolver;
use PHPUnit\Framework\TestCase;

class GridSystemResolverTest extends TestCase
{
    public function test_empty_grid_is_zero_wells(): void
    {
        $r = GridSystemResolver::resolve([]);
        $this->assertSame(0, $r['total_wells']);
        $this->assertSame([], $r['grids']);
    }

    public function test_wells_are_summed_per_grid_square(): void
    {
        // 3 squares: 150 (2 wells) + 500 (3 wells) + 900 (5 wells) = 10 wells.
        $r = GridSystemResolver::resolve([
            ['area_m2' => 150.0],
            ['area_m2' => 500.0],
            ['area_m2' => 900.0],
        ]);
        $this->assertSame(10, $r['total_wells']);
        $this->assertSame(2, $r['grids'][0]['wells']);
        $this->assertSame(3, $r['grids'][1]['wells']);
        $this->assertSame(5, $r['grids'][2]['wells']);
        $this->assertSame('lte_200',   $r['grids'][0]['band']);
        $this->assertSame('a_201_600', $r['grids'][1]['band']);
        $this->assertSame('a_801_1000',$r['grids'][2]['band']);
    }

    public function test_grid_result_differs_from_summed_area_calc(): void
    {
        // Grid of 4 × 500 m² squares = 4 × 3 wells = 12 wells,
        // vs. summing to a single 2000 m² parcel:
        // 6 + ceil((2000-1200)/300) = 6 + 3 = 9 wells.
        // Confirms Grid System is materially different from single-parcel calc.
        $grid = GridSystemResolver::resolve([
            ['area_m2' => 500.0], ['area_m2' => 500.0],
            ['area_m2' => 500.0], ['area_m2' => 500.0],
        ]);
        $this->assertSame(12, $grid['total_wells']);
    }
}
