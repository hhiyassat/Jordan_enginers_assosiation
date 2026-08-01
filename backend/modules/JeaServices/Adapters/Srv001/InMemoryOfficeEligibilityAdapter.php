<?php

declare(strict_types=1);

namespace Modules\JeaServices\Adapters\Srv001;

use Modules\JeaServices\Domain\Srv001\Contracts\OfficeEligibilityPort;
use Modules\JeaServices\Domain\Srv001\Contracts\Srv001EligibilityOutcome;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortAuditEnvelope;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-05 · Deterministic in-memory fake adapter for OfficeEligibilityPort.
 *
 * NOT_FOR_PRODUCTION. Used by tests + local development. Any test
 * consuming this adapter is exercising the SG-05 port contract, not
 * the eventual production adapter's implementation.
 *
 * Behaviour is a lookup table keyed by organization_id — tests pre-
 * populate outcomes before invoking. Absent entries return ELIGIBLE
 * only when the adapter is put in `allowByDefault=true` mode
 * explicitly; otherwise absent lookups return CONTRACT_MISSING (the
 * fail-closed default: "we can't confirm the office is eligible, so
 * we refuse").
 */
final class InMemoryOfficeEligibilityAdapter implements OfficeEligibilityPort
{
    public const PROVIDER_ID = 'in-memory-fake-office-eligibility';

    /** @var array<int, string> orgId => outcome */
    private array $overrides = [];

    public function __construct(private readonly bool $allowByDefault = false)
    {
    }

    public function withOutcomeFor(int $organizationId, string $outcome): self
    {
        $this->overrides[$organizationId] = $outcome;
        return $this;
    }

    public function evaluateOffice(int $organizationId, string $correlationId): Srv001PortDecision
    {
        $envelope = new Srv001PortAuditEnvelope(
            correlationId:          $correlationId,
            providerId:             self::PROVIDER_ID,
            sourceKind:             Srv001PortAuditEnvelope::KIND_FAKE,
            responseClassification: 'fake_lookup',
            timestamp:              date('c'),
            sourceStatus:           'DRAFT',
        );

        $outcome = $this->overrides[$organizationId] ?? null;

        if ($outcome === null) {
            return $this->allowByDefault
                ? Srv001PortDecision::eligible($envelope, ['reason' => 'allow_by_default_test_mode'])
                : new Srv001PortDecision(
                    outcome: Srv001EligibilityOutcome::CONTRACT_MISSING,
                    audit:   $envelope,
                );
        }

        return new Srv001PortDecision(outcome: $outcome, audit: $envelope);
    }
}
