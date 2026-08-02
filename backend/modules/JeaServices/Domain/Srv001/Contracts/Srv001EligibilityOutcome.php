<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

/**
 * TD-05 · Typed outcome for every SRV-001 eligibility / external-port
 * decision.
 *
 *   • ELIGIBLE                     — port produced an authoritative
 *                                    positive result.
 *   • INELIGIBLE                   — port produced an authoritative
 *                                    negative result (business rule).
 *   • QUOTA_EXHAUSTED              — office / engineer has consumed
 *                                    the yearly quota.
 *   • SPECIALIZATION_BLOCKED       — engineer discipline suspended /
 *                                    disqualified for the service.
 *   • CORRECTION_REQUIRED          — office record contains a pending
 *                                    correction the applicant must fix.
 *   • ENGINEERING_CEILING_EXCEEDED — total office ceiling exceeded.
 *   • MANDATORY_NOTE_BLOCK         — an active mandatory note (scope=
 *                                    office|parcel) blocks submission.
 *   • EXTERNAL_UNAVAILABLE         — provider timeout, DNS failure, or
 *                                    5xx — DECISION IS FAIL-CLOSED.
 *   • CONTRACT_MISSING             — required external contract is not
 *                                    yet signed (Oracle/DLS/BURA/etc.);
 *                                    the port MUST NOT invent a positive
 *                                    result — return this.
 *   • INVALID_EXTERNAL_RESPONSE    — provider responded with a payload
 *                                    that failed schema validation.
 *   • MANUAL_REVIEW                — port cannot decide; a human must
 *                                    review the case.
 *   • NOT_APPLICABLE               — port's precondition does not apply
 *                                    (e.g., specialization check on a
 *                                    service that doesn't require one).
 *
 * FAIL-CLOSED INVARIANT: any outcome that is not ELIGIBLE is treated
 * as blocking by the submission pipeline. External uncertainty NEVER
 * silently becomes ELIGIBLE — that would be an OWASP-adjacent
 * authorization defect.
 */
final class Srv001EligibilityOutcome
{
    public const ELIGIBLE                     = 'ELIGIBLE';
    public const INELIGIBLE                   = 'INELIGIBLE';
    public const QUOTA_EXHAUSTED              = 'QUOTA_EXHAUSTED';
    public const SPECIALIZATION_BLOCKED       = 'SPECIALIZATION_BLOCKED';
    public const CORRECTION_REQUIRED          = 'CORRECTION_REQUIRED';
    public const ENGINEERING_CEILING_EXCEEDED = 'ENGINEERING_CEILING_EXCEEDED';
    public const MANDATORY_NOTE_BLOCK         = 'MANDATORY_NOTE_BLOCK';
    public const EXTERNAL_UNAVAILABLE         = 'EXTERNAL_UNAVAILABLE';
    public const CONTRACT_MISSING             = 'CONTRACT_MISSING';
    public const INVALID_EXTERNAL_RESPONSE    = 'INVALID_EXTERNAL_RESPONSE';
    public const MANUAL_REVIEW                = 'MANUAL_REVIEW';
    public const NOT_APPLICABLE               = 'NOT_APPLICABLE';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::ELIGIBLE,
            self::INELIGIBLE,
            self::QUOTA_EXHAUSTED,
            self::SPECIALIZATION_BLOCKED,
            self::CORRECTION_REQUIRED,
            self::ENGINEERING_CEILING_EXCEEDED,
            self::MANDATORY_NOTE_BLOCK,
            self::EXTERNAL_UNAVAILABLE,
            self::CONTRACT_MISSING,
            self::INVALID_EXTERNAL_RESPONSE,
            self::MANUAL_REVIEW,
            self::NOT_APPLICABLE,
        ];
    }

    public static function isValid(string $v): bool
    {
        return in_array($v, self::all(), true);
    }

    /**
     * The ONLY outcome that permits submission to proceed. Every other
     * outcome MUST block. Callers of external ports must inspect this
     * list — not the outcome string directly — so that new outcome
     * states default to blocking (fail-closed by construction).
     *
     * @return list<string>
     */
    public static function permissiveOutcomes(): array
    {
        return [self::ELIGIBLE, self::NOT_APPLICABLE];
    }

    /**
     * @return list<string>
     */
    public static function blockingOutcomes(): array
    {
        return array_values(array_diff(self::all(), self::permissiveOutcomes()));
    }
}
