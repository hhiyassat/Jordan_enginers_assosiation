<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001;

use Modules\JeaServices\Domain\Srv001\Contracts\Srv001CalculationOutcome;
use Modules\JeaServices\Domain\Srv001\Contracts\Srv001ExplorationStatus;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001TypedCalculationResult;
use Modules\JeaServices\Governance\ServiceCalculationResult;
use Modules\JeaServices\Models\RuleVersion;

/**
 * TD-04 · Classifies a `ServiceCalculationResult` into a typed
 * `Srv001CalculationOutcome`.
 *
 * Kept separate from the calculators so:
 *   • calculators stay single-responsibility (numeric compute only)
 *   • outcome classification is testable in isolation without needing
 *     a Domain port or Legacy calculator
 *   • the classifier can evolve as new outcome semantics are added
 *     (e.g., a new CONFLICTED sub-category) without touching every
 *     calculator
 *
 * The classifier decides in this priority order (first match wins):
 *
 *   1. CONFLICTED         — outputs contain a validation error hint
 *   2. INSUFFICIENT_INPUT — required numeric fields missing from inputs
 *   3. NOT_APPLICABLE     — calculator declares its rule doesn't apply
 *   4. MANUAL_REVIEW      — matrix returned SPECIAL_STUDY_REQUIRED
 *   5. BLOCKED            — rule version is REJECTED
 *   6. SIMULATION_ONLY    — rule version is PROVISIONAL or PENDING
 *   7. CALCULATED         — rule version is APPROVED and everything else is well-formed
 *
 * The order matters: a matrix result with SPECIAL_STUDY_REQUIRED
 * status backed by an APPROVED rule version should still classify as
 * MANUAL_REVIEW — the semantic outcome takes precedence over the
 * publication status because the numeric output is intentionally
 * unavailable (the rule ROUTED away from a numeric answer).
 */
final class Srv001CalculatorOutcomeClassifier
{
    /** @var list<string> */
    private const REQUIRED_NUMERIC_KEYS_MATRIX  = ['floor_count', 'floor_area'];

    /** @var list<string> */
    private const REQUIRED_NUMERIC_KEYS_WELLS   = ['floor_area'];

    /** @var list<string> */
    private const REQUIRED_NUMERIC_KEYS_NETDEPTH = ['floor_count'];

