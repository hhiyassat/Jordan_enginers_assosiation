<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\CalculationSnapshot;
use Modules\JeaServices\Models\RuleVersion;
use RuntimeException;

/**
 * SG-04 · writes calculation snapshots per the JDG-SG04-02 policy.
 *
 * DRAFT purpose overwrites the existing draft snapshot for
 *   (application, rule_version). Insert-or-update.
 * SUBMIT and MANUAL_RECALC purposes always insert a new row and are
 *   immutable after insertion (enforced by the model observer).
 */
final class CalculationSnapshotWriter
{
    /**
     * @param  array<string, mixed>       $inputs
     * @param  array<string, mixed>       $outputs
     * @param  array<string, mixed>|null  $intermediateValues
     * @param  list<string>|null          $warnings
     * @param  list<string>|null          $openDecisions
     */
    public function writeForDraft(
        Application $application,
        RuleVersion $ruleVersion,
        array $inputs,
        array $outputs,
        ?array $intermediateValues = null,
        ?array $warnings = null,
        ?array $openDecisions = null,
        ?int $calculatedByUserId = null,
    ): CalculationSnapshot {
        $existing = CalculationSnapshot::query()
            ->where('application_id', $application->id)
            ->where('rule_version_id', $ruleVersion->id)
            ->where('purpose', CalculationSnapshot::PURPOSE_DRAFT)
            ->first();

        if ($existing !== null) {
            $existing->inputs              = $inputs;
            $existing->outputs             = $outputs;
            $existing->intermediate_values = $intermediateValues;
            $existing->warnings            = $warnings;
            $existing->open_decisions      = $openDecisions;
            $existing->calculated_by       = $calculatedByUserId;
            $existing->calculated_at       = now();
            $existing->save();
            return $existing;
        }

        return CalculationSnapshot::create([
            'application_id'      => $application->id,
            'rule_version_id'     => $ruleVersion->id,
            'purpose'             => CalculationSnapshot::PURPOSE_DRAFT,
            'inputs'              => $inputs,
            'outputs'             => $outputs,
            'intermediate_values' => $intermediateValues,
            'warnings'            => $warnings,
            'open_decisions'      => $openDecisions,
            'calculated_by'       => $calculatedByUserId,
            'calculated_at'       => now(),
        ]);
    }

    /** @param array<string, mixed> $inputs
     *  @param array<string, mixed> $outputs
     *  @param array<string, mixed>|null $intermediateValues
     *  @param list<string>|null $warnings
     *  @param list<string>|null $openDecisions */
    public function writeForSubmit(
        Application $application,
        RuleVersion $ruleVersion,
        array $inputs,
        array $outputs,
        ?array $intermediateValues = null,
        ?array $warnings = null,
        ?array $openDecisions = null,
        ?int $calculatedByUserId = null,
    ): CalculationSnapshot {
        // SUBMIT snapshots are always insert-only (unique constraint on
        // (application, rule_version, purpose='SUBMIT') means a duplicate
        // submit is rejected at the DB layer).
        return CalculationSnapshot::create([
            'application_id'      => $application->id,
            'rule_version_id'     => $ruleVersion->id,
            'purpose'             => CalculationSnapshot::PURPOSE_SUBMIT,
            'inputs'              => $inputs,
            'outputs'             => $outputs,
            'intermediate_values' => $intermediateValues,
            'warnings'            => $warnings,
            'open_decisions'      => $openDecisions,
            'calculated_by'       => $calculatedByUserId,
            'calculated_at'       => now(),
        ]);
    }

    /** @param array<string, mixed> $inputs
     *  @param array<string, mixed> $outputs
     *  @param array<string, mixed>|null $intermediateValues
     *  @param list<string>|null $warnings
     *  @param list<string>|null $openDecisions */
    public function writeForManualRecalc(
        Application $application,
        RuleVersion $ruleVersion,
        array $inputs,
        array $outputs,
        CalculationSnapshot $supersedes,
        ?array $intermediateValues = null,
        ?array $warnings = null,
        ?array $openDecisions = null,
        ?int $calculatedByUserId = null,
    ): CalculationSnapshot {
        if ($supersedes->purpose === CalculationSnapshot::PURPOSE_DRAFT) {
            throw new RuntimeException('MANUAL_RECALC must supersede a SUBMIT or prior MANUAL_RECALC snapshot, not a DRAFT.');
        }
        return CalculationSnapshot::create([
            'application_id'         => $application->id,
            'rule_version_id'        => $ruleVersion->id,
            'purpose'                => CalculationSnapshot::PURPOSE_MANUAL_RECALC,
            'inputs'                 => $inputs,
            'outputs'                => $outputs,
            'intermediate_values'    => $intermediateValues,
            'warnings'               => $warnings,
            'open_decisions'         => $openDecisions,
            'superseded_snapshot_id' => $supersedes->id,
            'calculated_by'          => $calculatedByUserId,
            'calculated_at'          => now(),
        ]);
    }
}
