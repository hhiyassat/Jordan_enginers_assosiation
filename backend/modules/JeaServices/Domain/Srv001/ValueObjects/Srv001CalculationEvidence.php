<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\ValueObjects;

/**
 * TD-01 · Bundle of ServiceCalculationResult objects produced by the
 * three Target* calculators during a submission evaluation.
 *
 * Kept as a distinct type (rather than a plain array) so
 * TargetSrv001SubmissionPolicy has a typed contract and downstream
 * snapshot writers can iterate deterministically.
 */
final class Srv001CalculationEvidence
{
    /**
     * @param \Modules\JeaServices\Governance\ServiceCalculationResult      $explorationMatrix
     * @param \Modules\JeaServices\Governance\ServiceCalculationResult      $wellsCount
     * @param \Modules\JeaServices\Governance\ServiceCalculationResult      $netDepth
     */
    public function __construct(
        public readonly \Modules\JeaServices\Governance\ServiceCalculationResult $explorationMatrix,
        public readonly \Modules\JeaServices\Governance\ServiceCalculationResult $wellsCount,
        public readonly \Modules\JeaServices\Governance\ServiceCalculationResult $netDepth,
    ) {
    }

    /**
     * All three calculator payloads in the ServiceSubmissionDecision
     * $calculationSnapshots shape — ready for CalculationSnapshotWriter.
     *
     * @return list<array{
     *   rule_version_id: int,
     *   inputs: array<string, mixed>,
     *   outputs: array<string, mixed>,
     *   intermediate_values?: array<string, mixed>|null,
     *   warnings?: list<string>|null,
     *   open_decisions?: list<string>|null
     * }>
     */
    public function toSnapshotPayloads(): array
    {
        return [
            $this->explorationMatrix->toSnapshotPayload(),
            $this->wellsCount->toSnapshotPayload(),
            $this->netDepth->toSnapshotPayload(),
        ];
    }
}
