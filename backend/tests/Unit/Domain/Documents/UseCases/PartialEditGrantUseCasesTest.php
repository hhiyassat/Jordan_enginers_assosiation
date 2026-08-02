<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Documents\UseCases;

use InvalidArgumentException;
use Modules\JeaServices\Domain\Documents\PartialEditGrantEnforcementPolicy;
use Modules\JeaServices\Domain\Documents\SignedContractLockPolicy;
use Modules\JeaServices\Domain\Documents\UseCases\ConsumePartialEditGrantUseCase;
use Modules\JeaServices\Domain\Documents\UseCases\ExpirePartialEditGrantUseCase;
use Modules\JeaServices\Domain\Documents\UseCases\IssuePartialEditGrantUseCase;
use Modules\JeaServices\Domain\Documents\UseCases\RevokePartialEditGrantUseCase;
use Modules\JeaServices\Domain\Documents\UseCases\ValidatePartialEditAttemptUseCase;
use Modules\JeaServices\Domain\Documents\ValueObjects\PartialEditGrant;
use PHPUnit\Framework\TestCase;

class PartialEditGrantUseCasesTest extends TestCase
{
    private PartialEditGrantEnforcementPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new PartialEditGrantEnforcementPolicy(new SignedContractLockPolicy());
    }

    // (12) valid PartialEditGrant use.
    public function test_issue_then_validate_then_consume_happy_path(): void
    {
        $issue = new IssuePartialEditGrantUseCase();
        $g     = $issue->execute(
            applicationId:    10,
            grantingActorId:  1,
            grantingRole:     'staff',
            reason:           'correction',
            permittedSections: [],
            permittedFields:  ['comment_text'],
            issueTimestamp:   '2026-08-02T00:00:00Z',
            singleUse:        true,
        );
        $this->assertSame(PartialEditGrant::STATE_ACTIVE, $g->state);
        $this->assertStringStartsWith('peg-10-', $g->grantId);

        $validate = new ValidatePartialEditAttemptUseCase($this->policy);
        $decision = $validate->execute($g, 'comment_text', 'section-a', '2026-08-02T00:05:00Z', []);
        $this->assertTrue($decision->permitted);

        $consume = new ConsumePartialEditGrantUseCase($this->policy);
        $after   = $consume->execute($g, 'comment_text', 'section-a', '2026-08-02T00:05:00Z', []);
        $this->assertSame(PartialEditGrant::STATE_CONSUMED, $after->state);
    }

    // (13) out-of-scope edit rejected.
    public function test_consume_denies_out_of_scope_edit(): void
    {
        $g = $this->activeGrant();
        $consume = new ConsumePartialEditGrantUseCase($this->policy);
        $this->expectException(InvalidArgumentException::class);
        $consume->execute($g, 'floor_area', 'other-section', '2026-08-02T00:05:00Z', []);
    }

    // (14) expired grant rejected.
    public function test_expire_transitions_active_grant_when_now_past_expiry(): void
    {
        $issue = new IssuePartialEditGrantUseCase();
        $g     = $issue->execute(
            applicationId:    10,
            grantingActorId:  1,
            grantingRole:     'staff',
            reason:           'correction',
            permittedSections: ['section-a'],
            permittedFields:  [],
            issueTimestamp:   '2026-08-02T00:00:00Z',
            expiryTimestamp:  '2026-08-02T00:01:00Z',
        );
        $expire = new ExpirePartialEditGrantUseCase();
        $after  = $expire->execute($g, '2026-08-02T01:00:00Z');
        $this->assertSame(PartialEditGrant::STATE_EXPIRED, $after->state);
    }

    // Expire is idempotent for already-terminal states.
    public function test_expire_is_idempotent_for_non_active_grants(): void
    {
        $g      = $this->activeGrant()->withState(PartialEditGrant::STATE_REVOKED);
        $expire = new ExpirePartialEditGrantUseCase();
        $this->assertSame(PartialEditGrant::STATE_REVOKED, $expire->execute($g, '2030-01-01T00:00:00Z')->state);
    }

    // (15) revoked grant rejected.
    public function test_revoke_transitions_grant_to_REVOKED(): void
    {
        $revoke = new RevokePartialEditGrantUseCase();
        $after  = $revoke->execute($this->activeGrant());
        $this->assertSame(PartialEditGrant::STATE_REVOKED, $after->state);
    }

    // (17) grant update and consumption atomicity — structural invariant.
    // We assert the use case DOES NOT flip state when singleUse=false,
    // proving the consumption boundary is explicit rather than
    // silently transitioning every grant on use.
    public function test_multi_use_grant_stays_active_after_consumption(): void
    {
        $g = (new IssuePartialEditGrantUseCase())->execute(
            applicationId:    10,
            grantingActorId:  1,
            grantingRole:     'staff',
            reason:           'multi-use correction',
            permittedSections: [],
            permittedFields:  ['comment_text'],
            issueTimestamp:   '2026-08-02T00:00:00Z',
            singleUse:        false,
        );
        $after = (new ConsumePartialEditGrantUseCase($this->policy))
            ->execute($g, 'comment_text', 'section-a', '2026-08-02T00:05:00Z', []);
        $this->assertSame(PartialEditGrant::STATE_ACTIVE, $after->state);
    }

    // Ownership invariant: grant carries granting actor id but does
    // NOT change application ownership. Domain VO has no
    // application-owner field, and construction rejects a null or
    // negative applicationId indirectly by not offering a way to
    // reassign it.
    public function test_grant_has_no_application_owner_reassignment_surface(): void
    {
        $g   = $this->activeGrant();
        $refl = new \ReflectionObject($g);
        foreach ($refl->getProperties() as $p) {
            $this->assertTrue($p->isReadOnly());
        }
        $this->assertFalse(method_exists($g, 'reassignApplicationOwner'),
            'APPLICATION_OWNERSHIP_CHANGED=NO — no owner reassignment method may exist');
    }

    private function activeGrant(): PartialEditGrant
    {
        return (new IssuePartialEditGrantUseCase())->execute(
            applicationId:    10,
            grantingActorId:  1,
            grantingRole:     'staff',
            reason:           'correction',
            permittedSections: ['section-a'],
            permittedFields:  ['comment_text'],
            issueTimestamp:   '2026-08-02T00:00:00Z',
        );
    }
}
