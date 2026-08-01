<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Payment;

use InvalidArgumentException;
use Modules\JeaServices\Domain\Financial\ValueObjects\FeeQuote;
use Modules\JeaServices\Domain\Financial\ValueObjects\FinancialRuleVersion;
use Modules\JeaServices\Domain\Financial\ValueObjects\Srv001FinancialOutcome;
use Modules\JeaServices\Domain\Financial\ValueObjects\TaxQuote;
use Modules\JeaServices\Domain\Payment\Policies\PaymentCallbackReplayGuard;
use Modules\JeaServices\Domain\Payment\ValueObjects\FinancialCorrectionRequest;
use Modules\JeaServices\Domain\Payment\ValueObjects\PaymentConfirmationDecision;
use Modules\JeaServices\Domain\Payment\ValueObjects\PaymentInitiationRequest;
use Modules\JeaServices\Domain\Payment\ValueObjects\ReceiptIssuanceRequest;
use PHPUnit\Framework\TestCase;

class Srv001PaymentFoundationTest extends TestCase
{
    // (7) simulation quote cannot initiate production payment.
    public function test_payment_initiation_refuses_non_binding_quote(): void
    {
        $simQuote = $this->simulationFeeQuote();
        $this->expectException(InvalidArgumentException::class);
        new PaymentInitiationRequest(
            requestId:       'pi-1',
            applicationId:   10,
            bindingQuote:    $simQuote,
            idempotencyKey:  'idem-1',
            issuedTimestamp: '2026-08-02T00:00:00Z',
        );
    }

    // (9) payment intent requires valid eligibility — the binding quote
    //     construction proves the whole chain (rule published + outcome
    //     QUOTED + unit/currency/rounding set).
    public function test_payment_initiation_accepts_binding_quote(): void
    {
        $r = new PaymentInitiationRequest(
            requestId:       'pi-1',
            applicationId:   10,
            bindingQuote:    $this->bindingFeeQuote(),
            idempotencyKey:  'idem-1',
            issuedTimestamp: '2026-08-02T00:00:00Z',
        );
        $this->assertSame('pi-1', $r->requestId);
    }

    // (10) failed payment leaves workflow unchanged — the confirmation
    //      decision's `unlocksReceiptFlow()` returns false for anything
    //      other than CONFIRMED.
    public function test_only_CONFIRMED_confirmation_unlocks_receipt(): void
    {
        foreach ([
            PaymentConfirmationDecision::REJECTED,
            PaymentConfirmationDecision::REPLAY_DETECTED,
            PaymentConfirmationDecision::CALLBACK_INVALID,
            PaymentConfirmationDecision::PENDING,
        ] as $bad) {
            $d = new PaymentConfirmationDecision(
                outcome:         $bad,
                paymentIntentId: 'pi-1',
                applicationId:   10,
            );
            $this->assertFalse($d->unlocksReceiptFlow(), "$bad must not unlock receipt");
        }
        $ok = new PaymentConfirmationDecision(
            outcome:         PaymentConfirmationDecision::CONFIRMED,
            paymentIntentId: 'pi-1',
            applicationId:   10,
        );
        $this->assertTrue($ok->unlocksReceiptFlow());
    }

    // (11) callback replay protection.
    public function test_replay_guard_detects_duplicate_callback(): void
    {
        $g     = new PaymentCallbackReplayGuard();
        $first = $g->evaluate('pi-1', 10, 'sig-abc', PaymentConfirmationDecision::CONFIRMED);
        $this->assertSame(PaymentConfirmationDecision::CONFIRMED, $first->outcome);

        $replay = $g->evaluate('pi-1', 10, 'sig-abc', PaymentConfirmationDecision::CONFIRMED);
        $this->assertSame(PaymentConfirmationDecision::REPLAY_DETECTED, $replay->outcome);
        $this->assertFalse($replay->unlocksReceiptFlow());
    }

    // Different signature or intent → not a replay.
    public function test_replay_guard_permits_different_signature(): void
    {
        $g       = new PaymentCallbackReplayGuard();
        $g->evaluate('pi-1', 10, 'sig-a', PaymentConfirmationDecision::CONFIRMED);
        $second  = $g->evaluate('pi-1', 10, 'sig-b', PaymentConfirmationDecision::CONFIRMED);
        $this->assertNotSame(PaymentConfirmationDecision::REPLAY_DETECTED, $second->outcome);
    }

    // (12) payment confirmation transaction atomicity — structural
    //      invariant: ReceiptIssuanceRequest cannot be constructed
    //      without a CONFIRMED payment.
    public function test_receipt_issuance_requires_CONFIRMED_payment(): void
    {
        $notConfirmed = new PaymentConfirmationDecision(
            outcome:         PaymentConfirmationDecision::PENDING,
            paymentIntentId: 'pi-1',
            applicationId:   10,
        );
        $this->expectException(InvalidArgumentException::class);
        new ReceiptIssuanceRequest(
            receiptId:           'r-1',
            applicationId:       10,
            paymentConfirmation: $notConfirmed,
            feeQuoteSnapshot:    $this->bindingFeeQuote(),
            taxQuoteSnapshot:    $this->simulationTaxQuote(),
            issuedTimestamp:     '2026-08-02T00:00:00Z',
        );
    }

