<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Srv001;

use InvalidArgumentException;
use Modules\JeaServices\Domain\Srv001\Contracts\Srv001EligibilityOutcome;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortAuditEnvelope;
use Modules\JeaServices\Domain\Srv001\ValueObjects\Srv001PortDecision;
use PHPUnit\Framework\TestCase;

/**
 * TD-05 · Enum + envelope + decision unit tests. Pure domain — no DB,
 * no HTTP, no container.
 */
class Srv001EligibilityOutcomeTest extends TestCase
{
    public function test_enum_exposes_the_twelve_documented_states(): void
    {
        $this->assertCount(12, Srv001EligibilityOutcome::all());
        $this->assertContains('ELIGIBLE', Srv001EligibilityOutcome::all());
        $this->assertContains('CONTRACT_MISSING', Srv001EligibilityOutcome::all());
        $this->assertContains('EXTERNAL_UNAVAILABLE', Srv001EligibilityOutcome::all());
        $this->assertContains('QUOTA_EXHAUSTED', Srv001EligibilityOutcome::all());
    }

    public function test_only_ELIGIBLE_and_NOT_APPLICABLE_are_permissive(): void
    {
        $this->assertSame(
            ['ELIGIBLE', 'NOT_APPLICABLE'],
            Srv001EligibilityOutcome::permissiveOutcomes(),
        );
    }

    public function test_permissive_and_blocking_partition_the_enum(): void
    {
        $union = array_merge(
            Srv001EligibilityOutcome::permissiveOutcomes(),
            Srv001EligibilityOutcome::blockingOutcomes(),
        );
        sort($union);
        $all = Srv001EligibilityOutcome::all();
        sort($all);
        $this->assertSame($all, $union);
    }

    public function test_decision_construction_rejects_unknown_outcome(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Srv001PortDecision(outcome: 'NOT_A_STATE', audit: $this->envelope());
    }

    public function test_decision_isPermissive_true_only_for_ELIGIBLE_or_NOT_APPLICABLE(): void
    {
        foreach (Srv001EligibilityOutcome::all() as $outcome) {
            $d = new Srv001PortDecision(outcome: $outcome, audit: $this->envelope());
            if (in_array($outcome, ['ELIGIBLE', 'NOT_APPLICABLE'], true)) {
                $this->assertTrue($d->isPermissive(), "$outcome must be permissive");
                $this->assertFalse($d->isBlocking());
            } else {
                $this->assertFalse($d->isPermissive(), "$outcome must be blocking");
                $this->assertTrue($d->isBlocking());
            }
        }
    }

    public function test_envelope_rejects_credential_shaped_reason_codes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Srv001PortAuditEnvelope(
            correlationId:          'c1',
            providerId:             'p',
            sourceKind:             Srv001PortAuditEnvelope::KIND_FAKE,
            responseClassification: 'x',
            timestamp:              '2026-08-02T00:00:00Z',
            sourceStatus:           'DRAFT',
            reasonCodes:            ['bearer eyJhbGc...'],
        );
    }

    public function test_envelope_rejects_unknown_source_kind(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Srv001PortAuditEnvelope(
            correlationId:          'c1',
            providerId:             'p',
            sourceKind:             'MYSTERY',
            responseClassification: 'x',
            timestamp:              '2026-08-02T00:00:00Z',
            sourceStatus:           'DRAFT',
        );
    }

    public function test_envelope_toAuditExtras_carries_all_documented_fields(): void
    {
        $e = new Srv001PortAuditEnvelope(
            correlationId:          'corr-1',
            providerId:             'in-memory',
            sourceKind:             Srv001PortAuditEnvelope::KIND_FAKE,
            responseClassification: 'ok',
            timestamp:              '2026-08-02T00:00:00Z',
            sourceStatus:           'DRAFT',
            blockingOd:             'OD-30',
            reasonCodes:            ['SANDBOX_ONLY'],
        );
        $x = $e->toAuditExtras();
        $this->assertSame('corr-1',                     $x['correlation_id']);
        $this->assertSame('in-memory',                  $x['provider_id']);
        $this->assertSame(Srv001PortAuditEnvelope::KIND_FAKE, $x['source_kind']);
        $this->assertSame('ok',                         $x['response_classification']);
        $this->assertSame('2026-08-02T00:00:00Z',       $x['timestamp']);
        $this->assertSame('DRAFT',                      $x['source_status']);
        $this->assertSame('OD-30',                      $x['blocking_od']);
        $this->assertSame(['SANDBOX_ONLY'],             $x['reason_codes']);
    }

    private function envelope(): Srv001PortAuditEnvelope
    {
        return new Srv001PortAuditEnvelope(
            correlationId:          'c1',
            providerId:             'p',
            sourceKind:             Srv001PortAuditEnvelope::KIND_FAKE,
            responseClassification: 'x',
            timestamp:              '2026-08-02T00:00:00Z',
            sourceStatus:           'DRAFT',
        );
    }
}
