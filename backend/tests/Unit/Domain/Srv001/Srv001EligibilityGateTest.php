<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Srv001;

use Modules\JeaServices\Adapters\Srv001\InMemoryOfficeEligibilityAdapter;
use Modules\JeaServices\Adapters\Srv001\InMemoryOfficeQuotaAdapter;
use Modules\JeaServices\Domain\Srv001\Contracts\EngineeringCeilingPort;
use Modules\JeaServices\Domain\Srv001\Contracts\MandatoryNotesPort;
use Modules\JeaServices\Domain\Srv001\Contracts\OfficeCorrectionStatusPort;
use Modules\JeaServices\Domain\Srv001\Contracts\Srv001EligibilityOutcome;
use Modules\JeaServices\Domain\Srv001\Srv001EligibilityGate;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortAuditEnvelope;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;
use PHPUnit\Framework\TestCase;

/**
 * TD-05 · Aggregator tests exercising the fail-closed composition
 * of five office-level ports.
 *
 * The 18 required TD-05 test items map to these + the
 * `Srv001EligibilityOutcomeTest` cases + the vendor-payload-DTO
 * translation test in `TitleDeedQrExtractionTest`.
 */
class Srv001EligibilityGateTest extends TestCase
{
    // (1) eligible result — all ports permissive.
    public function test_gate_returns_ELIGIBLE_when_every_port_is_permissive(): void
    {
        $gate = $this->buildGate(
            eligibility:       (new InMemoryOfficeEligibilityAdapter())->withOutcomeFor(1, Srv001EligibilityOutcome::ELIGIBLE),
            quota:             (new InMemoryOfficeQuotaAdapter())->withRemainingM2(1, 5000),
            correctionOutcome: Srv001EligibilityOutcome::ELIGIBLE,
            noteOutcome:       Srv001EligibilityOutcome::NOT_APPLICABLE,
            ceilingOutcome:    Srv001EligibilityOutcome::ELIGIBLE,
        );
        $result = $gate->decide(1, 100, '007', '042', 'corr-1');
        $this->assertSame(Srv001EligibilityOutcome::ELIGIBLE, $result['decision']->outcome);
        $this->assertCount(5, $result['audit_trail']);
    }

    // (2) ineligible result — office eligibility fails first.
    public function test_gate_returns_INELIGIBLE_when_office_eligibility_port_says_so(): void
    {
        $gate = $this->buildGate(
            eligibility:       (new InMemoryOfficeEligibilityAdapter())->withOutcomeFor(1, Srv001EligibilityOutcome::INELIGIBLE),
            quota:             (new InMemoryOfficeQuotaAdapter())->withRemainingM2(1, 5000),
            correctionOutcome: Srv001EligibilityOutcome::ELIGIBLE,
            noteOutcome:       Srv001EligibilityOutcome::NOT_APPLICABLE,
            ceilingOutcome:    Srv001EligibilityOutcome::ELIGIBLE,
        );
        $result = $gate->decide(1, 100, '007', '042', 'corr-1');
        $this->assertSame(Srv001EligibilityOutcome::INELIGIBLE, $result['decision']->outcome);
        // First blocking port wins — no downstream ports invoked after it.
        $this->assertCount(1, $result['audit_trail']);
    }

    // (3) quota exhausted.
    public function test_gate_returns_QUOTA_EXHAUSTED_when_quota_port_says_so(): void
    {
        $gate = $this->buildGate(
            eligibility:       (new InMemoryOfficeEligibilityAdapter())->withOutcomeFor(1, Srv001EligibilityOutcome::ELIGIBLE),
            quota:             (new InMemoryOfficeQuotaAdapter())->withRemainingM2(1, 50),
            correctionOutcome: Srv001EligibilityOutcome::ELIGIBLE,
            noteOutcome:       Srv001EligibilityOutcome::NOT_APPLICABLE,
            ceilingOutcome:    Srv001EligibilityOutcome::ELIGIBLE,
        );
        $result = $gate->decide(1, 100, '007', '042', 'corr-1');
        $this->assertSame(Srv001EligibilityOutcome::QUOTA_EXHAUSTED, $result['decision']->outcome);
        $this->assertSame(50, $result['decision']->payload['remaining_m2']);
    }

    // (4) correction required.
    public function test_gate_returns_CORRECTION_REQUIRED_when_correction_port_says_so(): void
    {
        $gate = $this->buildGate(
            eligibility:       (new InMemoryOfficeEligibilityAdapter())->withOutcomeFor(1, Srv001EligibilityOutcome::ELIGIBLE),
            quota:             (new InMemoryOfficeQuotaAdapter())->withRemainingM2(1, 5000),
            correctionOutcome: Srv001EligibilityOutcome::CORRECTION_REQUIRED,
            noteOutcome:       Srv001EligibilityOutcome::NOT_APPLICABLE,
            ceilingOutcome:    Srv001EligibilityOutcome::ELIGIBLE,
        );
        $result = $gate->decide(1, 100, '007', '042', 'corr-1');
        $this->assertSame(Srv001EligibilityOutcome::CORRECTION_REQUIRED, $result['decision']->outcome);
    }

