<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Certificates;

use InvalidArgumentException;
use Modules\JeaServices\Domain\Certificates\Contracts\CertificateRenderingPort;
use Modules\JeaServices\Domain\Certificates\Contracts\CertificateSigningPort;
use Modules\JeaServices\Domain\Certificates\ValueObjects\CertificateEligibilityDecision;
use Modules\JeaServices\Domain\Certificates\ValueObjects\CertificateIssuanceRequest;
use Modules\JeaServices\Domain\Payment\ValueObjects\PaymentEligibilityDecision;
use Modules\JeaServices\Domain\Reviews\ValueObjects\ReviewDecision;
use Modules\JeaServices\Domain\Reviews\ValueObjects\ReviewNote;
use PHPUnit\Framework\TestCase;

class CertificateFoundationTest extends TestCase
{
    // (20) payment boundary does not perform payment.
    public function test_payment_eligibility_decision_is_boundary_only(): void
    {
        $d = new PaymentEligibilityDecision(
            outcome:       PaymentEligibilityDecision::ELIGIBLE,
            applicationId: 10,
        );
        $this->assertTrue($d->isEligible());
        // The VO has no side-effect methods — it CANNOT initiate
        // payment. Prove structurally: no method named 'pay',
        // 'initiate', 'charge', 'confirm', 'settle', 'refund'.
        foreach (['pay', 'initiate', 'charge', 'confirm', 'settle', 'refund'] as $bad) {
            $this->assertFalse(method_exists($d, $bad),
                "PaymentEligibilityDecision must NOT expose method '{$bad}' (boundary only)");
        }
    }

    public function test_payment_eligibility_rejects_unknown_outcome(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PaymentEligibilityDecision('PAID', 1);
    }

    // (21) certificate blocked without payment evidence — structural.
    public function test_certificate_eligibility_blocked_records_reason(): void
    {
        $d = new CertificateEligibilityDecision(
            outcome:       CertificateEligibilityDecision::BLOCKED,
            applicationId: 10,
            reasonCodes:   ['PAYMENT_EVIDENCE_MISSING'],
        );
        $this->assertFalse($d->isEligible());
        $this->assertContains('PAYMENT_EVIDENCE_MISSING', $d->reasonCodes);
    }

    // (22) certificate blocked when workflow decision unresolved.
    public function test_certificate_eligibility_blocked_when_workflow_od_open(): void
    {
        $d = new CertificateEligibilityDecision(
            outcome:       CertificateEligibilityDecision::BLOCKED,
            applicationId: 10,
            reasonCodes:   ['WORKFLOW_TRANSITION_BLOCKED_BY_OD'],
            blockingOds:   ['OD-34'],
        );
        $this->assertFalse($d->isEligible());
        $this->assertContains('OD-34', $d->blockingOds);
    }

    // (23) production certificate issuance remains inactive —
    // structural: default request status is DRAFT (never ISSUED).
    public function test_new_certificate_issuance_request_defaults_to_DRAFT(): void
    {
        $req = new CertificateIssuanceRequest(
            requestId:                'req-1',
            applicationId:            10,
            certificateRuleVersionId: 'cert-rule-td07-v1',
        );
        $this->assertSame(CertificateIssuanceRequest::STATUS_DRAFT, $req->status);
    }

    public function test_certificate_issuance_request_rejects_unknown_status(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CertificateIssuanceRequest(
            requestId:                'req-1',
            applicationId:            10,
            certificateRuleVersionId: 'cert-rule-td07-v1',
            status:                   'AUTO_APPROVED',
        );
    }

    // Certificate ports are interfaces only — no default implementation.
    public function test_certificate_rendering_and_signing_ports_are_interfaces(): void
    {
        $this->assertTrue(interface_exists(CertificateRenderingPort::class));
        $this->assertTrue(interface_exists(CertificateSigningPort::class));
    }

    // (9) mandatory rejection note enforced.
    public function test_review_decision_reject_requires_mandatory_rejection_note(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ReviewDecision(
            outcome:       ReviewDecision::OUTCOME_REJECT,
            applicationId: 10,
            reviewerUserId: 1,
            reviewerRole:  'first_reviewer',
            timestamp:     '2026-08-02T00:00:00Z',
            notes:         [],
        );
    }

    // Approve does not require a note.
    public function test_review_decision_approve_allowed_without_note(): void
    {
        $d = new ReviewDecision(
            outcome:       ReviewDecision::OUTCOME_APPROVE,
            applicationId: 10,
            reviewerUserId: 1,
            reviewerRole:  'first_reviewer',
            timestamp:     '2026-08-02T00:00:00Z',
        );
        $this->assertSame(ReviewDecision::OUTCOME_APPROVE, $d->outcome);
    }

    // (10) community observation is not automatically blocking.
    public function test_community_observation_note_is_not_automatically_blocking(): void
    {
        $n = new ReviewNote(
            category:     ReviewNote::CATEGORY_COMMUNITY_OBSERVATION,
            noteTextAr:   'ملاحظة مجتمعية',
            authorUserId: 1,
            timestamp:    '2026-08-02T00:00:00Z',
        );
        $this->assertFalse($n->isAutomaticallyBlocking());
    }

    public function test_mandatory_rejection_note_is_automatically_blocking(): void
    {
        $n = new ReviewNote(
            category:     ReviewNote::CATEGORY_MANDATORY_REJECTION,
            noteTextAr:   'سبب الرفض',
            authorUserId: 1,
            timestamp:    '2026-08-02T00:00:00Z',
        );
        $this->assertTrue($n->isAutomaticallyBlocking());
    }
}
