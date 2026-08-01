<?php

declare(strict_types=1);

namespace Modules\JeaServices\Adapters\Srv001;

use Modules\JeaServices\Domain\Srv001\Contracts\OfficeQuotaPort;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortAuditEnvelope;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-05 · Deterministic in-memory fake adapter for OfficeQuotaPort.
 *
 * NOT_FOR_PRODUCTION. Callers set an office's remaining m2 via
 * `withRemainingM2()`; the adapter returns ELIGIBLE when the
 * request fits and QUOTA_EXHAUSTED otherwise.
 */
final class InMemoryOfficeQuotaAdapter implements OfficeQuotaPort
{
    public const PROVIDER_ID = 'in-memory-fake-office-quota';

    /** @var array<int, int> orgId => remainingM2 */
    private array $remaining = [];

    public function withRemainingM2(int $organizationId, int $remainingM2): self
    {
        $this->remaining[$organizationId] = $remainingM2;
        return $this;
    }

    public function checkYearlyQuota(
        int $organizationId,
        int $requestedM2,
        string $correlationId,
    ): Srv001PortDecision {
        $envelope = new Srv001PortAuditEnvelope(
            correlationId:          $correlationId,
            providerId:             self::PROVIDER_ID,
            sourceKind:             Srv001PortAuditEnvelope::KIND_FAKE,
            responseClassification: 'quota_lookup',
            timestamp:              date('c'),
            sourceStatus:           'DRAFT',
        );

        // No override for this org — the adapter has no source of
        // truth, so it must fail closed with CONTRACT_MISSING (not
        // ELIGIBLE).
        if (! array_key_exists($organizationId, $this->remaining)) {
            return Srv001PortDecision::contractMissing($envelope, 'OD-quota-integration');
        }

        $remaining = $this->remaining[$organizationId];
        if ($requestedM2 > $remaining) {
            return new Srv001PortDecision(
                outcome: \Modules\JeaServices\Domain\Srv001\Contracts\Srv001EligibilityOutcome::QUOTA_EXHAUSTED,
                audit:   $envelope,
                payload: ['remaining_m2' => $remaining, 'requested_m2' => $requestedM2],
            );
        }

        return Srv001PortDecision::eligible(
            $envelope,
            ['remaining_m2' => $remaining, 'requested_m2' => $requestedM2],
        );
    }
}
