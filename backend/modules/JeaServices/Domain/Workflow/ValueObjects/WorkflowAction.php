<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Workflow\ValueObjects;

/**
 * TD-07 · Immutable workflow action identifier.
 *
 * Only actions with sufficient evidence are enumerated. Unresolved
 * actions (committee substitutions, sensory-inspection gates, etc.)
 * appear as BLOCKED transitions, not as enum values — that would
 * imply they're runtime-eligible.
 */
final class WorkflowAction
{
    public const SUBMIT_APPLICATION       = 'submit_application';
    public const OFFICES_DEPT_APPROVE     = 'offices_dept_approve';
    public const OFFICES_DEPT_RETURN      = 'offices_dept_return';
    public const FIRST_REVIEW_APPROVE     = 'first_review_approve';
    public const FIRST_REVIEW_REQUEST_CORRECTION = 'first_review_request_correction';
    public const SECOND_REVIEW_APPROVE    = 'second_review_approve';
    public const SECOND_REVIEW_REQUEST_CORRECTION = 'second_review_request_correction';
    public const MARK_PAYMENT_ELIGIBLE    = 'mark_payment_eligible';
    public const RECORD_PAYMENT_CONFIRMED = 'record_payment_confirmed';
    public const MARK_CERTIFICATE_ELIGIBLE = 'mark_certificate_eligible';
    public const COMPLETE_APPLICATION     = 'complete_application';
    public const REJECT_APPLICATION       = 'reject_application';
}
