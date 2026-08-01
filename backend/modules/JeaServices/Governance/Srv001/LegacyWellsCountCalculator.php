<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance\Srv001;

use Modules\JeaServices\Engine\WellsCountCalculator as EngineWellsCountCalculator;
use Modules\JeaServices\Governance\ServiceCalculationPolicy;
use Modules\JeaServices\Governance\ServiceCalculationResult;
use Modules\JeaServices\Models\RuleDefinition;
use RuntimeException;

/**
 * SG-06 · adapter for the PROVISIONAL WellsCountCalculator.
 *
 * The `open_decisions` array carries the note that this calculator's
 * numeric outputs are sourced from meeting minutes and are not JEA-signed
 * — a governance signal that downstream consumers (reviewers, reports)
 * can surface without duplicating the disclaimer in every place.
 */
final class LegacyWellsCountCalculator implements ServiceCalculationPolicy
{
    public const RULE_IDENTIFIER = 'SRV001_WELLS_COUNT';

    public function ruleIdentifier(): string
    {
        return self::RULE_IDENTIFIER;
    }

    /** @param array<string, mixed> $inputs */
    public function compute(array $inputs): ServiceCalculationResult
    {
        $area   = (float) ($inputs['floor_area'] ?? 0.0);
        $result = EngineWellsCountCalculator::compute($area);

        return new ServiceCalculationResult(
            ruleVersionId: $this->resolveRuleVersionId(),
            inputs: ['floor_area' => $area],
            outputs: $result,
            intermediateValues: ['implementation_identity' => EngineWellsCountCalculator::class],
            openDecisions: [
                'PROVISIONAL — source: محضر اجتماع 2026-07-26 §X (not JEA-signed)',
            ],
        );
    }

    private function resolveRuleVersionId(): int
    {
        $rule = RuleDefinition::query()
            ->where('rule_identifier', self::RULE_IDENTIFIER)
            ->first();
        if ($rule === null) {
            throw new RuntimeException('SG-04 Srv001RulesSeeder must run before LegacyWellsCountCalculator.');
        }
        $version = $rule->currentEffectiveVersion();
        if ($version === null) {
            throw new RuntimeException('Rule ' . self::RULE_IDENTIFIER . ' has no version — seed misconfigured.');
        }
        return $version->id;
    }
}