    public function classify(ServiceCalculationResult $result): Srv001TypedCalculationResult
    {
        $required = $this->requiredKeysFor($result);
        $missing  = $this->missingKeys($required, $result->inputs);

        if ($this->outputsIndicateConflict($result->outputs)) {
            return new Srv001TypedCalculationResult(
                outcome:                Srv001CalculationOutcome::CONFLICTED,
                numeric:                $result,
                classifierReason:       'العمليّة تعارض قيد المدخلات (CONFLICTED).',
                classificationEvidence: ['conflict_status' => $result->outputs['status'] ?? null],
            );
        }

        if ($missing !== []) {
            return new Srv001TypedCalculationResult(
                outcome:                Srv001CalculationOutcome::INSUFFICIENT_INPUT,
                numeric:                $result,
                classifierReason:       'مدخلات إلزامية ناقصة (INSUFFICIENT_INPUT).',
                classificationEvidence: ['missing_input_keys' => $missing],
            );
        }

        if ($this->outputsIndicateNotApplicable($result->outputs)) {
            return new Srv001TypedCalculationResult(
                outcome:                Srv001CalculationOutcome::NOT_APPLICABLE,
                numeric:                $result,
                classifierReason:       'شروط تفعيل القاعدة غير مستوفاة (NOT_APPLICABLE).',
                classificationEvidence: ['not_applicable_status' => $result->outputs['status'] ?? null],
            );
        }

        if (($result->outputs['status'] ?? null) === Srv001ExplorationStatus::SPECIAL_STUDY_REQUIRED) {
            return new Srv001TypedCalculationResult(
                outcome:                Srv001CalculationOutcome::MANUAL_REVIEW,
                numeric:                $result,
                classifierReason:       'يستوجب مراجعة فنية يدوية (MANUAL_REVIEW).',
                classificationEvidence: [
                    'exploration_matrix_status' => $result->outputs['status'],
                    'reason'                    => $result->outputs['reason'] ?? null,
                ],
            );
        }

        // Rule-version status classification takes the remaining decision.
        $ruleStatus = $this->resolveRuleVersionStatus($result->ruleVersionId);

        return match ($ruleStatus) {
            RuleVersion::STATUS_REJECTED => new Srv001TypedCalculationResult(
                outcome:                Srv001CalculationOutcome::BLOCKED,
                numeric:                $result,
                classifierReason:       'نسخة القاعدة مرفوضة (BLOCKED).',
                classificationEvidence: ['rule_version_status' => $ruleStatus],
            ),

            RuleVersion::STATUS_PROVISIONAL,
            RuleVersion::STATUS_PENDING => new Srv001TypedCalculationResult(
                outcome:                Srv001CalculationOutcome::SIMULATION_ONLY,
                numeric:                $result,
                classifierReason:       'نسخة القاعدة غير مُعتمَدة — للمحاكاة فقط (SIMULATION_ONLY).',
                classificationEvidence: ['rule_version_status' => $ruleStatus],
            ),

            RuleVersion::STATUS_APPROVED => new Srv001TypedCalculationResult(
                outcome:                Srv001CalculationOutcome::CALCULATED,
                numeric:                $result,
                classifierReason:       'حُسِبَت من نسخة قاعدة مُعتمَدة (CALCULATED).',
                classificationEvidence: ['rule_version_status' => $ruleStatus],
            ),

            // Unknown / null (no DB row): treat as SIMULATION_ONLY —
            // safest classification for a synthetic result.
            default => new Srv001TypedCalculationResult(
                outcome:                Srv001CalculationOutcome::SIMULATION_ONLY,
                numeric:                $result,
                classifierReason:       'نسخة القاعدة غير معروفة — للمحاكاة فقط (SIMULATION_ONLY).',
                classificationEvidence: ['rule_version_status' => $ruleStatus ?? 'UNKNOWN'],
            ),
        };
    }

    /** @return list<string> */
    private function requiredKeysFor(ServiceCalculationResult $result): array
    {
        $identity = (string) ($result->intermediateValues['rule_source'] ?? '');
        return match (true) {
            str_contains($identity, 'Matrix')   => self::REQUIRED_NUMERIC_KEYS_MATRIX,
            str_contains($identity, 'Wells')    => self::REQUIRED_NUMERIC_KEYS_WELLS,
            str_contains($identity, 'NetDepth') => self::REQUIRED_NUMERIC_KEYS_NETDEPTH,
            default                             => [],
        };
    }

    /**
     * @param list<string> $required
     * @param array<string, mixed> $inputs
     * @return list<string>
     */
    private function missingKeys(array $required, array $inputs): array
    {
        $missing = [];
        foreach ($required as $key) {
            $v = $inputs[$key] ?? null;
            if ($v === null || (is_numeric($v) && (float) $v <= 0)) {
                $missing[] = $key;
            }
        }
        return $missing;
    }

    /** @param array<string, mixed> $outputs */
    private function outputsIndicateConflict(array $outputs): bool
    {
        return ($outputs['status'] ?? null) === 'CONFLICTED'
            || (isset($outputs['error']) && $outputs['error'] !== null);
    }

    /** @param array<string, mixed> $outputs */
    private function outputsIndicateNotApplicable(array $outputs): bool
    {
        return ($outputs['status'] ?? null) === 'NOT_APPLICABLE';
    }

    private function resolveRuleVersionStatus(int $ruleVersionId): ?string
    {
        if ($ruleVersionId <= 0) {
            return null;
        }
        $rv = RuleVersion::query()->find($ruleVersionId);
        return $rv?->business_approval_status;
    }
}
