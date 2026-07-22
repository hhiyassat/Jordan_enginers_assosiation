<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * JORD-30: read the Sanctum token from a httpOnly cookie and stuff it
 * into the Authorization header before Sanctum's guard runs.
 *
 * Rationale
 * ---------
 * The previous setup stored the bearer token in browser sessionStorage,
 * which is reachable from any JavaScript on the page — one XSS bug and
 * the attacker exfiltrates the session. Moving the token to an
 * httpOnly cookie removes the JS surface entirely (fetch still sends
 * the cookie because it's same-origin) at the cost of two things:
 *
 *   1. CSRF exposure. Mitigated with SameSite=Strict on the cookie:
 *      the browser refuses to attach it on cross-site navigations or
 *      form submits from evil.com, so a classic CSRF POST can't
 *      forge an authenticated request. The Sanctum guard chain then
 *      still owns actual token validation, so a stolen SameSite=Lax
 *      cookie from a phishing page still fails auth.
 *
 *   2. A middleware indirection. This class. It runs BEFORE
 *      auth:sanctum, promotes the cookie value to a bearer header
 *      IF the caller didn't already supply one, and then delegates
 *      to Sanctum unchanged. Every existing test using
 *      Sanctum::actingAs() or bearer tokens still works.
 *
 * The cookie is written by AuthController::login. The cookie name
 * is centralised in the COOKIE_NAME constant so the writer and
 * reader don't drift.
 */
class ReadTokenFromCookie
{
    public const COOKIE_NAME = 'esp_session';

    public function handle(Request $request, Closure $next): Response
    {
        // Skip if the caller already provided a bearer token — either
        // an integration script or a still-cached tab from the old flow.
        if ($request->bearerToken() !== null) {
            return $next($request);
        }

        $cookieValue = $request->cookie(self::COOKIE_NAME);
        if (is_string($cookieValue) && $cookieValue !== '') {
            $request->headers->set('Authorization', 'Bearer ' . $cookieValue);
        }

        return $next($request);
    }
}
