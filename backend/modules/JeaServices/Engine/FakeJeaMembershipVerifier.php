<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

/**
 * FakeJeaMembershipVerifier — demo/test implementation.
 *
 * Per stakeholder input (2026-07-27):
 *   "لغايت الديمو يقبل اي اسم اذا لم يتوفر api"
 *   (for demo purposes, accept any name if the API is not available)
 *
 * Any non-empty name + non-empty membership number is treated as valid.
 * Whitespace-only inputs are treated as invalid so the applicant gets a
 * useful error instead of a silent accept on malformed input.
 *
 * Bound as the default JeaMembershipVerifier in
 * JeaServicesServiceProvider::register() until the real HTTP client is
 * implemented.
 */
final class FakeJeaMembershipVerifier implements JeaMembershipVerifier
{
    public function verify(string $engineerName, string $membershipNumber): JeaMembershipResult
    {
        if (trim($engineerName) === '') {
            return JeaMembershipResult::invalid('اسم المهندس مطلوب.');
        }
        if (trim($membershipNumber) === '') {
            return JeaMembershipResult::invalid('رقم عضوية النقابة مطلوب.');
        }
        return JeaMembershipResult::valid();
    }
}
