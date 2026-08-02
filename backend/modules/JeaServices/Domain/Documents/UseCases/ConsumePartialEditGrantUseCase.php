<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents\UseCases;

use InvalidArgumentException;
use Modules\JeaServices\Domain\Documents\PartialEditGrantEnforcementPolicy;
use Modules\JeaServices\Domain\Documents\ValueObjects\DocumentMetadata;
use Modules\JeaServices\Domain\Documents\ValueObjects\PartialEditGrant;

/**
 * TD-07 · Consume a grant to authorise an edit.
 *
 * Returns the updated grant state (CONSUMED when singleUse=true;
 * ACTIVE otherwise). Enforces the enforcement policy first — the
 * caller MUST NOT bypass this use case and check the policy in
 * isolation.
 *
 * When persistence lands: the grant repository update + the
 * application field update MUST commit inside one transaction
 * (structural invariant recorded here for downstream wiring).
 */
final class ConsumePartialEditGrantUseCase
{
    public function __construct(private readonly PartialEditGrantEnforcementPolicy $policy)
    {
    }

    /**
     * @param list<DocumentMetadata> $documents
     */
    public function execute(
        PartialEditGrant $grant,
        string $fieldId,
        string $sectionId,
        string $nowTimestamp,
        array $documents,
    ): PartialEditGrant {
        $decision = $this->policy->decide($grant, $fieldId, $sectionId, $nowTimestamp, $documents);
        if (! $decision->permitted) {
            throw new InvalidArgumentException(
                'Grant consumption denied: ' . implode(',', $decision->reasonCodes),
            );
        }
        return $grant->singleUse
            ? $grant->withState(PartialEditGrant::STATE_CONSUMED)
            : $grant;
    }
}
