<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * CorrelationId — Workstream 11.
 *
 * Attaches a per-request correlation ID to every API response as the
 * `X-Request-Id` header. If the client sent an inbound `X-Request-Id`
 * (Nashmi does this for the outbound integration surface), it's
 * echoed back verbatim so the client can join its own log to ours.
 * Otherwise we mint a UUIDv4 so every request has SOMETHING to key on.
 *
 * The correlation ID is also placed into the request attribute bag as
 * `correlation_id` so any downstream component (LogApiAccess middleware,
 * controller, service) can pull it from the request without parsing
 * the header again.
 *
 * Registered globally in bootstrap/app.php so every request — public,
 * authenticated, integration webhook — gets one.
 */
class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Request-Id');
        if (!is_string($id) || !$this->isValid($id)) {
            $id = (string) Str::uuid();
        }

        $request->attributes->set('correlation_id', $id);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);
        return $response;
    }

    /**
     * Guard against oversized / unsafe client-supplied IDs. UUID
     * shape or an alphanumeric slug up to 64 chars is acceptable.
     */
    private function isValid(string $id): bool
    {
        return $id !== ''
            && strlen($id) <= 64
            && preg_match('/^[A-Za-z0-9._:\-]+$/', $id) === 1;
    }
}
