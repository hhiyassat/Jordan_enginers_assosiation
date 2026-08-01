<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance\Srv001;

use Modules\JeaServices\Engine\ExplorationRequirementMatrix;
use Modules\JeaServices\Governance\ServiceCalculationPolicy;
use Modules\JeaServices\Governance\ServiceCalculationResult;
use Modules\JeaServices\Models\RuleDefinition;
use RuntimeException;

/**
 * SG-06 · adapter — wraps the existing static ExplorationRequirementMatrix
 * as a ServiceCalculationPolicy without changing its numeric behaviour.
 *
 * The rule_version_id is resolved lazily so the DB doesn't need to be
 * seeded before the class is instantiated.
 */
final class LegacyExplorationRequirementMatrixCalculator implements ServiceCalculationPolicy
{
    public const RULE_IDENTIFIER = 'SRV001_EXPLORATION_MATRIX';

    public function ruleIdentifier(): string
    {
        return self::RULE_IDENTIFIER;
    }

    /** @param array<string, mixed> $inputs */
    public function compute(array $inputs): ServiceCalculationResult
    {
        $floors = (int) ($inputs['floor_count'] ?? 0);
        $area   = (float) ($inputs['floor_area'] ?? 0.0);

        $matrix = ExplorationRequirementMatrix::compute($floors, $area);

        return new ServiceCalculationResult(
            ruleVersionId: $this->resolveRuleVersionId(),
            inputs: ['floor_count' => $floors, 'floor_area' => $area],
            outputs: $matrix,
            intermediateValues: ['implementation_identity' => ExplorationRequirementMatrix::class],
        );
    }

    private function resolveRuleVersionId(): int
    {
        $rule = RuleDefinition::query()
            ->where('rule_identifier', self::RULE_IDENTIFIER)
            ->first();
        if ($rule === null) {
            throw new RuntimeException('SG-04 Srv001RulesSeeder must run before LegacyExplorationRequirementMatrixCalculator.');
        }
        $version = $rule->currentEffectiveVersion();
        if ($version === null) {
            throw new RuntimeException('Rule ' . self::RULE_IDENTIFIER . ' has no version — seed misconfigured.');
        }
        return $version->id;
    }
}
