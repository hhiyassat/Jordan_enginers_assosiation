<?php

namespace Tests\Unit;

use Modules\JeaServices\Engine\WellsCountCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the wells table from the 2026-07-26 JEA meeting minutes §X.
 * If the engine's band boundaries or step formulas drift, these tests fail —
 * catching accidental interpretation changes before they reach production.
 */
class WellsCountCalculatorTest extends TestCase
{
    /** @return array<string, array{float, int}> */
    public static function fixedBandCases(): array
    {
        return [
            'lower edge (0.01 m²)' => [0.01, 2],
            'at 200'               => [200.0, 2],
            'first band jump 201'  => [201.0, 3],
            'at 600'               => [600.0, 3],
            'second jump 601'      => [601.0, 4],
            'at 800'               => [800.0, 4],
            'third jump 801'       => [801.0, 5],
            'at 1000'              => [1000.0, 5],
            'fourth jump 1001'     => [1001.0, 6],
            'at 1200'              => [1200.0, 6],
        ];
    }

    #[DataProvider('fixedBandCases')]
    public function test_fixed_bands_match_the_meeting_table(float $area, int $expected): void
    {
        $r = WellsCountCalculator::compute($area);
        $this->assertSame(WellsCountCalculator::STATUS_CALCULATED, $r['status']);
        $this->assertSame($expected, $r['wells'], "area={$area} should yield {$expected}");
    }

    /** @return array<string, array{float, int}> */
    public static function extendedBandCases(): array
    {
        return [
            // 1201-3000: +1 well per 300 m² above 1200.
            'just past 1200 → +1'                => [1201.0, 7],
            'exactly at 1500 (one 300 step)'     => [1500.0, 7],
            'just past 1500 → +2'                => [1501.0, 8],
            'exactly at 3000 → 12 wells'         => [3000.0, 12],
            // >3000: +1 per 400 m² above 3000.
            'just past 3000 → 13'                => [3001.0, 13],
            'exactly at 3400 → 13'               => [3400.0, 13],
            'just past 3400 → 14'                => [3401.0, 14],
        ];
    }

    #[DataProvider('extendedBandCases')]
    public function test_extended_bands_from_meeting_step_formulas(float $area, int $expected): void
    {
        $r = WellsCountCalculator::compute($area);
        $this->assertSame(WellsCountCalculator::STATUS_CALCULATED, $r['status']);
        $this->assertSame($expected, $r['wells'], "area={$area} should yield {$expected}");
    }

    public function test_non_positive_area_returns_invalid(): void
    {
        $r = WellsCountCalculator::compute(0);
        $this->assertSame(WellsCountCalculator::STATUS_INVALID, $r['status']);
        $this->assertNull($r['wells']);
        $this->assertSame('AREA_MUST_BE_POSITIVE', $r['reason']);
    }

    public function test_band_label_is_returned_for_grouping_and_display(): void
    {
        $this->assertSame('lte_200',      WellsCountCalculator::compute(50)['band']);
        $this->assertSame('a_201_600',    WellsCountCalculator::compute(500)['band']);
        $this->assertSame('a_1001_1200',  WellsCountCalculator::compute(1200)['band']);
        $this->assertSame('a_1201_3000',  WellsCountCalculator::compute(2500)['band']);
        $this->assertSame('gt_3000',      WellsCountCalculator::compute(5000)['band']);
    }
}
