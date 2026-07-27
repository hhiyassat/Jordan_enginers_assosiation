<?php

namespace Tests\Unit;

use Modules\JeaServices\Engine\NetDepthTable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the net-depth-per-well table from the 2026-07-26 JEA meeting §XI.
 * Ambiguity around third/two-thirds meaning is documented in NetDepthTable's
 * class-doc — these tests only enforce the table values as stated.
 */
class NetDepthTableTest extends TestCase
{
    /** @return array<string, array{int, int, int, int}> */
    public static function meetingTableRows(): array
    {
        return [
            '3 floors' => [3, 3,  6,  9],
            '4 floors' => [4, 4,  7,  10],
            '5 floors' => [5, 5,  8,  12],
            '6 floors' => [6, 6,  9,  13],
            '7 floors' => [7, 7,  10, 14],
            '8 floors' => [8, 8,  10, 15],
            '9 floors' => [9, 9,  11, 16],
        ];
    }

    #[DataProvider('meetingTableRows')]
    public function test_row_matches_meeting_table(int $floors, int $third, int $twoThirds, int $total): void
    {
        $r = NetDepthTable::compute($floors);
        $this->assertSame(NetDepthTable::STATUS_CALCULATED, $r['status']);
        $this->assertSame($floors,    $r['floors']);
        $this->assertSame($third,     $r['third_m']);
        $this->assertSame($twoThirds, $r['two_thirds_m']);
        $this->assertSame($total,     $r['total_m']);
    }

    public function test_floors_below_three_are_ineligible(): void
    {
        foreach ([-1, 0, 1, 2] as $f) {
            $r = NetDepthTable::compute($f);
            $this->assertSame(NetDepthTable::STATUS_INELIGIBLE, $r['status'], "floors={$f}");
            $this->assertSame('FLOORS_BELOW_MIN_3',             $r['reason']);
            $this->assertNull($r['total_m']);
        }
    }

    public function test_floor_count_above_nine_inherits_nine_row_conservatively(): void
    {
        $r = NetDepthTable::compute(12);
        $this->assertSame(NetDepthTable::STATUS_CALCULATED, $r['status']);
        // Uses the 9-floor row values (safe minimum); high-rise routing
        // is expected to happen upstream via ExplorationRequirementMatrix.
        $this->assertSame(16, $r['total_m']);
    }
}
