<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001;

use Modules\JeaServices\Domain\Srv001\Contracts\EngineeringCeilingPort;
use Modules\JeaServices\Domain\Srv001\Contracts\MandatoryNotesPort;
use Modules\JeaServices\Domain\Srv001\Contracts\OfficeCorrectionStatusPort;
use Modules\JeaServices\Domain\Srv001\Contracts\OfficeEligibilityPort;
use Modules\JeaServices\Domain\Srv001\Contracts\OfficeQuotaPort;
use Modules\JeaServices\Domain\Srv001\Contracts\Srv001EligibilityOutcome;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;

/**
 * TD-05 · Aggregator that composes the office-level eligibility ports
 * into a single decision.
 *
 * FAIL-CLOSED: the first non-permissive port decision wins — the
 * gate returns that decision verbatim. Every port's audit envelope
 * is retained so downstream consumers can reconstruct the full
 * decision trace.
 *
 * INVARIANT: NOT WIRED TO RUNTIME. `ApplicationController::submit`
 * does not resolve this gate today; TD-05 registers structural
 * pieces only. The gate is intended for TD-05+ when the eligibility
 * pipeline lands.
 *
 * The gate is service-code-agnostic in construction but only wired
 * for SRV-001 today. Callers that want to reuse the pattern for
 * other services must instantiate their own aggregator.
 */
final class Srv001EligibilityGate
{
    public function __construct(
        private readonly OfficeEligibilityPort $officeEligibility,
        private readonly OfficeQuotaPort $officeQuota,
        private readonly EngineeringCeilingPort $engineeringCeiling,
        private readonly OfficeCorrectionStatusPort $correctionStatus,
        private readonly MandatoryNotesPort $mandatoryNotes,
    ) {
    }

    /**
     * @return array{decision: Srv001PortDecision, audit_trail: list<Srv001PortDecision>}
     */
    public function decide(
        int $organizationId,
        int $requestedM2,
        ?string $basinNumber,
        ?string $parcelNumber,
        string $correlationId,
    ): array {
        $trail = [];

        $steps = [
            fn () => $this->officeEligibility->evaluateOffice($organizationId, $correlationId),
            fn () => $this->correctionStatus->checkCorrectionStatus($organizationId, $correlationId),
            fn () => $this->mandatoryNotes->checkMandatoryNotes($organizationId, $basinNumber, $parcelNumber, $correlationId),
            fn () => $this->officeQuota->checkYearlyQuota($organizationId, $requestedM2, $correlationId),
            fn () => $this->engineeringCeiling->checkCeiling($organizationId, $requestedM2, $correlationId),
        ];

        foreach ($steps as $step) {
            $decision = $step();
            $trail[]  = $decision;
            if ($decision->isBlocking()) {
                return ['decision' => $decision, 'audit_trail' => $trail];
            }
        }

        // All ports permissive → aggregate ELIGIBLE with the last
        // port's audit envelope (any envelope proves the pipeline ran
        // end-to-end).
        $last = end($trail);
        return [
            'decision'    => new Srv001PortDecision(
                outcome: Srv001EligibilityOutcome::ELIGIBLE,
                audit:   $last->audit,
                payload: ['aggregated' => true, 'steps' => count($trail)],
            ),
            'audit_trail' => $trail,
        ];
    }
}
