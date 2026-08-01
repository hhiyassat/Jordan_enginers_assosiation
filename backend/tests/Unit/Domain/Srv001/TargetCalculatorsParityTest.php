<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Srv001;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\JeaServices\Database\Seeders\Srv001RulesSeeder;
use Modules\JeaServices\Domain\Srv001\Calculators\TargetExplorationRequirementMatrixCalculator;
use Modules\JeaServices\Domain\Srv001\Calculators\TargetNetDepthTableCalculator;
use Modules\JeaServices\Domain\Srv001\Calculators\TargetWellsCountCalculator;
use Modules\JeaServices\Governance\Srv001\LegacyExplorationRequirementMatrixCalculator;
use Modules\JeaServices\Governance\Srv001\LegacyNetDepthTableCalculator;
use Modules\JeaServices\Governance\Srv001\LegacyWellsCountCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TD-01 · Numeric parity — Target* calculators produce IDENTICAL
 * outputs to Legacy* for every input we test. This is the primary
 * safeguard against inadvertent behaviour drift while the skeleton
 * classes exist alongside the legacy path.
 *
 * If any of these assertions fail, either the target class was
 * modified to change legacy behaviour (FORBIDDEN by JDG-TD00-02) or
 * the legacy class was modified in a way not mirrored (equally
 * FORBIDDEN in TD-01+ scope).
 */
class TargetCalculatorsParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new Srv001RulesSeeder())->run();
    }

    /**
     * @param array{floor_count: int, floor_area: float} $inputs
     */
    #[DataProvider('explorationSamples')]
    public function test_exploration_matrix_parity(array $inputs): void
    {
        $legacy = new LegacyExplorationRequirementMatrixCalculator();
        $target = new TargetExplorationRequirementMatrixCalculator($legacy);

        $legacyResult = $legacy->compute($inputs);
        $targetResult = $target->compute($inputs);

        $this->assertSame($legacyResult->outputs, $targetResult->outputs, 'exploration matrix outputs must match legacy exactly');
        $this->assertSame($legacyResult->ruleVersionId, $targetResult->ruleVersionId);
        $this->assertContains(
            'TARGET_DOMAIN_PROVISIONAL — awaiting OD-Closure for SRS v1.2 §4.1 rows before promotion to BUSINESS_APPROVED',
            $targetResult->openDecisions ?? [],
            'target must add TARGET_DOMAIN_PROVISIONAL marker',
        );
    }

    /** @return list<array{0: array<string, mixed>}> */
    public static function explorationSamples(): array
    {
        return [
            'floors 3, area 150 (§8 example 1)' => [['floor_count' => 3, 'floor_area' => 150.0]],
            'floors 3, area 500 (§8 example 2)' => [['floor_count' => 3, 'floor_area' => 500.0]],
            'floors 4, area 199.99'             => [['floor_count' => 4, 'floor_area' => 199.99]],
            'floors 5, area 900'                => [['floor_count' => 5, 'floor_area' => 900.0]],
            'floors 8, area 1200'               => [['floor_count' => 8, 'floor_area' => 1200.0]],
            'floors 3, area 2000 (special)'     => [['floor_count' => 3, 'floor_area' => 2000.0]],
        ];
    }

    /**
     * @param array{floor_area: float} $inputs
     */
    #[DataProvider('wellsSamples')]
    public function test_wells_count_parity(array $inputs): void
    {
        $legacy = new LegacyWellsCountCalculator();
        $target = new TargetWellsCountCalculator($legacy);

        $legacyResult = $legacy->compute($inputs);
        $targetResult = $target->compute($inputs);

        $this->assertSame($legacyResult->outputs, $targetResult->outputs);
        $this->assertSame($legacyResult->ruleVersionId, $targetResult->ruleVersionId);
        $this->assertNotEmpty($targetResult->openDecisions, 'target must surface OD blockers');
    }

    /** @return list<array{0: array<string, mixed>}> */
    public static function wellsSamples(): array
    {
        return [
            'area 150'  => [['floor_area' => 150.0]],
            'area 500'  => [['floor_area' => 500.0]],
            'area 900'  => [['floor_area' => 900.0]],
            'area 1200' => [['floor_area' => 1200.0]],
            'area 2000' => [['floor_area' => 2000.0]],
            'area 3000' => [['floor_area' => 3000.0]],
        ];
    }

    /**
     * @param array{floor_count: int} $inputs
     */
    #[DataProvider('depthSamples')]
    public function test_net_depth_parity(array $inputs): void
    {
        $legacy = new LegacyNetDepthTableCalculator();
        $target = new TargetNetDepthTableCalculator($legacy);

        $legacyResult = $legacy->compute($inputs);
        $targetResult = $target->compute($inputs);

        $this->assertSame($legacyResult->outputs, $targetResult->outputs);
        $this->assertSame($legacyResult->ruleVersionId, $targetResult->ruleVersionId);
    }

    /** @return list<array{0: array<string, mixed>}> */
    public static function depthSamples(): array
    {
        return [
            'floors 3' => [['floor_count' => 3]],
            'floors 5' => [['floor_count' => 5]],
            'floors 9' => [['floor_count' => 9]],
        ];
    }

    public function test_target_calculators_carry_target_domain_provisional_marker(): void
    {
        $matrixTarget   = new TargetExplorationRequirementMatrixCalculator(new LegacyExplorationRequirementMatrixCalculator());
        $wellsTarget    = new TargetWellsCountCalculator(new LegacyWellsCountCalculator());
        $netDepthTarget = new TargetNetDepthTableCalculator(new LegacyNetDepthTableCalculator());

        $m = $matrixTarget->compute(['floor_count' => 3, 'floor_area' => 500]);
        $w = $wellsTarget->compute(['floor_area' => 500]);
        $d = $netDepthTarget->compute(['floor_count' => 5]);

        $this->assertSame('TARGET_DOMAIN_PROVISIONAL', $m->intermediateValues['target_domain_classification'] ?? null);
        $this->assertSame('TARGET_DOMAIN_PROVISIONAL', $w->intermediateValues['target_domain_classification'] ?? null);
        $this->assertSame('TARGET_DOMAIN_PROVISIONAL', $d->intermediateValues['target_domain_classification'] ?? null);
    }
}
