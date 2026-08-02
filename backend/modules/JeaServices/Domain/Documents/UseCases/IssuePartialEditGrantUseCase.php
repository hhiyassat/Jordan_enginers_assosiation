<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents\UseCases;

use Modules\JeaServices\Domain\Documents\ValueObjects\PartialEditGrant;

/**
 * TD-07 · Application-layer use case that issues a new partial-edit
 * grant.
 *
 * Pure factory today (structural). When persistence + audit lands,
 * this use case gains a repository + audit writer dependency and
 * wraps them in a transaction. The signature stays stable so
 * downstream consumers don't need re-wiring.
 *
 * `APPLICATION_OWNERSHIP_CHANGED=NO` invariant: the grant carries
 * granting-actor identity but does NOT reassign the application's
 * owner. Callers persisting a grant MUST NOT co-mutate the
 * application's applicant_user_id.
 */
final class IssuePartialEditGrantUseCase
{
    /**
     * @param list<string> $permittedSections
     * @param list<string> $permittedFields
     */
    public function execute(
        int $applicationId,
        int $grantingActorId,
        string $grantingRole,
        string $reason,
        array $permittedSections,
        array $permittedFields,
        string $issueTimestamp,
        ?string $expiryTimestamp = null,
        bool $singleUse = false,
    ): PartialEditGrant {
        return new PartialEditGrant(
            grantId:           $this->generateGrantId($applicationId, $issueTimestamp),
            applicationId:     $applicationId,
            grantingActorId:   $grantingActorId,
            grantingRole:      $grantingRole,
            reason:            $reason,
            permittedSections: $permittedSections,
            permittedFields:   $permittedFields,
            issueTimestamp:    $issueTimestamp,
            expiryTimestamp:   $expiryTimestamp,
            state:             PartialEditGrant::STATE_ACTIVE,
            singleUse:         $singleUse,
        );
    }

    private function generateGrantId(int $applicationId, string $issueTimestamp): string
    {
        return sprintf('peg-%d-%s', $applicationId, substr(sha1($issueTimestamp . '-' . $applicationId), 0, 12));
    }
}
