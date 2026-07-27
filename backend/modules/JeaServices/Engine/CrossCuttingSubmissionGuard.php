<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

use Modules\JeaServices\Models\Application;

/**
 * CrossCuttingSubmissionGuard — platform-wide submit-time invariant check.
 *
 * Distinct from `ServiceSubmissionGuard`: cross-cutting guards run for
 * EVERY application regardless of service code, and the guard itself
 * decides whether the current application is in scope (typically by
 * inspecting the service schema for the fields it cares about).
 *
 * The interface signature is identical to `ServiceSubmissionGuard` on
 * purpose — the difference is the invoking pipeline
 * (`CrossCuttingSubmissionPipeline` vs `ServiceSubmissionGuardRegistry`)
 * and the ordering in `ApplicationController::submit`.
 */
interface CrossCuttingSubmissionGuard
{
    /**
     * @return array<string, string>  field-id keyed error messages, [] on pass
     */
    public function validate(Application $app): array;
}
