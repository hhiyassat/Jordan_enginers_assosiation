<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\ValueObjects;

use InvalidArgumentException;
use Modules\JeaServices\Domain\Srv001\Contracts\Srv001EligibilityOutcome;

/**
 * TD-05 · Immutable decision returned by every SRV-001 eligibility /
 * external port.
 *
 * FAIL-CLOSED by construction: `isPermissive()` returns true ONLY
 * when the outcome is in `Srv001EligibilityOutcome::permissiveOutcomes()`.
 * A new outcome added to the enum defaults to blocking — no receiver
 * needs to be updated to keep its guard closed.
 *
 * The decision carries a mandatory `Srv001PortAuditEnvelope` so every
 * downstream consumer (Srv001EligibilityGate, WorkflowEngine, audit
 * writer) has enough evidence to record the decision without ever
 * reaching back through the port for a second lookup.
 */
final class Srv001PortDecision
{
    /**
     * @param string $outcome  one of Srv001EligibilityOutcome::*
     * @param array<string, mixed> $payload  free-form domain-owned
     *   details (e.g., quota_used, correction_target_field). MUST NOT
     *   contain credentials or vendor response bytes.
     */
    public function __construct(
        public readonly string $outcome,
        public readonly Srv001PortAuditEnvelope $audit,
        public readonly array $payload = [],
    ) {
        if (! Srv001EligibilityOutcome::isValid($outcome)) {
            throw new InvalidArgumentException("Unknown Srv001EligibilityOutcome: {$outcome}");
        }
    }

    public function isPermissive(): bool
    {
        return in_array(
            $this->outcome,
            Srv001EligibilityOutcome::permissiveOutcomes(),
            true,
        );
    }

    public function isBlocking(): bool
    {
        return ! $this->isPermissive();
    }

    public static function eligible(Srv001PortAuditEnvelope $audit, array $payload = []): self
    {
        return new self(Srv001EligibilityOutcome::ELIGIBLE, $audit, $payload);
    }

    public static function ineligible(
        Srv001PortAuditEnvelope $audit,
        array $payload = [],
    ): self {
        return new self(Srv001EligibilityOutcome::INELIGIBLE, $audit, $payload);
    }

    /**
     * Fail-closed shortcut for external-provider failure paths.
     */
    public static function externalUnavailable(Srv001PortAuditEnvelope $audit): self
    {
        return new self(Srv001EligibilityOutcome::EXTERNAL_UNAVAILABLE, $audit);
    }

    /**
     * Fail-closed shortcut for missing external contract.
     */
    public static function contractMissing(
        Srv001PortAuditEnvelope $audit,
        ?string $providerBlockingOd = null,
    ): self {
        return new self(
            Srv001EligibilityOutcome::CONTRACT_MISSING,
            new Srv001PortAuditEnvelope(
                correlationId:          $audit->correlationId,
                providerId:             $audit->providerId,
                sourceKind:             $audit->sourceKind,
                responseClassification: $audit->responseClassification,
                timestamp:              $audit->timestamp,
                sourceStatus:           $audit->sourceStatus,
                blockingOd:             $providerBlockingOd ?? $audit->blockingOd,
                reasonCodes:            array_merge($audit->reasonCodes, ['NO_SIGNED_INTEGRATION_CONTRACT']),
            ),
        );
    }
}
