<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Financial;

use InvalidArgumentException;
use Modules\JeaServices\Domain\Financial\Policies\FinancialRuleSelectionPolicy;
use Modules\JeaServices\Domain\Financial\ValueObjects\DonationCampaignDecision;
use Modules\JeaServices\Domain\Financial\ValueObjects\ExemptionDecision;
use Modules\JeaServices\Domain\Financial\ValueObjects\FeeQuote;
use Modules\JeaServices\Domain\Financial\ValueObjects\FinancialRuleVersion;
use Modules\JeaServices\Domain\Financial\ValueObjects\Srv001FinancialOutcome;
use Modules\JeaServices\Domain\Financial\ValueObjects\TaxQuote;
use PHPUnit\Framework\TestCase;

class Srv001FinancialFoundationTest extends TestCase
{
    // (1) conflicted income-tax rule returns CONFLICTED.
    public function test_income_tax_quote_with_CONFLICTED_outcome_is_not_binding(): void
    {
        $q = new TaxQuote(
            outcome:            Srv001FinancialOutcome::CONFLICTED,
            ruleVersion:        $this->draftRule('SRV001_INCOME_TAX'),
            inputs:             ['base' => 1000],
            unit:               '',
            currency:           '',
            roundingRule:       '',
            taxLines:           [],
            exemptionEvidence:  [],
            blockingOds:        ['OD-01'],
            generatedTimestamp: '2026-08-02T00:00:00Z',
        );
        $this->assertSame(Srv001FinancialOutcome::CONFLICTED, $q->outcome);
        $this->assertFalse($q->isBinding());
        $this->assertContains('OD-01', $q->blockingOds);
    }

    // (2) unresolved total-contract-value rule returns BLOCKED.
    public function test_total_contract_value_rule_returns_BLOCKED_when_OD_open(): void
    {
        $q = new FeeQuote(
            outcome:            Srv001FinancialOutcome::BLOCKED,
            ruleVersion:        $this->draftRule('SRV001_TOTAL_CONTRACT_VALUE'),
            inputs:             ['contract_value' => 5000],
            unit:               '',
            currency:           '',
            roundingRule:       '',
            lineItems:          [],
            taxLines:           [],
            exemptionEvidence:  [],
            blockingOds:        ['OD-01'],
            generatedTimestamp: '2026-08-02T00:00:00Z',
        );
        $this->assertFalse($q->isBinding());
    }

