<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\DualRun\ValueObjects;

use InvalidArgumentException;

/**
 * TD-10 · Immutable dual-run comparison result.
 *
 * Classifications:
 *   • MATCH                          — legacy and target agree
 *   • EXPECTED_PROVISIONAL_DIFFERENCE — target blocked-by-OD or
 *                                       simulation-only; difference
 *                                       is expected pending OD closure
 *   • BLOCKED_TARGET_RULE            — target explicitly BLOCKED_BY_OD
 *   • LEGACY_ONLY_BEHAVIOR           — legacy returns a decision; target
 *                                       has no equivalent structural path
 *   • TARGET_ONLY_STRUCTURE          — target models something legacy
 *                                       doesn't (rare — future rules)
 *   • UNEXPLAINED_DIFFERENCE         — divergence with no legitimate
 *                                       explanation → dual-run gate fails
 *   • EXECUTION_ERROR                — target simulator threw
 *
 * FAIL-CLOSED: UNEXPLAINED_DIFFERENCE and EXECUTION_ERROR are the
 * only classifications that fail the dual-run acceptance gate.
 * MATCH + EXPECTED_PROVISIONAL_DIFFERENCE + BLOCKED_TARGET_RULE
 * pass. LEGACY_ONLY_BEHAVIOR + TARGET_ONLY_STRUCTURE pass with
 * disposition attached.
 */
final class DualRunResult
{
    public const MATCH_OK                        = 'MATCH';
    public const EXPECTED_PROVISIONAL_DIFFERENCE = 'EXPECTED_PROVISIONAL_DIFFERENCE';
    public const BLOCKED_TARGET_RULE             = 'BLOCKED_TARGET_RULE';
    public const LEGACY_ONLY_BEHAVIOR            = 'LEGACY_ONLY_BEHAVIOR';
    public const TARGET_ONLY_STRUCTURE           = 'TARGET_ONLY_STRUCTURE';
    public const UNEXPLAINED_DIFFERENCE          = 'UNEXPLAINED_DIFFERENCE';
    public const EXECUTION_ERROR                 = 'EXECUTION_ERROR';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::MATCH_OK, self::EXPECTED_PROVISIONAL_DIFFERENCE,
            self::BLOCKED_TARGET_RULE, self::LEGACY_ONLY_BEHAVIOR,
            self::TARGET_ONLY_STRUCTURE, self::UNEXPLAINED_DIFFERENCE,
            self::EXECUTION_ERROR,
        ];
    }

    /** @return list<string> */
    public static function passingClassifications(): array
    {
        return [
            self::MATCH_OK,
            self::EXPECTED_PROVISIONAL_DIFFERENCE,
            self::BLOCKED_TARGET_RULE,
            self::LEGACY_ONLY_BEHAVIOR,
            self::TARGET_ONLY_STRUCTURE,
        ];
    }

    /**
     * @param array<string, mixed> $normalizedInput
     * @param array<string, mixed> $legacyResult
     * @param array<string, mixed> $targetResult
     * @param list<string>         $reasonCodes
     */
    public function __construct(
        public readonly string $caseId,
        public readonly string $classification,
        public readonly array $normalizedInput,
        public readonly array $legacyResult,
        public readonly array $targetResult,
        public readonly int $targetWriteCount,
        public readonly int $targetExternalCallCount,
        public readonly array $reasonCodes = [],
    ) {
        if (! in_array($classification, self::all(), true)) {
            throw new InvalidArgumentException("Unknown DualRunResult classification: {$classification}");
        }
        if ($targetWriteCount !== 0) {
            throw new InvalidArgumentException(
                'DualRunResult MUST have targetWriteCount=0 — target simulation must not persist',
            );
        }
        if ($targetExternalCallCount !== 0) {
            throw new InvalidArgumentException(
                'DualRunResult MUST have targetExternalCallCount=0 — target simulation must not make external calls',
            );
        }
    }

    public function passesGate(): bool
    {
        return in_array($this->classification, self::passingClassifications(), true);
    }
}
