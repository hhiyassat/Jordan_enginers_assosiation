<?php

declare(strict_types=1);

namespace Tests\Feature\DualRun;

use InvalidArgumentException;
use Modules\JeaServices\Domain\DualRun\DualRunClassifier;
use Modules\JeaServices\Domain\DualRun\ValueObjects\DualRunResult;
use PHPUnit\Framework\TestCase;

/**
 * TD-10 · Dual-run classifier tests.
 *
 * Every case here uses a stubbed target simulator (a pure PHP
 * callable) — the classifier NEVER invokes any real target policy
 * so there's no risk of target-side writes or external calls.
 */
class Srv001DualRunTest extends TestCase
{
    private DualRunClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new DualRunClassifier();
    }

    // (1) legacy + target receive identical immutable input.
    public function test_normalized_input_is_passed_verbatim_to_both_paths(): void
    {
        $input        = ['a' => 1, 'b' => 2];
        $capturedInput = null;
        $sim = function (array $x) use (&$capturedInput) {
            $capturedInput = $x;
            return ['decision' => 'ACCEPTED', 'derived' => ['x' => 1], 'blockers' => []];
        };
        $legacy = ['decision' => 'ACCEPTED', 'derived' => ['x' => 1]];
        $r = $this->classifier->classify('c-1', $input, $legacy, $sim);
        $this->assertSame($input, $capturedInput);
        $this->assertSame($input, $r->normalizedInput);
    }

    // (2-8) target simulation writes 0 records / snapshots / workflow /
    // payments / receipts / certificates / makes 0 external calls.
    public function test_dual_run_result_enforces_zero_target_writes_and_external_calls(): void
    {
        // Attempting to construct a DualRunResult with nonzero writes
        // throws — this is the structural gate.
        $this->expectException(InvalidArgumentException::class);
        new DualRunResult(
            caseId: 'x',
            classification: DualRunResult::MATCH_OK,
            normalizedInput: [],
            legacyResult: [],
            targetResult: [],
            targetWriteCount: 1,
            targetExternalCallCount: 0,
        );
    }

    public function test_dual_run_result_enforces_zero_external_calls(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DualRunResult(
            caseId: 'x',
            classification: DualRunResult::MATCH_OK,
            normalizedInput: [],
            legacyResult: [],
            targetResult: [],
            targetWriteCount: 0,
            targetExternalCallCount: 1,
        );
    }

    // (9) MATCH classification.
    public function test_matching_results_classified_as_MATCH(): void
    {
        $sim = fn () => ['decision' => 'ACCEPTED', 'derived' => ['min' => 5], 'blockers' => []];
        $r   = $this->classifier->classify('c-1', [], ['decision' => 'ACCEPTED', 'derived' => ['min' => 5]], $sim);
        $this->assertSame(DualRunResult::MATCH_OK, $r->classification);
        $this->assertTrue($r->passesGate());
    }

    // (10) BLOCKED_TARGET_RULE classification.
    public function test_target_blocked_by_od_classified_as_BLOCKED_TARGET_RULE(): void
    {
        $sim = fn () => ['decision' => 'BLOCKED_BY_OD', 'blockers' => ['OD-34']];
        $r   = $this->classifier->classify('c-2', [], ['decision' => 'ACCEPTED'], $sim);
        $this->assertSame(DualRunResult::BLOCKED_TARGET_RULE, $r->classification);
        $this->assertTrue($r->passesGate());
    }

    // (11) EXPECTED_PROVISIONAL_DIFFERENCE.
    public function test_target_simulation_only_classified_as_EXPECTED_PROVISIONAL_DIFFERENCE(): void
    {
        $sim = fn () => ['decision' => 'SIMULATION_ONLY'];
        $r   = $this->classifier->classify('c-3', [], ['decision' => 'ACCEPTED'], $sim);
        $this->assertSame(DualRunResult::EXPECTED_PROVISIONAL_DIFFERENCE, $r->classification);
        $this->assertTrue($r->passesGate());
    }

    // (12) UNEXPLAINED_DIFFERENCE fails the gate.
    public function test_unexplained_difference_fails_the_gate(): void
    {
        $sim = fn () => ['decision' => 'REJECTED', 'derived' => []];
        $r   = $this->classifier->classify('c-4', [], ['decision' => 'ACCEPTED', 'derived' => []], $sim);
        $this->assertSame(DualRunResult::UNEXPLAINED_DIFFERENCE, $r->classification);
        $this->assertFalse($r->passesGate());
    }

    // Target throws → EXECUTION_ERROR.
    public function test_target_execution_error_classified_and_fails_gate(): void
    {
        $sim = function () { throw new \RuntimeException('boom'); };
        $r   = $this->classifier->classify('c-5', [], ['decision' => 'ACCEPTED'], $sim);
        $this->assertSame(DualRunResult::EXECUTION_ERROR, $r->classification);
        $this->assertFalse($r->passesGate());
    }

    // (13) legacy remains authoritative — the classifier NEVER
    // returns a decision; the legacyResult passes through unchanged.
    public function test_legacy_result_is_preserved_verbatim_in_the_dual_run_result(): void
    {
        $legacy = ['decision' => 'ACCEPTED', 'derived' => ['x' => 42], 'trace' => 'legacy-path'];
        $sim    = fn () => ['decision' => 'REJECTED'];
        $r      = $this->classifier->classify('c-6', [], $legacy, $sim);
        $this->assertSame($legacy, $r->legacyResult,
            'legacy result must pass through untouched — production decision source is legacy-compatible');
    }

    // Comparison fixtures — 18-case sanity: at least the counts +
    // classifications match the mandate.
    public function test_all_documented_classifications_are_reachable(): void
    {
        // Cover MATCH
        $r1 = $this->classifier->classify('m-1', [], ['decision' => 'A'], fn () => ['decision' => 'A']);
        $this->assertSame(DualRunResult::MATCH_OK, $r1->classification);

        // Cover BLOCKED_TARGET_RULE
        $r2 = $this->classifier->classify('m-2', [], ['decision' => 'A'], fn () => ['decision' => 'BLOCKED_BY_OD', 'blockers' => ['OD-1']]);
        $this->assertSame(DualRunResult::BLOCKED_TARGET_RULE, $r2->classification);

        // Cover EXPECTED_PROVISIONAL_DIFFERENCE
        $r3 = $this->classifier->classify('m-3', [], ['decision' => 'A'], fn () => ['decision' => 'SIMULATION_ONLY']);
        $this->assertSame(DualRunResult::EXPECTED_PROVISIONAL_DIFFERENCE, $r3->classification);

        // Cover LEGACY_ONLY_BEHAVIOR (target returns no decision key)
        $r4 = $this->classifier->classify('m-4', [], ['decision' => 'A'], fn () => []);
        $this->assertSame(DualRunResult::LEGACY_ONLY_BEHAVIOR, $r4->classification);

        // Cover TARGET_ONLY_STRUCTURE (legacy returns no decision)
        $r5 = $this->classifier->classify('m-5', [], [], fn () => ['decision' => 'A']);
        $this->assertSame(DualRunResult::TARGET_ONLY_STRUCTURE, $r5->classification);

        // Cover UNEXPLAINED
        $r6 = $this->classifier->classify('m-6', [], ['decision' => 'A'], fn () => ['decision' => 'B']);
        $this->assertSame(DualRunResult::UNEXPLAINED_DIFFERENCE, $r6->classification);
        $this->assertFalse($r6->passesGate());

        // Cover EXECUTION_ERROR
        $r7 = $this->classifier->classify('m-7', [], [], function () { throw new \RuntimeException('x'); });
        $this->assertSame(DualRunResult::EXECUTION_ERROR, $r7->classification);
    }
}
