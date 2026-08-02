<?php

declare(strict_types=1);

namespace Modules\JeaServices\UseCases\SubmitApplication;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\JeaServices\Governance\ApplicationVersionBinderContract;
use Modules\JeaServices\Governance\CalculationSnapshotWriter;
use Modules\JeaServices\Governance\ServiceSubmissionDecision;
use Modules\JeaServices\Governance\SubmissionAuditRecorder;
use Modules\JeaServices\Governance\SubmissionAuditRecorderContract;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\RuleVersion;
use Throwable;

/**
 * TD-02 · Application submission use case.
 *
 * Orchestrates the three side effects that must move together on an
 * accepted submission:
 *
 *   1. Merge decision.derivedValues into application.data
 *   2. Bind the currently-published ServiceDefinitionVersion (or classify
 *      LEGACY_UNVERSIONED when none exists)
 *   3. Write one calculation_snapshots row per decision.calculationSnapshots
 *      payload (SUBMIT purpose, immutable per SG-04)
 *
 * All three run inside a single DB::transaction — snapshot failure or
 * binding failure rolls back the entire submission side effect set,
 * leaving application state exactly as it was before invocation.
 *
 * SCOPE + INVARIANTS (per user TD-02 directive):
 *
 * - Service-code AGNOSTIC — the use case never checks or branches on
 *   service_code. Callers pass an already-produced ServiceSubmissionDecision.
 *   Any policy (Legacy or Target) can produce that decision.
 * - NOT wired into runtime yet (Srv001Guard remains the sole SRV-001 runtime
 *   path). Callers invoke this only from tests and from a future
 *   caller-of-record when RES-SG06-01 closes.
 * - Preserves ServiceDefinition::lockForUpdate() publication concurrency
 *   invariant by not touching ServiceVersionPublisher / ServiceDefinition
 *   during submission.
 * - Does NOT publish RuleVersion, does NOT change SRV-001 numeric outputs,
 *   does NOT change fee, workflow, or activation state.
 * - Does NOT alter the workflow status of the Application. Callers combine
 *   this with WorkflowEngine::submit when they want the full runtime step.
 */
final class SubmitApplicationUseCase
{
    public function __construct(
        private readonly ApplicationVersionBinderContract $versionBinder,
        private readonly CalculationSnapshotWriter $snapshotWriter,
        private readonly SubmissionAuditRecorderContract $auditRecorder,
    ) {
    }

    public function execute(
        Application $application,
        ServiceSubmissionDecision $decision,
        User $actor,
    ): SubmitApplicationResult {
        // Short-circuit: rejected decisions never touch the DB.
        if (! $decision->accepted) {
            return SubmitApplicationResult::rejected($decision->errors);
        }

        try {
            $out = DB::transaction(function () use ($application, $decision, $actor) {
                // 1. Merge derived values into applications.data. Preserve
                //    every existing key so callers who accumulated data
                //    from earlier steps do not lose it.
                $existing = is_array($application->data) ? $application->data : [];
                $merged   = array_merge($existing, $decision->derivedValues);
                $application->data = $merged;
                $derivedPersisted  = $merged !== $existing;

                // 2. Bind to currently-published version, if any. Idempotent
                //    on already-bound applications (see SG-03).
                $classification = $this->versionBinder->bindOrClassifyLegacy($application);

                // 3. Persist application changes now — before snapshots — so
                //    the snapshot rows can reference the application_id
                //    (already exists) AND so any Eloquent event on save
                //    happens inside the transaction.
                $application->save();

                $boundVersionId = $application->service_definition_version_id;

                // 4. Write one SUBMIT snapshot per calculator payload.
                //    Immutable after insert (SG-04 observer + unique
                //    constraint on (application_id, rule_version_id,
                //    purpose='SUBMIT')). Any duplicate insert raises
                //    QueryException → whole transaction rolls back.
                $snapshotIds        = [];
                $ruleIdentifiers    = [];
                foreach ($decision->calculationSnapshots as $payload) {
                    $ruleVersion = RuleVersion::query()->findOrFail($payload['rule_version_id']);
                    $snap = $this->snapshotWriter->writeForSubmit(
                        application:        $application,
                        ruleVersion:        $ruleVersion,
                        inputs:             $payload['inputs'],
                        outputs:            $payload['outputs'],
                        intermediateValues: $payload['intermediate_values'] ?? null,
                        warnings:           $payload['warnings'] ?? null,
                        openDecisions:      $payload['open_decisions'] ?? null,
                        calculatedByUserId: $actor->id,
                    );
                    $snapshotIds[]     = $snap->id;
                    $ruleIdentifiers[] = $ruleVersion->ruleDefinition->rule_identifier;
                }

                // 5. TD-02-SUPP · audit-event persistence inside the same
                //    transaction. Any failure here (schema drift, DB error,
                //    injected test-double throw) rolls back the entire
                //    submission — application + version bind + snapshots
                //    all revert, PARTIAL_PERSISTENCE=0.
                $auditId = $this->auditRecorder->recordSubmissionCommitted(
                    actor:       $actor,
                    application: $application,
                    extra: [
                        'rule_id'                        => SubmissionAuditRecorder::ACTION,
                        'service_definition_version_id'  => $boundVersionId,
                        'version_binding_classification' => $classification,
                        'snapshot_ids'                   => $snapshotIds,
                        'rule_identifiers'               => $ruleIdentifiers,
                        'derived_value_keys'             => array_keys($decision->derivedValues),
                        'target_domain_provisional'      => true,
                    ],
                );

                return SubmitApplicationResult::committed(
                    versionBindingClassification: $classification,
                    boundVersionId:               $boundVersionId,
                    snapshotIds:                  $snapshotIds,
                    derivedValuesPersisted:       $derivedPersisted,
                    auditEventId:                 $auditId,
                );
            });

            return $out;
        } catch (Throwable $e) {
            // Ensure the caller can distinguish a rollback from an
            // upstream rejection. The application in-memory instance may
            // still carry the merged data locally; a fresh reload will
            // show the pre-invoke state.
            return SubmitApplicationResult::rolledBack(sprintf(
                '%s: %s',
                $e::class,
                $e->getMessage(),
            ));
        }
    }
}
