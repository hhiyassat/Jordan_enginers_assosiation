<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\ValueObjects;

use InvalidArgumentException;
use Modules\JeaServices\Domain\Srv001\Contracts\Srv001CalculationOutcome;
use Modules\JeaServices\Governance\ServiceCalculationResult;

/**
 * TD-04 · Evidence-rich wrapper around a `ServiceCalculationResult`
 * that adds a typed provenance outcome (Srv001CalculationOutcome) plus
 * the classifier's reasoning trail.
 *
 * IMMUTABLE. Constructed by
 * `Srv001CalculatorOutcomeClassifier::classify(...)`. Never mutated
 * afterwards.
 *
 * The wrapper preserves the underlying `ServiceCalculationResult`
 * unchanged — no adapter transformations, no numeric mutation — so
 * downstream code that already consumes `ServiceCalculationResult`
 * continues to work.
 *
 * Consumers may inspect `$outcome` to decide whether to persist the
 * numeric result (`CALCULATED`) or route through a different flow
 * (`MANUAL_REVIEW`, `BLOCKED`, `SIMULATION_ONLY`, etc.). See
 * `Srv001CalculationOutcome` for the semantics of each state.
 */
final class Srv001TypedCalculationResult
{
    /**
     * @param  string  $outcome           one of Srv001CalculationOutcome::*
     * @param  ServiceCalculationResult  $numeric       the underlying numeric result
     * @param  string  $classifierReason  short human-readable reason (Arabic OK)
     * @param  array<string, mixed>  $classificationEvidence  additional fields
     *   the classifier used (e.g. rule_version_status, missing_inputs)
     */
    public function __construct(
        public readonly string $outcome,
        public readonly ServiceCalculationResult $numeric,
        public readonly string $classifierReason,
        public readonly array $classificationEvidence = [],
    ) {
        if (! Srv001CalculationOutcome::isValid($outcome)) {
            throw new InvalidArgumentException(
                "Unknown Srv001CalculationOutcome: {$outcome}",
            );
        }
    }

    public function isBinding(): bool
    {
        return in_array($this->outcome, Srv001CalculationOutcome::bindingOutcomes(), true);
    }

    public function isNonBinding(): bool
    {
        return in_array($this->outcome, Srv001CalculationOutcome::nonBindingOutcomes(), true);
    }

    /**
     * Snapshot payload extension — the classifier's outcome + reason
     * live in `intermediateValues` so they persist alongside the
     * numeric result in `calculation_snapshots.intermediate_values`.
     *
     * @return array<string, mixed>
     */
    public function toSnapshotIntermediateValuesExtension(): array
    {
        return [
            'srv001_calculation_outcome'          => $this->outcome,
            'srv001_calculation_outcome_reason'   => $this->classifierReason,
            'srv001_calculation_outcome_evidence' => $this->classificationEvidence,
        ];
    }
}
