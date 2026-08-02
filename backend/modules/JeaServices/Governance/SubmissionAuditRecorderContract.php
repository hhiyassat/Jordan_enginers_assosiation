<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

use App\Models\User;
use Modules\JeaServices\Models\Application;

/**
 * TD-02-SUPP · Contract for writing an audit event during a submission
 * use-case transaction.
 *
 * Extracted (rather than calling `AuditLog::record` directly) so the
 * use case can:
 *   (a) inject a test double that throws — proving audit persistence
 *       failure rolls back the whole submission transaction;
 *   (b) keep the audit call inside the same transaction as application
 *       persistence + version binding + snapshot writes.
 *
 * The single production implementation is `SubmissionAuditRecorder`
 * which delegates to `AuditLog::record`.
 */
interface SubmissionAuditRecorderContract
{
    /**
     * Persist an audit event for the submission side-effect commit.
     * Called by SubmitApplicationUseCase inside its DB::transaction.
     *
     * @param  array<string, mixed>  $extra  attribution/context extras
     *   (typically: rule_id, service_definition_version_id,
     *   version_binding_classification, snapshot_ids, derived_value_keys)
     *
     * @return int  new audit_logs.id
     */
    public function recordSubmissionCommitted(
        User $actor,
        Application $application,
        array $extra,
    ): int;
}
