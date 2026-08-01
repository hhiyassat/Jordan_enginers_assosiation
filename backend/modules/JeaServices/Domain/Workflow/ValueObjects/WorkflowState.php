<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Workflow\ValueObjects;

use InvalidArgumentException;

/**
 * TD-07 · Immutable workflow state identifier.
 *
 * States for the SRV-001 pilot graph (subset supported by evidence):
 */
final class WorkflowState
{
    public const DRAFT                              = 'draft';
    public const SUBMITTED                          = 'submitted';
    public const OFFICES_DEPT_REVIEW                = 'offices_dept_review';
    public const FIRST_TECHNICAL_REVIEW             = 'first_technical_review';
    public const SECOND_TECHNICAL_REVIEW            = 'second_technical_review';
    public const PAYMENT_ELIGIBLE                   = 'payment_eligible';
    public const PAYMENT_CONFIRMED                  = 'payment_confirmed';
    public const CERTIFICATE_ELIGIBLE               = 'certificate_eligible';
    public const COMPLETED                          = 'completed';
    public const RETURNED_TO_APPLICANT              = 'returned_to_applicant';
    public const REJECTED                           = 'rejected';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::DRAFT, self::SUBMITTED, self::OFFICES_DEPT_REVIEW,
            self::FIRST_TECHNICAL_REVIEW, self::SECOND_TECHNICAL_REVIEW,
            self::PAYMENT_ELIGIBLE, self::PAYMENT_CONFIRMED,
            self::CERTIFICATE_ELIGIBLE, self::COMPLETED,
            self::RETURNED_TO_APPLICANT, self::REJECTED,
        ];
    }

    public static function assertValid(string $state): void
    {
        if (! in_array($state, self::all(), true)) {
            throw new InvalidArgumentException("Unknown workflow state: {$state}");
        }
    }
}
