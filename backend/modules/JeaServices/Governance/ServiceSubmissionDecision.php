<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

/**
 * SG-05 · Typed decision returned by a ServiceSubmissionPolicy.
 *
 * Per JDG-SG00-04, policies MUST NOT save Eloquent models. They return
 * this value object; the calling use case orchestrates persistence
 * (writing derived values, snapshotting via CalculationSnapshotWriter,
 * transitioning workflow state).
 *
 * The `derivedValues` array carries computed values the calling use case
 * should persist onto `applications.data`. The `calculationSnapshots`
 * array carries the raw material for CalculationSnapshotWriter (one
 * entry per rule executed, each with inputs+outputs+intermediate+warnings
 * + rule_version_id).
 */
final class ServiceSubmissionDecision
{
    /**
     * @param  array<string, list<string>>  $errors                field-id keyed error messages
     * @param  array<string, mixed>         $derivedValues         key → computed value to persist onto app.data
     * @param  list<string>                 $warnings
     * @param  list<array{
     *     rule_version_id: int,
     *     inputs: array<string, mixed>,
     *     outputs: array<string, mixed>,
     *     intermediate_values?: array<string, mixed>|null,
     *     warnings?: list<string>|null,
     *     open_decisions?: list<string>|null
     * }>                                   $calculationSnapshots
     */
    private function __construct(
        public readonly bool $accepted,
        public readonly array $errors,
        public readonly array $derivedValues,
        public readonly array $warnings,
        public readonly array $calculationSnapshots,
    ) {
    }

    /**
     * @param  array<string, mixed>  $derivedValues
     * @param  list<string>          $warnings
     * @param  list<array{
     *     rule_version_id: int,
     *     inputs: array<string, mixed>,
     *     outputs: array<string, mixed>,
     *     intermediate_values?: array<string, mixed>|null,
     *     warnings?: list<string>|null,
     *     open_decisions?: list<string>|null
     * }>                            $calculationSnapshots
     */
    public static function accepted(
        array $derivedValues = [],
        array $warnings = [],
        array $calculationSnapshots = [],
    ): self {
        return new self(true, [], $derivedValues, $warnings, $calculationSnapshots);
    }

    /**
     * @param  array<string, list<string>>  $errors  field-id keyed
     */
    public static function rejected(array $errors): self
    {
        return new self(false, $errors, [], [], []);
    }
}
