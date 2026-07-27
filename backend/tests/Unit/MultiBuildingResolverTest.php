<?php

namespace Tests\Unit;

use Modules\JeaServices\Engine\MultiBuildingResolver;
use PHPUnit\Framework\TestCase;

class MultiBuildingResolverTest extends TestCase
{
    public function test_empty_input_returns_zero_wells(): void
    {
        $r = MultiBuildingResolver::resolve(true, []);
        $this->assertSame(MultiBuildingResolver::MODE_SEPARATE, $r['mode']);
        $this->assertSame(0, $r['total_wells']);
        $this->assertSame([], $r['buildings']);
    }

    public function test_merge_sums_areas_then_calculates_once(): void
    {
        // Two buildings, 500 + 700 = 1200 m² → single-well band = 6 wells.
        $r = MultiBuildingResolver::resolve(true, [
            ['area_m2' => 500.0],
            ['area_m2' => 700.0],
        ]);
        $this->assertSame(MultiBuildingResolver::MODE_MERGED, $r['mode']);
        $this->assertCount(1, $r['buildings']);
        $this->assertSame(1200.0, $r['buildings'][0]['area_m2']);
        $this->assertSame(6, $r['buildings'][0]['wells']);
        $this->assertSame(6, $r['total_wells']);
    }

    public function test_separate_calculates_each_building_then_sums(): void
    {
        // 500 m² → 3 wells, 700 m² → 4 wells. Total 7.
        $r = MultiBuildingResolver::resolve(false, [
            ['area_m2' => 500.0],
            ['area_m2' => 700.0],
        ]);
        $this->assertSame(MultiBuildingResolver::MODE_SEPARATE, $r['mode']);
        $this->assertCount(2, $r['buildings']);
        $this->assertSame(3, $r['buildings'][0]['wells']);
        $this->assertSame(4, $r['buildings'][1]['wells']);
        $this->assertSame(7, $r['total_wells']);
    }

    public function test_merged_result_differs_from_separate_when_bands_cross(): void
    {
        // 200 + 200 = 400 (merged → 3 wells) vs. two 200s separately (2+2=4).
        // Confirms the merge rule is not a wash; the choice matters.
        $merged   = MultiBuildingResolver::resolve(true,  [['area_m2' => 200.0], ['area_m2' => 200.0]]);
        $separate = MultiBuildingResolver::resolve(false, [['area_m2' => 200.0], ['area_m2' => 200.0]]);
        $this->assertSame(3, $merged['total_wells']);
        $this->assertSame(4, $separate['total_wells']);
    }
}
