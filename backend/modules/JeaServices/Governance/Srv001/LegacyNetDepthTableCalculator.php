<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance\Srv001;

use Modules\JeaServices\Engine\NetDepthTable as EngineNetDepthTable;
use Modules\JeaServices\Governance\ServiceCalculationPolicy;
use Modules\JeaServices\Governance\ServiceCalculationResult;
use Modules\JeaServices\Models\RuleDefinition;
use RuntimeException;

/**
 * SG-06 · adapter for the PROVISIONAL NetDepthTable.
 *
 * Surfaces the acknowledged unresolved invariant (third + two_thirds ≠ total)
 * via open_decisions so no consumer can rely on the values without
 * seeing the caveat.
 */
final class LegacyNetDepthTableCalculator implements ServiceCalculationPolicy
{
    public const RULE_IDENTIFIER = 'SRV001_NET_DEPTH';

    public function ruleIdentifier(): string
    {
        return self::RULE_IDENTIFIER;
    }

    /** @param array<string, mixed> $inputs */
    public function compute(array $inputs): ServiceCalculationResult
    {
        $floors = (int) ($inputs['floor_count'] ?? 0);
        $result = EngineNetDepthTable::compute($floors);

        return new ServiceCalculationResult(
            ruleVersionId: $this->resolveRuleVersionId(),
            inputs: ['floor_count' => $floors],
            outputs: $result,
            intermediateValues: ['implementation_identity' => EngineNetDepthTable::class],
            openDecisions: [
                'PROVISIONAL — source: محضر اجتماع 2026-07-26 §XI',
                'Unresolved invariant: third + two_thirds ≠ total awaits JEA clarification',
            ],
        );
    }

    private function resolveRuleVersionId(): int
    {
        $rule = RuleDefinition::query()
            ->where('rule_identifier', self::RULE_IDENTIFIER)
            ->first();
        if ($rule === null) {
            throw new RuntimeException('SG-04 Srv001RulesSeeder must run before LegacyNetDepthTableCalculator.');
        }
        $version = $rule->currentEffectiveVersion();
        if ($version === null) {
            throw new RuntimeException('Rule ' . self::RULE_IDENTIFIER . ' has no version — seed misconfigured.');
        }
        return $version->id;
    }
}
