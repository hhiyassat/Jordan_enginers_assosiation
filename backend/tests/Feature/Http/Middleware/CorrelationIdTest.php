<?php

namespace Tests\Feature\Http\Middleware;

use Tests\TestCase;

/**
 * Workstream 11: correlation-id round-trip.
 *
 * Verifies that:
 *   • Every response carries an X-Request-Id header.
 *   • A client-supplied X-Request-Id is echoed back verbatim.
 *   • A malformed / oversized client-supplied ID is rejected — the
 *     middleware mints a fresh UUID instead of trusting the header.
 */
class CorrelationIdTest extends TestCase
{
    public function test_response_carries_correlation_id_when_none_supplied(): void
    {
        $r = $this->getJson('/up');

        $id = $r->headers->get('X-Request-Id');
        $this->assertNotNull($id);
        // Minted UUIDv4 shape: 8-4-4-4-12 hex.
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $id,
        );
    }

    public function test_client_supplied_id_is_echoed_back(): void
    {
        $r = $this->withHeaders(['X-Request-Id' => 'client-req-abc-123'])
            ->getJson('/up');

        $this->assertSame('client-req-abc-123', $r->headers->get('X-Request-Id'));
    }

    public function test_malformed_client_id_is_rejected_and_replaced(): void
    {
        $bad = str_repeat('x', 200); // exceeds 64-char cap
        $r = $this->withHeaders(['X-Request-Id' => $bad])
            ->getJson('/up');

        $echoed = $r->headers->get('X-Request-Id');
        $this->assertNotSame($bad, $echoed);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $echoed,
        );
    }

    public function test_unsafe_characters_are_rejected(): void
    {
        $r = $this->withHeaders(['X-Request-Id' => 'evil<script>alert(1)</script>'])
            ->getJson('/up');

        $echoed = $r->headers->get('X-Request-Id');
        $this->assertStringNotContainsString('<', $echoed);
        $this->assertStringNotContainsString('>', $echoed);
    }
}
