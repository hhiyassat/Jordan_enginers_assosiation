<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Srv001;

use InvalidArgumentException;
use Modules\JeaServices\Adapters\Srv001\ContractMissingOracleDecisionAdapter;
use Modules\JeaServices\Adapters\Srv001\InMemoryOfficeQuotaAdapter;
use Modules\JeaServices\Domain\Srv001\Contracts\Srv001EligibilityOutcome;
use Modules\JeaServices\Domain\Srv001\ValueObjects\InternalMandatoryNote;
use Modules\JeaServices\Domain\Srv001\ValueObjects\QuotaIncreaseReferral;
use PHPUnit\Framework\TestCase;

/**
 * TD-05 · Additional coverage: fail-closed defaults on absent
 * contracts, structural entity invariants, and the guard that no
 * generic engine ever adds an SRV-001 branch.
 */
class Srv001PortsAndEntitiesTest extends TestCase
{
    // (13) contract-missing default — Oracle adapter fails closed even for valid inputs.
    public function test_contract_missing_oracle_adapter_always_returns_CONTRACT_MISSING(): void
    {
        $adapter  = new ContractMissingOracleDecisionAdapter();
        $decision = $adapter->retrieveDecision(1, 2, 'corr-1');
        $this->assertSame(Srv001EligibilityOutcome::CONTRACT_MISSING, $decision->outcome);
        $this->assertTrue($decision->isBlocking());
        $this->assertSame('OD-30', $decision->audit->blockingOd);
        $this->assertContains('ORACLE_INTEGRATION_CONTRACT_UNSIGNED', $decision->audit->reasonCodes);
    }

    // (14) quota adapter fails closed on absent org.
    public function test_quota_adapter_fails_closed_when_no_remaining_recorded(): void
    {
        $adapter = new InMemoryOfficeQuotaAdapter();
        $d       = $adapter->checkYearlyQuota(999, 100, 'corr-1');
        $this->assertSame(Srv001EligibilityOutcome::CONTRACT_MISSING, $d->outcome);
    }

    // (15) QuotaIncreaseReferral invariants.
    public function test_quota_increase_referral_requires_positive_m2_and_valid_status(): void
    {
        $r = new QuotaIncreaseReferral(
            referralId:          'r-1',
            applicationId:       10,
            requestedM2Increase: 500,
            justificationText:   'test',
            feeAmount:           25,
        );
        $this->assertSame(QuotaIncreaseReferral::DECISION_PENDING, $r->decisionStatus);
        $approved = $r->withDecision(QuotaIncreaseReferral::DECISION_APPROVED, ['REV-01'], 'sess-1');
        $this->assertSame(QuotaIncreaseReferral::DECISION_APPROVED, $approved->decisionStatus);
        $this->assertSame(['REV-01'], $approved->reasonCodes);
        // Original is unchanged (immutability).
        $this->assertSame(QuotaIncreaseReferral::DECISION_PENDING, $r->decisionStatus);
    }

    public function test_quota_increase_referral_rejects_non_positive_m2(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new QuotaIncreaseReferral(
            referralId: 'r-1',
            applicationId: 10,
            requestedM2Increase: 0,
            justificationText: 'test',
            feeAmount: 25,
        );
    }

    // (16) InternalMandatoryNote invariants.
    public function test_internal_mandatory_note_blocks_when_active_and_effect_BLOCK(): void
    {
        $blocker = new InternalMandatoryNote(
            noteId:         'n-1',
            scope:          InternalMandatoryNote::SCOPE_OFFICE,
            organizationId: 1,
            basinNumber:    null,
            parcelNumber:   null,
            effect:         InternalMandatoryNote::EFFECT_BLOCK,
            noteTextAr:     'ملاحظة عاجلة',
            isActive:       true,
        );
        $this->assertTrue($blocker->blocksSubmission());

        $warn = new InternalMandatoryNote(
            noteId:         'n-2',
            scope:          InternalMandatoryNote::SCOPE_OFFICE,
            organizationId: 1,
            basinNumber:    null,
            parcelNumber:   null,
            effect:         InternalMandatoryNote::EFFECT_WARN,
            noteTextAr:     'تنبيه',
            isActive:       true,
        );
        $this->assertFalse($warn->blocksSubmission());
    }

    public function test_parcel_scoped_note_requires_basin_and_parcel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InternalMandatoryNote(
            noteId:         'n-1',
            scope:          InternalMandatoryNote::SCOPE_PARCEL,
            organizationId: null,
            basinNumber:    null,
            parcelNumber:   null,
            effect:         InternalMandatoryNote::EFFECT_BLOCK,
            noteTextAr:     'ملاحظة',
        );
    }

    // (17) vendor payload → internal DTO translation invariant.
    // The test simulates a raw external payload being translated into
    // a QuotaIncreaseReferral VO WITHOUT crossing into Domain code as
    // a vendor object. We assert the resulting VO carries none of the
    // vendor field names.
    public function test_vendor_payload_translated_to_internal_DTO(): void
    {
        $vendor = [
            'vendor_referral_id' => 'V-99',
            'vendor_org_id'      => 42,
            'vendor_m2'          => 750,
            'vendor_justif'      => 'need extra capacity Q3',
            'vendor_fee_amount'  => 30,
        ];
        // Simulated adapter-side translation:
        $referral = new QuotaIncreaseReferral(
            referralId:          $vendor['vendor_referral_id'],
            applicationId:       $vendor['vendor_org_id'],
            requestedM2Increase: $vendor['vendor_m2'],
            justificationText:   $vendor['vendor_justif'],
            feeAmount:           $vendor['vendor_fee_amount'],
        );
        $this->assertSame('V-99',                    $referral->referralId);
        $this->assertSame(42,                        $referral->applicationId);
        $this->assertSame(750,                       $referral->requestedM2Increase);
        $this->assertSame('need extra capacity Q3',  $referral->justificationText);
        // Vendor keys must NOT appear on the domain VO.
        $props = get_object_vars($referral);
        $this->assertArrayNotHasKey('vendor_referral_id', $props);
        $this->assertArrayNotHasKey('vendor_org_id',      $props);
    }

    // (18) target calculation runtime remains inactive after TD-05 —
    // adding ports must not accidentally bind Target* calculators.
    public function test_target_calculators_still_unbound_after_TD05(): void
    {
        foreach ([
            'Modules\\JeaServices\\Domain\\Srv001\\TargetSrv001SubmissionPolicy',
            'Modules\\JeaServices\\Domain\\Srv001\\Calculators\\TargetExplorationRequirementMatrixCalculator',
            'Modules\\JeaServices\\Domain\\Srv001\\Calculators\\TargetWellsCountCalculator',
            'Modules\\JeaServices\\Domain\\Srv001\\Calculators\\TargetNetDepthTableCalculator',
        ] as $cls) {
            $this->assertFalse(
                app()->bound($cls),
                "TD-05 must not bind {$cls} — TARGET_RUNTIME_STATUS=INACTIVE",
            );
        }
    }
}
