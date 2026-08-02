<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents\ValueObjects;

use InvalidArgumentException;

/**
 * TD-06 · Immutable partial-edit grant.
 *
 * A grant permits the applicant to edit specific fields/sections of
 * an application AFTER the standard edit window closes (usually
 * after payment or after a mid-flow lock). Enforced by
 * `PartialEditGrantEnforcementPolicy`.
 *
 * INVARIANTS:
 *   • grant does NOT transfer application ownership
 *   • grant does NOT unlock legally-locked fields (signed-contract
 *     lock stands regardless)
 *   • expiry (if set) is enforced strictly
 *   • revocation is a terminal state
 *   • once consumed and the grant is single-use, further use fails
 */
final class PartialEditGrant
{
    public const STATE_ACTIVE   = 'ACTIVE';
    public const STATE_CONSUMED = 'CONSUMED';
    public const STATE_EXPIRED  = 'EXPIRED';
    public const STATE_REVOKED  = 'REVOKED';

    /**
     * @param list<string> $permittedSections   allowed section IDs
     * @param list<string> $permittedFields     allowed field IDs
     */
    public function __construct(
        public readonly string $grantId,
        public readonly int $applicationId,
        public readonly int $grantingActorId,
        public readonly string $grantingRole,
        public readonly string $reason,
        public readonly array $permittedSections,
        public readonly array $permittedFields,
        public readonly string $issueTimestamp,
        public readonly ?string $expiryTimestamp = null,
        public readonly string $state = self::STATE_ACTIVE,
        public readonly bool $singleUse = false,
    ) {
        if ($grantId === '' || $reason === '' || $grantingRole === '' || $issueTimestamp === '') {
            throw new InvalidArgumentException('PartialEditGrant: required string fields missing');
        }
        if ($permittedSections === [] && $permittedFields === []) {
            throw new InvalidArgumentException('At least one permittedSection or permittedField required');
        }
        if (! in_array(
            $state,
            [self::STATE_ACTIVE, self::STATE_CONSUMED, self::STATE_EXPIRED, self::STATE_REVOKED],
            true,
        )) {
            throw new InvalidArgumentException("Unknown state: {$state}");
        }
    }

    public function isUsable(string $nowTimestamp): bool
    {
        if ($this->state !== self::STATE_ACTIVE) {
            return false;
        }
        if ($this->expiryTimestamp !== null && $nowTimestamp > $this->expiryTimestamp) {
            return false;
        }
        return true;
    }

    public function permitsField(string $fieldId): bool
    {
        return in_array($fieldId, $this->permittedFields, true);
    }

    public function permitsSection(string $sectionId): bool
    {
        return in_array($sectionId, $this->permittedSections, true);
    }

    public function withState(string $newState): self
    {
        return new self(
            grantId:           $this->grantId,
            applicationId:     $this->applicationId,
            grantingActorId:   $this->grantingActorId,
            grantingRole:      $this->grantingRole,
            reason:            $this->reason,
            permittedSections: $this->permittedSections,
            permittedFields:   $this->permittedFields,
            issueTimestamp:    $this->issueTimestamp,
            expiryTimestamp:   $this->expiryTimestamp,
            state:             $newState,
            singleUse:         $this->singleUse,
        );
    }
}