    // (5) mandatory-note block.
    public function test_gate_returns_MANDATORY_NOTE_BLOCK_when_notes_port_says_so(): void
    {
        $gate = $this->buildGate(
            eligibility:       (new InMemoryOfficeEligibilityAdapter())->withOutcomeFor(1, Srv001EligibilityOutcome::ELIGIBLE),
            quota:             (new InMemoryOfficeQuotaAdapter())->withRemainingM2(1, 5000),
            correctionOutcome: Srv001EligibilityOutcome::ELIGIBLE,
            noteOutcome:       Srv001EligibilityOutcome::MANDATORY_NOTE_BLOCK,
            ceilingOutcome:    Srv001EligibilityOutcome::ELIGIBLE,
        );
        $result = $gate->decide(1, 100, '007', '042', 'corr-1');
        $this->assertSame(Srv001EligibilityOutcome::MANDATORY_NOTE_BLOCK, $result['decision']->outcome);
    }

    // (6) engineering ceiling exceeded.
    public function test_gate_returns_ENGINEERING_CEILING_EXCEEDED_when_ceiling_port_says_so(): void
    {
        $gate = $this->buildGate(
            eligibility:       (new InMemoryOfficeEligibilityAdapter())->withOutcomeFor(1, Srv001EligibilityOutcome::ELIGIBLE),
            quota:             (new InMemoryOfficeQuotaAdapter())->withRemainingM2(1, 5000),
            correctionOutcome: Srv001EligibilityOutcome::ELIGIBLE,
            noteOutcome:       Srv001EligibilityOutcome::NOT_APPLICABLE,
            ceilingOutcome:    Srv001EligibilityOutcome::ENGINEERING_CEILING_EXCEEDED,
        );
        $result = $gate->decide(1, 100, '007', '042', 'corr-1');
        $this->assertSame(Srv001EligibilityOutcome::ENGINEERING_CEILING_EXCEEDED, $result['decision']->outcome);
    }

    // (7) EXTERNAL_UNAVAILABLE — provider timeout fails closed.
    public function test_gate_treats_external_unavailable_as_blocking(): void
    {
        $gate = $this->buildGate(
            eligibility:       (new InMemoryOfficeEligibilityAdapter())->withOutcomeFor(1, Srv001EligibilityOutcome::EXTERNAL_UNAVAILABLE),
            quota:             (new InMemoryOfficeQuotaAdapter())->withRemainingM2(1, 5000),
            correctionOutcome: Srv001EligibilityOutcome::ELIGIBLE,
            noteOutcome:       Srv001EligibilityOutcome::NOT_APPLICABLE,
            ceilingOutcome:    Srv001EligibilityOutcome::ELIGIBLE,
        );
        $result = $gate->decide(1, 100, '007', '042', 'corr-1');
        $this->assertSame(Srv001EligibilityOutcome::EXTERNAL_UNAVAILABLE, $result['decision']->outcome);
        $this->assertTrue($result['decision']->isBlocking());
    }

    // (8) CONTRACT_MISSING — no signed integration contract; fails closed.
    public function test_absent_org_in_fake_adapter_defaults_to_CONTRACT_MISSING(): void
    {
        // Empty adapter with no override + allowByDefault=false ⇒
        // no ELIGIBLE fabrication.
        $gate = $this->buildGate(
            eligibility:       new InMemoryOfficeEligibilityAdapter(allowByDefault: false),
            quota:             (new InMemoryOfficeQuotaAdapter())->withRemainingM2(1, 5000),
            correctionOutcome: Srv001EligibilityOutcome::ELIGIBLE,
            noteOutcome:       Srv001EligibilityOutcome::NOT_APPLICABLE,
            ceilingOutcome:    Srv001EligibilityOutcome::ELIGIBLE,
        );
        $result = $gate->decide(999, 100, '007', '042', 'corr-1');
        $this->assertSame(Srv001EligibilityOutcome::CONTRACT_MISSING, $result['decision']->outcome);
    }

    // (9) INVALID_EXTERNAL_RESPONSE — port propagates blocking outcome.
    public function test_gate_propagates_INVALID_EXTERNAL_RESPONSE(): void
    {
        $gate = $this->buildGate(
            eligibility:       (new InMemoryOfficeEligibilityAdapter())->withOutcomeFor(1, Srv001EligibilityOutcome::INVALID_EXTERNAL_RESPONSE),
            quota:             (new InMemoryOfficeQuotaAdapter())->withRemainingM2(1, 5000),
            correctionOutcome: Srv001EligibilityOutcome::ELIGIBLE,
            noteOutcome:       Srv001EligibilityOutcome::NOT_APPLICABLE,
            ceilingOutcome:    Srv001EligibilityOutcome::ELIGIBLE,
        );
        $result = $gate->decide(1, 100, '007', '042', 'corr-1');
        $this->assertSame(Srv001EligibilityOutcome::INVALID_EXTERNAL_RESPONSE, $result['decision']->outcome);
    }

