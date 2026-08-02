<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\DualRun;

use Modules\JeaServices\Domain\DualRun\ValueObjects\DualRunResult;
use Throwable;

/**
 * TD-10 · Pure classifier — takes normalized inputs + a legacy
 * result + a target-simulation callable, and returns a
 * `DualRunResult` without ever calling either the real target
 * runtime or any external system.
 *
 * INVARIANTS:
 *   • the classifier NEVER touches the DB
 *   • the classifier NEVER makes external calls
 *   • the classifier NEVER mutates the passed inputs
 *   • if the target callable throws, classification is EXECUTION_ERROR
 *   • target callable is expected to return an array shaped
 *     `['decision' => string, 'derived' => array, 'blockers' => list]`
 */
final class DualRunClassifier
{
    /**
     * @param callable(array<string, mixed>): array<string, mixed> $targetSimulator
     * @param array<string, mixed> $normalizedInput
     * @param array<string, mixed> $legacyResult
     */
    public function classify(
        string $caseId,
        array $normalizedInput,
        array $legacyResult,
        callable $targetSimulator,
    ): DualRunResult {
        $targetResult = null;
        try {
            $targetResult = $targetSimulator($normalizedInput);
        } catch (Throwable $e) {
            return new DualRunResult(
                caseId:                  $caseId,
                classification:          DualRunResult::EXECUTION_ERROR,
                normalizedInput:         $normalizedInput,
                legacyResult:            $legacyResult,
                targetResult:            ['error' => $e::class, 'message' => $e->getMessage()],
                targetWriteCount:        0,
                targetExternalCallCount: 0,
                reasonCodes:             ['TARGET_SIMULATOR_THREW'],
            );
        }

        // Target explicitly BLOCKED_BY_OD? classify as BLOCKED_TARGET_RULE.
        if (($targetResult['decision'] ?? null) === 'BLOCKED_BY_OD') {
            return new DualRunResult(
                caseId:                  $caseId,
                classification:          DualRunResult::BLOCKED_TARGET_RULE,
                normalizedInput:         $normalizedInput,
                legacyResult:            $legacyResult,
                targetResult:            $targetResult,
                targetWriteCount:        0,
                targetExternalCallCount: 0,
                reasonCodes:             $targetResult['blockers'] ?? [],
            );
        }
        if (($targetResult['decision'] ?? null) === 'SIMULATION_ONLY') {
            return new DualRunResult(
                caseId:                  $caseId,
                classification:          DualRunResult::EXPECTED_PROVISIONAL_DIFFERENCE,
                normalizedInput:         $normalizedInput,
                legacyResult:            $legacyResult,
                targetResult:            $targetResult,
                targetWriteCount:        0,
                targetExternalCallCount: 0,
                reasonCodes:             ['TARGET_SIMULATION_ONLY'],
            );
        }

        // Compare decisions.
        $legacyDecision = $legacyResult['decision'] ?? null;
        $targetDecision = $targetResult['decision'] ?? null;

        if ($legacyDecision === $targetDecision && ($legacyResult['derived'] ?? null) === ($targetResult['derived'] ?? null)) {
            return new DualRunResult(
                caseId:                  $caseId,
                classification:          DualRunResult::MATCH_OK,
                normalizedInput:         $normalizedInput,
                legacyResult:            $legacyResult,
                targetResult:            $targetResult,
                targetWriteCount:        0,
                targetExternalCallCount: 0,
            );
        }

        // Legacy-only path — target has no equivalent structural path.
        if ($targetResult === null || ($targetResult['decision'] ?? null) === null) {
            return new DualRunResult(
                caseId:                  $caseId,
                classification:          DualRunResult::LEGACY_ONLY_BEHAVIOR,
                normalizedInput:         $normalizedInput,
                legacyResult:            $legacyResult,
                targetResult:            $targetResult ?? [],
                targetWriteCount:        0,
                targetExternalCallCount: 0,
            );
        }

        // Target-only structure — legacy has nothing to say.
        if ($legacyDecision === null) {
            return new DualRunResult(
                caseId:                  $caseId,
                classification:          DualRunResult::TARGET_ONLY_STRUCTURE,
                normalizedInput:         $normalizedInput,
                legacyResult:            $legacyResult,
                targetResult:            $targetResult,
                targetWriteCount:        0,
                targetExternalCallCount: 0,
            );
        }

        // Everything else: unexplained.
        return new DualRunResult(
            caseId:                  $caseId,
            classification:          DualRunResult::UNEXPLAINED_DIFFERENCE,
            normalizedInput:         $normalizedInput,
            legacyResult:            $legacyResult,
            targetResult:            $targetResult,
            targetWriteCount:        0,
            targetExternalCallCount: 0,
            reasonCodes:             ['LEGACY_DECISION_DIFFERS_FROM_TARGET_WITHOUT_KNOWN_REASON'],
        );
    }
}
