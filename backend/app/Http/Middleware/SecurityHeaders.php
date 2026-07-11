<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders — adds HTTP security headers to every response.
 *
 * §8.1 Transport and Storage Security:
 *   "Use HTTPS/TLS, strong encryption, secure headers, encryption at rest."
 *
 * §8.1 Identity and Access:
 *   "Session timeout" — enforced via frontend idle detection assisted by
 *   the X-Session-Timeout header; the backend invalidates tokens after
 *   SESSION_TIMEOUT_MINUTES of inactivity (enforced in TokenInactivityCheck).
 *
 * Headers applied:
 *   - Strict-Transport-Security  (HSTS — forces HTTPS for 1 year)
 *   - Content-Security-Policy    (CSP — restricts script/style origins)
 *   - X-Frame-Options            (clickjacking protection)
 *   - X-Content-Type-Options     (MIME sniffing prevention)
 *   - Referrer-Policy            (limits referrer leakage)
 *   - Permissions-Policy         (disable unused browser features)
 *   - X-Session-Timeout          (informs SPA of idle timeout in seconds)
 *   - Cache-Control              (prevents sensitive API response caching)
 *
 * MODEE Annex 4.7 §7 + §8 — Identity, access, and transport controls.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // ── HSTS ────────────────────────────────────────────────────────
        // Only meaningful over HTTPS; harmless over HTTP (ignored by browser).
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains; preload'
        );

        // ── CSP ─────────────────────────────────────────────────────────
        // Restrictive policy for the API (no scripts/styles expected in API responses).
        // The frontend SPA must set its own CSP via the web server (nginx/apache).
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'none'; frame-ancestors 'none'"
        );

        // ── Framing ──────────────────────────────────────────────────────
        $response->headers->set('X-Frame-Options', 'DENY');

        // ── MIME sniffing ─────────────────────────────────────────────────
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // ── Referrer leakage ─────────────────────────────────────────────
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // ── Browser feature policy ───────────────────────────────────────
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()'
        );

        // ── Session timeout hint for SPA ─────────────────────────────────
        $timeoutSeconds = (int) env('SESSION_TIMEOUT_MINUTES', 30) * 60;
        $response->headers->set('X-Session-Timeout', (string) $timeoutSeconds);

        // ── Cache control — no caching of API responses ───────────────────
        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        // Remove fingerprinting headers added by the web server / framework
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
