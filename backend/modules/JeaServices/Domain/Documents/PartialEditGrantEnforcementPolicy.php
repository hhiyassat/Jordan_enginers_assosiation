<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents;

use Modules\JeaServices\Domain\Documents\ValueObjects\DocumentMetadata;
use Modules\JeaServices\Domain\Documents\ValueObjects\PartialEditGrant;

/**
 * TD-06 · Partial-edit grant enforcement policy.
 *
 * Pure decision function. `decide()` returns an
 * `EditPermissionDecision` — the caller MUST inspect the decision;
 * it MUST NOT infer from any single grant/lock check independently.
 */
final class PartialEditGrantEnforcementPolicy
{
    public function __construct(
        private readonly SignedContractLockPolicy $lockPolicy,
    ) {
    }

    /**
     * @param list<DocumentMetadata> $documents  the application's document stack
     */
    public function decide(
        PartialEditGrant $grant,
        string $fieldId,
        string $sectionId,
        string $nowTimestamp,
        array $documents,
    ): EditPermissionDecision {
        // Terminal states short-circuit first — cheapest check.
        if ($grant->state === PartialEditGrant::STATE_REVOKED) {
            return EditPermissionDecision::denied(['GRANT_REVOKED']);
        }
        if ($grant->state === PartialEditGrant::STATE_CONSUMED) {
            return EditPermissionDecision::denied(['GRANT_ALREADY_CONSUMED']);
        }
        if ($grant->state === PartialEditGrant::STATE_EXPIRED) {
            return EditPermissionDecision::denied(['GRANT_EXPIRED']);
        }
        if (! $grant->isUsable($nowTimestamp)) {
            return EditPermissionDecision::denied(['GRANT_EXPIRY_PASSED']);
        }

        // Scope check — deny if BOTH section and field are outside
        // the grant. (A grant may name a section OR a field; either
        // membership passes.)
        if (! $grant->permitsField($fieldId) && ! $grant->permitsSection($sectionId)) {
            return EditPermissionDecision::denied(['EDIT_OUTSIDE_GRANT_SCOPE']);
        }

        // Legal-lock check — even a valid grant cannot unlock a
        // legally-protected field once the signed contract is accepted.
        if (
            $this->lockPolicy->isProtectedField($fieldId)
            && $this->lockPolicy->isLocked($documents)
        ) {
            return EditPermissionDecision::denied(['SIGNED_CONTRACT_LOCK_ACTIVE']);
        }

        return EditPermissionDecision::allowed();
    }
}
