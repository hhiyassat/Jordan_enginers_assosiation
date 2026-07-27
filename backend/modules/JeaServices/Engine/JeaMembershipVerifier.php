<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

/**
 * JeaMembershipVerifier — external JEA API abstraction.
 *
 * Real prod = HTTP call to JEA's engineer-registry endpoint.
 * Demo / dev = FakeJeaMembershipVerifier (accepts any name+number).
 *
 * See docs/architecture/office-registration-flow.md.
 *
 * Container binding lives in JeaServicesServiceProvider::register().
 * Tests can override via $this->app->bind(JeaMembershipVerifier::class, ...)
 * to inject a spy or a reject-all impl.
 */
interface JeaMembershipVerifier
{
    /**
     * Verify that the given (name, membership_number) pair corresponds
     * to an engineer currently registered with the syndicate.
     *
     * Implementations MUST NOT throw on "not found" — that's a valid
     * VerificationResult (result->isValid = false with a reason). Throwing
     * should be reserved for network / config / auth failures where the
     * caller cannot determine registration status.
     */
    public function verify(string $engineerName, string $membershipNumber): JeaMembershipResult;
}