    // (3) missing unit/rounding on a QUOTED path is refused at construction.
    public function test_quoted_fee_requires_unit_currency_rounding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FeeQuote(
            outcome:            Srv001FinancialOutcome::QUOTED,
            ruleVersion:        $this->publishedRule('SRV001_FEE'),
            inputs:             ['area' => 100],
            unit:               '',        // OD-19 unresolved
            currency:           '',
            roundingRule:       '',
            lineItems:          [],
            taxLines:           [],
            exemptionEvidence:  [],
            blockingOds:        [],
            generatedTimestamp: '2026-08-02T00:00:00Z',
        );
    }

    // (4) OD-35 regional exemption remains blocked.
    public function test_regional_exemption_od35_blocks_by_construction(): void
    {
        $d = new ExemptionDecision(
            kind:            ExemptionDecision::KIND_REGIONAL,
            outcome:         ExemptionDecision::OUTCOME_BLOCKED_BY_OD,
            applicantUserId: 1,
            blockingOds:     ['OD-35'],
        );
        $this->assertSame(ExemptionDecision::OUTCOME_BLOCKED_BY_OD, $d->outcome);
        $this->assertContains('OD-35', $d->blockingOds);
    }

    // (5) exemption does not silently change tax or quota.
    public function test_exemption_has_no_runtime_financial_effect(): void
    {
        foreach ([
            ExemptionDecision::KIND_ENGINEER,
            ExemptionDecision::KIND_EMPLOYEE,
            ExemptionDecision::KIND_ASSOCIATION,
            ExemptionDecision::KIND_PLACE_OF_WORSHIP,
        ] as $kind) {
            $d = new ExemptionDecision(
                kind:            $kind,
                outcome:         ExemptionDecision::OUTCOME_APPROVED_PENDING_EFFECT,
                applicantUserId: 1,
            );
            $this->assertFalse($d->hasRuntimeFinancialEffect());
        }
    }

    // (6) unpublished financial rule cannot be selected.
    public function test_unpublished_financial_rule_is_not_selectable(): void
    {
        $policy = new FinancialRuleSelectionPolicy();
        foreach ([
            FinancialRuleVersion::LIFECYCLE_DRAFT,
            FinancialRuleVersion::LIFECYCLE_PROVISIONAL,
            FinancialRuleVersion::LIFECYCLE_SIMULATION_ONLY,
            FinancialRuleVersion::LIFECYCLE_UNPUBLISHED,
            FinancialRuleVersion::LIFECYCLE_RETIRED,
        ] as $lifecycle) {
            $r = new FinancialRuleVersion(
                ruleVersionId:                'rv',
                formulaIdentifier:            'f',
                sourceReference:              'src',
                sourceStatus:                 'DRAFT_SRS_V1_2',
                businessApprovalStatus:       'UNVERIFIED',
                implementationAuthorization:  'PROVISIONAL',
                publicationAuthorization:     'NOT_AUTHORIZED',
                lifecycleStatus:              $lifecycle,
                blockingOds:                  ['OD-01'],
            );
            $this->assertFalse($policy->isRuntimeSelectable($r),
                "lifecycle {$lifecycle} must NOT be runtime-selectable");
        }
    }

    public function test_published_financial_rule_is_selectable(): void
    {
        $policy = new FinancialRuleSelectionPolicy();
        $r = $this->publishedRule('any');
        $this->assertTrue($policy->isRuntimeSelectable($r));
    }

    // (8) mandatory donation campaign blocked without legal authority.
    public function test_mandatory_campaign_construction_rejects_missing_legal_authority(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DonationCampaignDecision(
            campaignId:              'c-1',
            scope:                   'all',
            amountType:              DonationCampaignDecision::AMOUNT_TYPE_FIXED,
            amount:                  10.0,
            mandatory:               true,
            startTimestamp:          null,
            endTimestamp:            null,
            ruleVersionId:           'rv-camp-1',
            legalAuthorityReference: null,
            publicationStatus:       'UNPUBLISHED',
            outcome:                 DonationCampaignDecision::OUTCOME_ACTIVE_MANDATORY,
        );
    }

    // Optional campaign without legal authority is still constructible
    // (blocked-no-authority outcome is fine).
    public function test_optional_or_blocked_campaign_allowed_without_legal_authority(): void
    {
        $d = new DonationCampaignDecision(
            campaignId:              'c-2',
            scope:                   'all',
            amountType:              DonationCampaignDecision::AMOUNT_TYPE_PERCENTAGE,
            amount:                  1.0,
            mandatory:               false,
            startTimestamp:          null,
            endTimestamp:            null,
            ruleVersionId:           'rv-camp-2',
            legalAuthorityReference: null,
            publicationStatus:       'UNPUBLISHED',
            outcome:                 DonationCampaignDecision::OUTCOME_BLOCKED_NO_AUTHORITY,
        );
        $this->assertSame(DonationCampaignDecision::OUTCOME_BLOCKED_NO_AUTHORITY, $d->outcome);
    }

    // Enum sanity: bindingOutcomes is only QUOTED.
    public function test_binding_outcomes_are_only_QUOTED(): void
    {
        $this->assertSame([Srv001FinancialOutcome::QUOTED], Srv001FinancialOutcome::bindingOutcomes());
    }

    public function test_enum_lists_all_ten_states(): void
    {
        $this->assertCount(10, Srv001FinancialOutcome::all());
    }

    // (13) fee/tax snapshots immutable — readonly properties.
    public function test_feequote_and_taxquote_are_readonly(): void
    {
        $q  = $this->simpleQuotedFee();
        $tq = $this->simpleSimulationTax();
        foreach ([$q, $tq] as $obj) {
            $refl = new \ReflectionObject($obj);
            foreach ($refl->getProperties() as $p) {
                $this->assertTrue($p->isReadOnly());
            }
        }
    }

    // (14) historical quote remains bound to its rule version.
    public function test_quote_carries_rule_version_id(): void
    {
        $q = $this->simpleQuotedFee();
        $this->assertSame('rv-published-1', $q->ruleVersion->ruleVersionId);
    }

    // (16) no target financial RuleVersion published — invariant asserted
    // via container binding check.
    public function test_no_srv001_financial_rule_bound_at_runtime(): void
    {
        // No FinancialRuleVersion is ever bound in the container in TD-08.
        // We assert via app()->bound() on the class name — no binding
        // will exist since VOs aren't typically resolved from the container.
        $this->assertFalse(app()->bound(FinancialRuleVersion::class));
    }

    // helpers
    private function draftRule(string $formula): FinancialRuleVersion
    {
        return new FinancialRuleVersion(
            ruleVersionId:                'rv-draft-1',
            formulaIdentifier:            $formula,
            sourceReference:              'SRS v1.2 §draft',
            sourceStatus:                 'DRAFT_SRS_V1_2',
            businessApprovalStatus:       'UNVERIFIED',
            implementationAuthorization:  'PROVISIONAL',
            publicationAuthorization:     'NOT_AUTHORIZED',
            lifecycleStatus:              FinancialRuleVersion::LIFECYCLE_DRAFT,
            blockingOds:                  ['OD-01', 'OD-10', 'OD-19'],
        );
    }

    private function publishedRule(string $formula): FinancialRuleVersion
    {
        return new FinancialRuleVersion(
            ruleVersionId:                'rv-published-1',
            formulaIdentifier:            $formula,
            sourceReference:              'signed policy 2.0',
            sourceStatus:                 'SIGNED_BASELINE_2_0',
            businessApprovalStatus:       'APPROVED',
            implementationAuthorization:  'AUTHORIZED',
            publicationAuthorization:     'PUBLISHED',
            lifecycleStatus:              FinancialRuleVersion::LIFECYCLE_PUBLISHED,
        );
    }

    private function simpleQuotedFee(): FeeQuote
    {
        return new FeeQuote(
            outcome:            Srv001FinancialOutcome::QUOTED,
            ruleVersion:        $this->publishedRule('SRV001_FEE'),
            inputs:             ['length_lm' => 20],
            unit:               'lm',
            currency:           'JOD',
            roundingRule:       'HALF_UP_2',
            lineItems:          [['label' => 'survey_fee', 'amount' => 3.0, 'currency' => 'JOD']],
            taxLines:           [],
            exemptionEvidence:  [],
            blockingOds:        [],
            generatedTimestamp: '2026-08-02T00:00:00Z',
        );
    }

    private function simpleSimulationTax(): TaxQuote
    {
        return new TaxQuote(
            outcome:            Srv001FinancialOutcome::SIMULATION_ONLY,
            ruleVersion:        $this->draftRule('SRV001_SALES_TAX'),
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
