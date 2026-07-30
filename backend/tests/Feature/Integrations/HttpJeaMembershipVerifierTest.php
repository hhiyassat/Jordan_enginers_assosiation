<?php

namespace Tests\Feature\Integrations;

use Illuminate\Support\Facades\Http;
use Modules\JeaServices\Engine\HttpJeaMembershipVerifier;
use Tests\TestCase;

/**
 * C-03 · HttpJeaMembershipVerifier — production HTTP driver.
 *
 * Contract with the interface:
 *  - trims + validates inputs the same way the Fake did
 *  - endpoint 200 with is_valid=true  → valid()
 *  - endpoint 200 with is_valid=false → invalid(reason_ar)
 *  - non-2xx or network failure       → RuntimeException
 *
 * The real endpoint URL / auth scheme is BLOCKED_EXTERNAL_INPUT; these
 * tests exercise the driver against a stubbed URL to prove the mapping
 * shape and the failure modes.
 */
class HttpJeaMembershipVerifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'jea.membership_api.base_url'       => 'https://jea-api.example.test/verify',
            'jea.membership_api.auth_scheme'    => 'bearer',
            'jea.membership_api.auth_token'     => 'test-token',
            'jea.membership_api.timeout'        => 5,
            'jea.membership_api.retries'        => 0,
            'jea.membership_api.retry_delay_ms' => 0,
        ]);
    }

    public function test_empty_name_returns_invalid_without_hitting_the_wire(): void
    {
        Http::fake(); // no fake registered — asserts no call is made
        $result = (new HttpJeaMembershipVerifier())->verify('', '12345');
        $this->assertFalse($result->isValid);
        Http::assertNothingSent();
    }

    public function test_empty_number_returns_invalid_without_hitting_the_wire(): void
    {
        Http::fake();
        $result = (new HttpJeaMembershipVerifier())->verify('محمد', '  ');
        $this->assertFalse($result->isValid);
        Http::assertNothingSent();
    }

    public function test_endpoint_success_with_valid_true_returns_valid(): void
    {
        Http::fake([
            'jea-api.example.test/*' => Http::response(['is_valid' => true], 200),
        ]);
        $result = (new HttpJeaMembershipVerifier())->verify('محمد', '12345');
        $this->assertTrue($result->isValid);
    }

    public function test_endpoint_success_with_valid_false_returns_invalid_with_reason(): void
    {
        Http::fake([
            'jea-api.example.test/*' => Http::response([
                'is_valid'  => false,
                'reason_ar' => 'العضوية موقوفة.',
            ], 200),
        ]);
        $result = (new HttpJeaMembershipVerifier())->verify('محمد', '12345');
        $this->assertFalse($result->isValid);
        $this->assertSame('العضوية موقوفة.', $result->reasonAr);
    }

    public function test_endpoint_success_with_valid_false_defaults_reason_when_absent(): void
    {
        Http::fake([
            'jea-api.example.test/*' => Http::response(['is_valid' => false], 200),
        ]);
        $result = (new HttpJeaMembershipVerifier())->verify('محمد', '12345');
        $this->assertFalse($result->isValid);
        $this->assertNotSame('', $result->reasonAr);
    }

    public function test_endpoint_5xx_throws_runtime_exception(): void
    {
        Http::fake([
            'jea-api.example.test/*' => Http::response(['error' => 'upstream'], 502),
        ]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('non-success');
        (new HttpJeaMembershipVerifier())->verify('محمد', '12345');
    }

    public function test_missing_base_url_throws(): void
    {
        config(['jea.membership_api.base_url' => '']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('base_url is not configured');
        (new HttpJeaMembershipVerifier())->verify('محمد', '12345');
    }
}
