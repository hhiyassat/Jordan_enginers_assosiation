<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\JeaServices\Engine\FakeJeaMembershipVerifier;
use PHPUnit\Framework\TestCase;

/**
 * FakeJeaMembershipVerifier — pins the demo-mode semantic that any
 * non-empty (name, membership_number) pair is treated as a valid
 * JEA-registered engineer. Whitespace-only inputs must still fail so
 * an applicant gets a real error instead of a silent accept.
 */
class FakeJeaMembershipVerifierTest extends TestCase
{
    public function test_accepts_any_non_empty_pair(): void
    {
        $result = (new FakeJeaMembershipVerifier())->verify('أحمد الخطيب', '12345');
        $this->assertTrue($result->isValid);
        $this->assertSame('', $result->reasonAr);
    }

    public function test_accepts_english_names_and_numeric_membership(): void
    {
        $result = (new FakeJeaMembershipVerifier())->verify('Jane Doe', 'ENG-99999');
        $this->assertTrue($result->isValid);
    }

    public function test_rejects_empty_name(): void
    {
        $result = (new FakeJeaMembershipVerifier())->verify('', '12345');
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('اسم المهندس', $result->reasonAr);
    }

    public function test_rejects_whitespace_only_name(): void
    {
        $result = (new FakeJeaMembershipVerifier())->verify('   ', '12345');
        $this->assertFalse($result->isValid);
    }

    public function test_rejects_empty_membership_number(): void
    {
        $result = (new FakeJeaMembershipVerifier())->verify('أحمد', '');
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('رقم عضوية', $result->reasonAr);
    }
}
