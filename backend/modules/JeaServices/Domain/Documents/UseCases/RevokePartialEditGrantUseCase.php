<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents\UseCases;

use Modules\JeaServices\Domain\Documents\ValueObjects\PartialEditGrant;

/**
 * TD-07 · Revoke a grant. Once revoked, the state is terminal.
 * Re-issue must produce a NEW grant with a new grantId.
 */
final class RevokePartialEditGrantUseCase
{
    public function execute(PartialEditGrant $grant): PartialEditGrant
    {
        return $grant->withState(PartialEditGrant::STATE_REVOKED);
    }
}
