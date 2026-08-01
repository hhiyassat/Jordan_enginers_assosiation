<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

/**
 * SG-05 · Typed result returned by a ServiceCalculationPolicy.
 *
 * A calculator returns this instead of writing directly to
 * applications.data. The calling policy (typically a
 * ServiceSubmissionPolicy) collects results and hands them off to the
 * use case that owns persistence + CalculationSnapshotWriter invocation.
 */
final class ServiceCalculationResult
{
    /**
     * @param  int                        $ruleVersionId       (from rule_versions.id)
     * @param  array<string, mixed>       $inputs              exact inputs used by the calculator
     * @param  array<string, mixed>       $outputs             derived values
     * @param  array<string, mixed>|null  $intermediateValues  optional debug/audit values
     * @param  list<string>|null          $warnings            calculator-emitted warnings
     * @param  list<string>|null          $openDecisions       unresolved decisions (e.g. PROVISIONAL invariants)
     */
    public function __construct(
        public readonly int $ruleVersionId,
        public readonly array $inputs,
        public readonly array $outputs,
        public readonly ?array $intermediateValues = null,
        public readonly ?array $warnings = null,
        public readonly ?array $openDecisions = null,
    ) {
    }

    /**
     * Convert to the array shape consumed by ServiceSubmissionDecision::accepted
     * (the $calculationSnapshots parameter).
     *
     * @return array{
     *     rule_version_id: int,
     *     inputs: array<string, mixed>,
     *     outputs: array<string, mixed>,
     *     intermediate_values?: array<string, mixed>|null,
     *     warnings?: list<string>|null,
     *     open_decisions?: list<string>|null
     * }
     */
    public function toSnapshotPayload(): array
    {
        $payload = [
            'rule_version_id' => $this->ruleVersionId,
            'inputs'          => $this->inputs,
            'outputs'         => $this->outputs,
        ];
        if ($this->intermediateValues !== null) {
            $payload['intermediate_values'] = $this->intermediateValues;
        }
        if ($this->warnings !== null) {
            $payload['warnings'] = $this->warnings;
        }
        if ($this->openDecisions !== null) {
            $payload['open_decisions'] = $this->openDecisions;
        }
        return $payload;
    }
}
