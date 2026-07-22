<?php

namespace Integrations\Gsb\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GSB IP Whitelist Middleware
 * MODEE Annex 4.15 §4.5 rule 11
 *
 * Prevents access to GSB-proxied endpoints from any IP not in the
 * configured GSB_ALLOWED_IPS list. Apply to all routes under /api/gsb/*.
 *
 * This middleware is a defence-in-depth layer — GsbClient also checks
 * the IP before making outbound calls, but this blocks at the HTTP layer first.
 */
class GsbIpWhitelist
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = config('gsb.allowed_ips', []);

        // If no IPs configured, log warning but allow through
        // (avoids locking out in development with empty config)
        if (empty($allowedIps)) {
            logger()->warning('GSB_IP_WHITELIST: no IPs configured — allowing all (set GSB_ALLOWED_IPS in .env)');
            return $next($request);
        }

        $clientIp = $request->ip();

        foreach ($allowedIps as $allowed) {
            $allowed = trim($allowed);
            if ($clientIp === $allowed) {
                return $next($request);
            }
            // CIDR check
            if (str_contains($allowed, '/') && $this->ipInCidr($clientIp, $allowed)) {
                return $next($request);
            }
        }

        // §4.8.1: generic error, no internal detail
        // §4.5.10: logging happens in GsbClient — here we just block
        logger()->warning('GSB_IP_WHITELIST: blocked', ['ip' => $clientIp, 'path' => $request->path()]);

        return response()->json([
            'message' => 'Access denied.',
        ], 403);
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$network, $prefix] = explode('/', $cidr);
        if (! is_numeric($prefix)) {
            return false;
        }
        $mask = ~((1 << (32 - (int) $prefix)) - 1);
        return (ip2long($ip) & $mask) === (ip2long($network) & $mask);
    }
}
