<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

use Modules\JeaServices\Models\Application;

/**
 * ServiceSubmissionGuard — contract for per-service submit-time gates.
 *
 * ApplicationController::submit resolves the guard for the application's
 * service code via ServiceSubmissionGuardRegistry and calls validate().
 * Return [] to pass; return field_id => Arabic message to reject with
 * 422 (same envelope as SchemaValidator's field errors).
 *
 * A service without a registered guard is a pass-through — nothing is
 * called, and no branching is required in the controller.
 */
interface ServiceSubmissionGuard
{
    /**
     * @return array<string, string>  field-id keyed error messages, [] on pass
     */
    public function validate(Application $app): array;
}
