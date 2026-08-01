<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents\UseCases;

use Modules\JeaServices\Domain\Documents\EditPermissionDecision;
use Modules\JeaServices\Domain\Documents\PartialEditGrantEnforcementPolicy;
use Modules\JeaServices\Domain\Documents\ValueObjects\DocumentMetadata;
use Modules\JeaServices\Domain\Documents\ValueObjects\PartialEditGrant;

/**
 * TD-07 · Non-mutating validation of a partial-edit attempt.
 *
 * Returns an EditPermissionDecision. Callers use this before opening
 * a transaction; if the decision is denied, no consumption should
 * be attempted.
 */
final class ValidatePartialEditAttemptUseCase
{
    public function __construct(private readonly PartialEditGrantEnforcementPolicy $policy)
    {
    }

    /** @param list<DocumentMetadata> $documents */
    public function execute(
        PartialEditGrant $grant,
        string $fieldId,
        string $sectionId,
        string $nowTimestamp,
        array $documents,
    ): EditPermissionDecision {
        return $this->policy->decide($grant, $fieldId, $sectionId, $nowTimestamp, $documents);
    }
}
