<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

use App\Models\AuditLog;
use App\Models\User;
use Modules\JeaServices\Models\Application;

/**
 * TD-02-SUPP · Default `SubmissionAuditRecorderContract` implementation.
 *
 * Delegates to the platform `AuditLog::record` helper — which writes to
 * `audit_logs`. Called inside the SubmitApplicationUseCase transaction,
 * so any DB failure (unique-constraint violation, connection loss)
 * during the write rolls back all sibling writes atomically.
 *
 * Action string is `application.target_submission_committed` —
 * distinct from the legacy runtime path's `application.submitted`
 * emitted by WorkflowEngine::submit, so log consumers can tell the
 * two paths apart.
 */
final class SubmissionAuditRecorder implements SubmissionAuditRecorderContract
{
    public const ACTION = 'application.target_submission_committed';

    public function recordSubmissionCommitted(
        User $actor,
        Application $application,
        array $extra,
    ): int {
        $entry = AuditLog::record(
            user:    $actor,
            subject: $application,
            action:  self::ACTION,
            extra:   $extra,
        );

        return (int) $entry->id;
    }
}
