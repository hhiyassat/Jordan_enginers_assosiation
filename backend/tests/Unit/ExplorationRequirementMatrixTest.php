<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\JeaServices\Engine\ExplorationRequirementMatrix;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins every example row cited in the SRV-001 directive §8 plus the
 * out-of-scope branches (>1200m², floors >=9, invalid input).
 *
 * The matrix is a pure function; no DB, no Laravel container.
 */
class ExplorationRequirementMatrixTest extends TestCase
{
    #[DataProvider('directiveExamples')]
    public function test_directive_examples_calculate_correctly(
        int $floorCount,
        float $floorArea,
        int $expectedPoints,
        int $expectedDepth,
    ): void {
        $r = ExplorationRequirementMatrix::compute($floorCount, $floorArea);
        $this->assertSame(
            ExplorationRequirementMatrix::STATUS_CALCULATED,
            $r['status'],
            "floors={$floorCount} area={$floorArea} must resolve to CALCULATED",
        );
        $this->assertSame($expectedPoints, $r['minimum_exploration_point_count']);
        $this->assertSame($expectedDepth, $r['minimum_total_depth_lm']);
    }

    /** @return list<array{int, float, int, int}> */
    public static function directiveExamples(): array
    {
        return [
            // §8 example 1: floors <= 3, area < 200 → 2 points, 10 lm
            'floors <=3, area <200'    => [3, 150, 2, 10],
            'floors 1, area <200'      => [1, 150, 2, 10],
            // §8 example 2: floors <= 3, area 200-600 → 3 points, 18 lm
            'floors <=3, area 200-600' => [3, 500, 3, 18],
            // §8 example 3: floors = 4, area < 200 → 2 points, 15 lm
            'floors 4, area <200'      => [4, 199.99, 2, 15],
            // §8 example 4: floors = 5, area 801-1000 → 5 points, 43 lm
            'floors 5, area 801-1000'  => [5, 900, 5, 43],
            // §8 example 5: floors = 8, area 1001-1200 → 6 points, 64 lm
            'floors 8, area 1001-1200' => [8, 1200, 6, 64],
            // Additional pins covering row edges directly from the manual
            // pp.230 table so subtle off-by-one bugs surface immediately.
            'floors 4, area 601-800'   => [4, 700, 4, 27],
            'floors 6, area <200'      => [6, 100, 2, 20],
            'floors 7, area 1001-1200' => [7, 1100, 6, 62],
        ];
    }

    public function test_area_greater_than_1200_triggers_special_study(): void
    {
        $r = ExplorationRequirementMatrix::compute(3, 1200.01);
        $this->assertSame(
            ExplorationRequirementMatrix::STATUS_SPECIAL_STUDY_REQUIRED,
            $r['status'],
        );
        $this->assertNull($r['minimum_exploration_point_count']);
        $this->assertNull($r['minimum_total_depth_lm']);
        $this->assertSame('AREA_EXCEEDS_ORDINARY_MATRIX', $r['reason']);
    }

    /**
     * §3 boundary suite — pins the exact behavior at every floor count
     * that sits on a matrix edge. 8 → last ordinary row, 9 → first
     * gap-in-manual row, 15 → last gap-in-manual row, 16 → first
     * high-rise row (out of pilot). Each expected status + reason is
     * asserted explicitly so a shift in the boundary breaks the test.
     */
    #[DataProvider('floorBoundaryCases')]
    public function test_floor_boundary(
        int $floors,
        string $expectedStatus,
        ?string $expectedReason,
    ): void {
        $r = ExplorationRequirementMatrix::compute($floors, 500);
        $this->assertSame($expectedStatus, $r['status'],
            "floors={$floors} must be {$expectedStatus}");
        if ($expectedReason !== null) {
            $this->assertSame($expectedReason, $r['reason'] ?? null,
                "floors={$floors} reason mismatch");
            $this->assertNull($r['minimum_exploration_point_count']);
            $this->assertNull($r['minimum_total_depth_lm']);
        }
    }

    /** @return list<array{int, string, string|null}> */
    public static function floorBoundaryCases(): array
    {
        return [
            'floor 8 = last ordinary row'          => [8,  ExplorationRequirementMatrix::STATUS_CALCULATED,            null],
            'floor 9 = first no-manual-row gap'    => [9,  ExplorationRequirementMatrix::STATUS_SPECIAL_STUDY_REQUIRED, 'FLOOR_COUNT_9_TO_15_NO_MANUAL_ROW'],
            'floor 15 = last no-manual-row gap'    => [15, ExplorationRequirementMatrix::STATUS_SPECIAL_STUDY_REQUIRED, 'FLOOR_COUNT_9_TO_15_NO_MANUAL_ROW'],
            'floor 16 = first high-rise row'       => [16, ExplorationRequirementMatrix::STATUS_SPECIAL_STUDY_REQUIRED, 'FLOOR_COUNT_HIGH_RISE_OUT_OF_PILOT'],
            'floor 30 = well inside high-rise'     => [30, ExplorationRequirementMatrix::STATUS_SPECIAL_STUDY_REQUIRED, 'FLOOR_COUNT_HIGH_RISE_OUT_OF_PILOT'],
        ];
    }

    public function test_special_study_never_invents_a_value(): void
    {
        foreach ([
            [3, 5000.0],
            [15, 500.0],
            [30, 100.0],
            [1, 1500.0],
        ] as [$floors, $area]) {
            $r = ExplorationRequirementMatrix::compute($floors, $area);
            $this->assertSame(
                ExplorationRequirementMatrix::STATUS_SPECIAL_STUDY_REQUIRED,
                $r['status'],
            );
            $this->assertNull($r['minimum_exploration_point_count'],
                "floors={$floors} area={$area}: must NOT invent a value");
            $this->assertNull($r['minimum_total_depth_lm']);
        }
    }

    public function test_invalid_input_returns_special_study(): void
    {
        $r = ExplorationRequirementMatrix::compute(0, 100);
        $this->assertSame(
            ExplorationRequirementMatrix::STATUS_SPECIAL_STUDY_REQUIRED,
            $r['status'],
        );
        $this->assertSame('INVALID_INPUT', $r['reason']);
    }
}