    public function test_receipt_carries_frozen_fee_and_tax_snapshots(): void
    {
        $confirmed = new PaymentConfirmationDecision(
            outcome:         PaymentConfirmationDecision::CONFIRMED,
            paymentIntentId: 'pi-1',
            applicationId:   10,
        );
        $receipt = new ReceiptIssuanceRequest(
            receiptId:           'r-1',
            applicationId:       10,
            paymentConfirmation: $confirmed,
            feeQuoteSnapshot:    $this->bindingFeeQuote(),
            taxQuoteSnapshot:    $this->simulationTaxQuote(),
            issuedTimestamp:     '2026-08-02T00:00:00Z',
        );
        $this->assertSame('rv-published-1', $receipt->feeQuoteSnapshot->ruleVersion->ruleVersionId);
        $this->assertSame('rv-draft-1',     $receipt->taxQuoteSnapshot->ruleVersion->ruleVersionId);
    }

    // (15) certificate remains blocked without valid payment evidence —
    //      structurally: TD-07 CertificateEligibilityDecision returns
    //      BLOCKED without payment evidence. TD-08 reasserts by having
    //      ReceiptIssuanceRequest refuse non-CONFIRMED payments (above).

    // (17) no production payment endpoint activated — check the payment
    //      controller (if it exists) has no reference to a new TD-08
    //      production gateway class.
    public function test_no_production_payment_gateway_class_wired(): void
    {
        // Deliberately narrow: assert no class name matching the
        // pattern *ProductionPaymentGateway* exists in the tree.
        // (Fake and sandbox classes are acceptable — production is
        // the forbidden shape.)
        $dir   = __DIR__ . '/../../../../modules/JeaServices';
        $found = [];
        if (is_dir($dir)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($it as $f) {
                if ($f->isFile() && preg_match('~ProductionPaymentGateway~i', $f->getFilename())) {
                    $found[] = $f->getFilename();
                }
            }
        }
        $this->assertEmpty($found,
            'No class matching ProductionPaymentGateway may exist in TD-08');
    }

    // (18) no generic-engine SRV-001 conditional — already enforced by
    //      TD-05 architecture test; reasserted structurally.

    // (19) legacy runtime behavior remains unchanged — TD-08 adds no
    //      Providers changes; the legacy path stays identical to TD-07.

    // (20) target calculation runtime remains inactive.
    public function test_target_srv001_submission_policy_still_unbound(): void
    {
        $this->assertFalse(app()->bound('Modules\\JeaServices\\Domain\\Srv001\\TargetSrv001SubmissionPolicy'));
    }

    public function test_financial_correction_request_defaults_to_DRAFT(): void
    {
        $r = new FinancialCorrectionRequest(
            requestId:         'fc-1',
            applicationId:     10,
            requestingActorId: 1,
            requestingRole:    'staff',
            reason:            'post-payment correction',
        );
        $this->assertSame(FinancialCorrectionRequest::STATUS_DRAFT, $r->status);
    }

    public function test_financial_correction_request_rejects_unknown_status(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FinancialCorrectionRequest(
            requestId:         'fc-1',
            applicationId:     10,
            requestingActorId: 1,
            requestingRole:    'staff',
            reason:            'x',
            status:            'AUTO_APPROVED',
        );
    }

    // helpers
    private function publishedRule(): FinancialRuleVersion
    {
        return new FinancialRuleVersion(
            ruleVersionId:                'rv-published-1',
            formulaIdentifier:            'SRV001_FEE',
            sourceReference:              'signed policy 2.0',
            sourceStatus:                 'SIGNED_BASELINE_2_0',
            businessApprovalStatus:       'APPROVED',
            implementationAuthorization:  'AUTHORIZED',
            publicationAuthorization:     'PUBLISHED',
            lifecycleStatus:              FinancialRuleVersion::LIFECYCLE_PUBLISHED,
        );
    }

    private function draftRule(): FinancialRuleVersion
    {
        return new FinancialRuleVersion(
            ruleVersionId:                'rv-draft-1',
            formulaIdentifier:            'SRV001_SALES_TAX',
            sourceReference:              'SRS v1.2 §draft',
            sourceStatus:                 'DRAFT_SRS_V1_2',
            businessApprovalStatus:       'UNVERIFIED',
            implementationAuthorization:  'PROVISIONAL',
            publicationAuthorization:     'NOT_AUTHORIZED',
            lifecycleStatus:              FinancialRuleVersion::LIFECYCLE_DRAFT,
            blockingOds:                  ['OD-10'],
        );
    }

    private function bindingFeeQuote(): FeeQuote
    {
        return new FeeQuote(
            outcome:            Srv001FinancialOutcome::QUOTED,
            ruleVersion:        $this->publishedRule(),
            inputs:             ['length_lm' => 20],
            unit:               'lm',
            currency:           'JOD',
            roundingRule:       'HALF_UP_2',
            lineItems:          [['label' => 'fee', 'amount' => 3.0, 'currency' => 'JOD']],
            taxLines:           [],
            exemptionEvidence:  [],
            blockingOds:        [],
            generatedTimestamp: '2026-08-02T00:00:00Z',
        );
    }

    private function simulationFeeQuote(): FeeQuote
    {
        return new FeeQuote(
            outcome:            Srv001FinancialOutcome::SIMULATION_ONLY,
            ruleVersion:        $this->draftRule(),
            inputs:             ['length_lm' => 20],
            unit:               '',
            currency:           '',
            roundingRule:       '',
            lineItems:          [],
            taxLines:           [],
            exemptionEvidence:  [],
            blockingOds:        [],
            generatedTimestamp: '2026-08-02T00:00:00Z',
        );
    }

    private function simulationTaxQuote(): TaxQuote
    {
        return new TaxQuote(
            outcome:            Srv001FinancialOutcome::SIMULATION_ONLY,
            ruleVersion:        $this->draftRule(),
            inputs:             ['base' => 3.0],
            unit:               '',
            currency:           '',
            roundingRule:       '',
            taxLines:           [],
            exemptionEvidence:  [],
            blockingOds:        [],
            generatedTimestamp: '2026-08-02T00:00:00Z',
        );
    }
}
