<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Documents\UseCases;

use Modules\JeaServices\Domain\Documents\ValueObjects\PartialEditGrant;

/**
 * TD-07 · Mark an active grant as EXPIRED when its expiry timestamp
 * has passed. Idempotent: a grant already in a terminal state is
 * returned unchanged.
 */
final class ExpirePartialEditGrantUseCase
{
    public function execute(PartialEditGrant $grant, string $nowTimestamp): PartialEditGrant
    {
        if ($grant->state !== PartialEditGrant::STATE_ACTIVE) {
            return $grant;
        }
        if ($grant->expiryTimestamp === null || $nowTimestamp <= $grant->expiryTimestamp) {
            return $grant;
        }
        return $grant->withState(PartialEditGrant::STATE_EXPIRED);
    }
}