    // (10) MANUAL_REVIEW — blocking outcome.
    public function test_gate_returns_MANUAL_REVIEW_when_port_asks_for_it(): void
    {
        $gate = $this->buildGate(
            eligibility:       (new InMemoryOfficeEligibilityAdapter())->withOutcomeFor(1, Srv001EligibilityOutcome::MANUAL_REVIEW),
            quota:             (new InMemoryOfficeQuotaAdapter())->withRemainingM2(1, 5000),
            correctionOutcome: Srv001EligibilityOutcome::ELIGIBLE,
            noteOutcome:       Srv001EligibilityOutcome::NOT_APPLICABLE,
            ceilingOutcome:    Srv001EligibilityOutcome::ELIGIBLE,
        );
        $result = $gate->decide(1, 100, '007', '042', 'corr-1');
        $this->assertSame(Srv001EligibilityOutcome::MANUAL_REVIEW, $result['decision']->outcome);
    }

    // (11) audit-safe decision evidence — every port decision preserved.
    public function test_gate_preserves_audit_trail_for_every_port_invoked(): void
    {
        $gate = $this->buildGate(
            eligibility:       (new InMemoryOfficeEligibilityAdapter())->withOutcomeFor(1, Srv001EligibilityOutcome::ELIGIBLE),
            quota:             (new InMemoryOfficeQuotaAdapter())->withRemainingM2(1, 5000),
            correctionOutcome: Srv001EligibilityOutcome::ELIGIBLE,
            noteOutcome:       Srv001EligibilityOutcome::NOT_APPLICABLE,
            ceilingOutcome:    Srv001EligibilityOutcome::ELIGIBLE,
        );
        $result = $gate->decide(1, 100, '007', '042', 'corr-1');
        foreach ($result['audit_trail'] as $step) {
            $this->assertNotEmpty($step->audit->correlationId);
            $this->assertNotEmpty($step->audit->providerId);
            $this->assertContains($step->audit->sourceKind, Srv001PortAuditEnvelope::allSourceKinds());
        }
    }

    // (12) deterministic fake behaviour — repeatable outcomes.
    public function test_fake_adapter_is_deterministic(): void
    {
        $adapter = (new InMemoryOfficeEligibilityAdapter())->withOutcomeFor(1, Srv001EligibilityOutcome::ELIGIBLE);
        $first   = $adapter->evaluateOffice(1, 'c-1');
        $second  = $adapter->evaluateOffice(1, 'c-1');
        $this->assertSame($first->outcome, $second->outcome);
        $this->assertSame(InMemoryOfficeEligibilityAdapter::PROVIDER_ID, $first->audit->providerId);
    }

    // --- helpers ---

    private function buildGate(
        InMemoryOfficeEligibilityAdapter $eligibility,
        InMemoryOfficeQuotaAdapter $quota,
        string $correctionOutcome,
        string $noteOutcome,
        string $ceilingOutcome,
    ): Srv001EligibilityGate {
        return new Srv001EligibilityGate(
            officeEligibility:  $eligibility,
            officeQuota:        $quota,
            engineeringCeiling: $this->fakeCeiling($ceilingOutcome),
            correctionStatus:   $this->fakeCorrection($correctionOutcome),
            mandatoryNotes:     $this->fakeNotes($noteOutcome),
        );
    }

    private function fakeCeiling(string $outcome): EngineeringCeilingPort
    {
        return new class ($outcome) implements EngineeringCeilingPort {
            public function __construct(private readonly string $outcome) {}
            public function checkCeiling(int $organizationId, int $requestedM2, string $correlationId): Srv001PortDecision
            {
                return new Srv001PortDecision(
                    outcome: $this->outcome,
                    audit:   new Srv001PortAuditEnvelope('c', 'fake-ceiling', Srv001PortAuditEnvelope::KIND_FAKE, 'ok', date('c'), 'DRAFT'),
                );
            }
        };
    }

    private function fakeCorrection(string $outcome): OfficeCorrectionStatusPort
    {
        return new class ($outcome) implements OfficeCorrectionStatusPort {
            public function __construct(private readonly string $outcome) {}
            public function checkCorrectionStatus(int $organizationId, string $correlationId): Srv001PortDecision
            {
                return new Srv001PortDecision(
                    outcome: $this->outcome,
                    audit:   new Srv001PortAuditEnvelope('c', 'fake-correction', Srv001PortAuditEnvelope::KIND_FAKE, 'ok', date('c'), 'DRAFT'),
                );
            }
        };
    }

    private function fakeNotes(string $outcome): MandatoryNotesPort
    {
        return new class ($outcome) implements MandatoryNotesPort {
            public function __construct(private readonly string $outcome) {}
            public function checkMandatoryNotes(int $organizationId, ?string $basinNumber, ?string $parcelNumber, string $correlationId): Srv001PortDecision
            {
                return new Srv001PortDecision(
                    outcome: $this->outcome,
                    audit:   new Srv001PortAuditEnvelope('c', 'fake-notes', Srv001PortAuditEnvelope::KIND_FAKE, 'ok', date('c'), 'DRAFT'),
                );
            }
        };
    }
}
