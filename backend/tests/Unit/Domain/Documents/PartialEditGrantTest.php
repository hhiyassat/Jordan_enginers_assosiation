<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Documents;

use InvalidArgumentException;
use Modules\JeaServices\Domain\Documents\Contracts\MalwareScanResult;
use Modules\JeaServices\Domain\Documents\PartialEditGrantEnforcementPolicy;
use Modules\JeaServices\Domain\Documents\SignedContractLockPolicy;
use Modules\JeaServices\Domain\Documents\ValueObjects\DocumentCategory;
use Modules\JeaServices\Domain\Documents\ValueObjects\DocumentMetadata;
use Modules\JeaServices\Domain\Documents\ValueObjects\PartialEditGrant;
use PHPUnit\Framework\TestCase;

class PartialEditGrantTest extends TestCase
{
    private PartialEditGrantEnforcementPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new PartialEditGrantEnforcementPolicy(new SignedContractLockPolicy());
    }

    // (1) valid PartialEditGrant permits an in-scope field.
    public function test_active_grant_permits_in_scope_field(): void
    {
        $grant  = $this->grant(fields: ['comment_text']);
        $decision = $this->policy->decide($grant, 'comment_text', 'section-a', '2026-08-02T00:00:00Z', []);
        $this->assertTrue($decision->permitted);
    }

    // (2) edit outside grant scope rejected.
    public function test_edit_outside_grant_scope_is_denied(): void
    {
        $grant  = $this->grant(fields: ['comment_text']);
        $decision = $this->policy->decide($grant, 'floor_area', 'section-a', '2026-08-02T00:00:00Z', []);
        $this->assertFalse($decision->permitted);
        $this->assertContains('EDIT_OUTSIDE_GRANT_SCOPE', $decision->reasonCodes);
    }

    // (3) expired grant rejected — expiry timestamp before now.
    public function test_expired_grant_is_denied(): void
    {
        $grant  = $this->grant(
            fields: ['comment_text'],
            expiry: '2020-01-01T00:00:00Z',
        );
        $decision = $this->policy->decide($grant, 'comment_text', 'section-a', '2026-08-02T00:00:00Z', []);
        $this->assertFalse($decision->permitted);
        $this->assertContains('GRANT_EXPIRY_PASSED', $decision->reasonCodes);
    }

    // (4) revoked grant rejected — state=REVOKED.
    public function test_revoked_grant_is_denied(): void
    {
        $grant  = $this->grant(fields: ['comment_text'])->withState(PartialEditGrant::STATE_REVOKED);
        $decision = $this->policy->decide($grant, 'comment_text', 'section-a', '2026-08-02T00:00:00Z', []);
        $this->assertFalse($decision->permitted);
        $this->assertContains('GRANT_REVOKED', $decision->reasonCodes);
    }

    // (5) consumed grant rejected — state=CONSUMED (one-time use exhausted).
    public function test_consumed_grant_is_denied(): void
    {
        $grant  = $this->grant(fields: ['comment_text'])->withState(PartialEditGrant::STATE_CONSUMED);
        $decision = $this->policy->decide($grant, 'comment_text', 'section-a', '2026-08-02T00:00:00Z', []);
        $this->assertFalse($decision->permitted);
        $this->assertContains('GRANT_ALREADY_CONSUMED', $decision->reasonCodes);
    }

    // (6) legally-locked field cannot be edited even with a valid grant.
    public function test_signed_contract_lock_beats_grant_scope(): void
    {
        $lockedDoc = $this->acceptedSignedContract();
        $grant  = $this->grant(fields: ['contract_owner_name']);
        $decision = $this->policy->decide($grant, 'contract_owner_name', 'section-a', '2026-08-02T00:00:00Z', [$lockedDoc]);
        $this->assertFalse($decision->permitted);
        $this->assertContains('SIGNED_CONTRACT_LOCK_ACTIVE', $decision->reasonCodes);
    }

    // (7) grant that names a section (not a field) still permits fields under it.
    public function test_section_scoped_grant_permits_any_field_under_that_section(): void
    {
        $grant  = $this->grant(sections: ['section-b']);
        $decision = $this->policy->decide($grant, 'random_field', 'section-b', '2026-08-02T00:00:00Z', []);
        $this->assertTrue($decision->permitted);
    }

    // (8) grant audit evidence — every field surfaced.
    public function test_grant_carries_full_audit_shape(): void
    {
        $g = $this->grant(fields: ['comment_text']);
        $this->assertNotEmpty($g->grantId);
        $this->assertNotEmpty($g->grantingRole);
        $this->assertNotEmpty($g->reason);
        $this->assertNotEmpty($g->issueTimestamp);
        $this->assertSame(PartialEditGrant::STATE_ACTIVE, $g->state);
    }

    // (9) grant construction rejects empty scope.
    public function test_grant_construction_rejects_empty_scope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PartialEditGrant(
            grantId:           'g-1',
            applicationId:     1,
            grantingActorId:   1,
            grantingRole:      'staff',
            reason:            'correction',
            permittedSections: [],
            permittedFields:   [],
            issueTimestamp:    '2026-08-02T00:00:00Z',
        );
    }

    // (10) grant construction rejects unknown state.
    public function test_grant_construction_rejects_unknown_state(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PartialEditGrant(
            grantId:           'g-1',
            applicationId:     1,
            grantingActorId:   1,
            grantingRole:      'staff',
            reason:            'correction',
            permittedSections: ['x'],
            permittedFields:   [],
            issueTimestamp:    '2026-08-02T00:00:00Z',
            state:             'MAYBE',
        );
    }

    // (11) concurrent-use single-use handling — once withState(CONSUMED), further use denied.
    public function test_single_use_grant_denies_reuse_after_consumption(): void
    {
        $grant   = $this->grant(fields: ['comment_text'], singleUse: true);
        $consumed = $grant->withState(PartialEditGrant::STATE_CONSUMED);
        $decision = $this->policy->decide($consumed, 'comment_text', 'section-a', '2026-08-02T00:00:00Z', []);
        $this->assertFalse($decision->permitted);
    }

    // (12) MalwareScanResult contract — UNKNOWN is not clean.
    public function test_malware_scan_result_UNKNOWN_is_not_treated_as_clean(): void
    {
        $u = MalwareScanResult::unknown(['SCAN_TIMEOUT']);
        $this->assertFalse($u->isClean());
        $c = MalwareScanResult::clean();
        $this->assertTrue($c->isClean());
        $i = MalwareScanResult::infected(['EICAR_TEST_STRING']);
        $this->assertFalse($i->isClean());
    }

    // helpers
    private function grant(
        array $sections = [],
        array $fields = [],
        ?string $expiry = null,
        bool $singleUse = false,
    ): PartialEditGrant {
        return new PartialEditGrant(
            grantId:           'g-1',
            applicationId:     10,
            grantingActorId:   1,
            grantingRole:      'staff',
            reason:            'correction after payment',
            permittedSections: $sections,
            permittedFields:   $fields,
            issueTimestamp:    '2026-08-02T00:00:00Z',
            expiryTimestamp:   $expiry,
            singleUse:         $singleUse,
        );
    }

    private function acceptedSignedContract(): DocumentMetadata
    {
        return new DocumentMetadata(
            documentId:           'sc-1',
            applicationId:        10,
            category:             DocumentCategory::SIGNED_CONTRACT,
            originalFilename:     'contract.pdf',
            declaredMime:         'application/pdf',
            detectedMime:         'application/pdf',
            fileSizeBytes:        1000,
            checksumSha256:       '1111111111111111111111111111111111111111111111111111111111111111',
            storageKey:           'k/sc',
            documentVersion:      1,
            uploadActorId:        1,
            uploadTimestamp:      '2026-08-02T00:00:00Z',
            validationStatus:     DocumentMetadata::VALIDATION_ACCEPTED,
            quarantineStatus:     DocumentMetadata::QUARANTINE_NONE,
            malwareScanStatus:    DocumentMetadata::SCAN_CLEAN,
            sourceClassification: 'test',
            legalLockActive:      true,
        );
    }
}
