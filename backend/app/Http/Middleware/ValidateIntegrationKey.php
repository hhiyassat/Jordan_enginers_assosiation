<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * ValidateIntegrationKey
 *
 * SEC-011: All integration endpoints require a shared secret in X-Integration-Key header.
 * Uses hash_equals() to prevent timing attacks.
 * Logs both accepted and rejected attempts to the integration channel.
 */
class ValidateIntegrationKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = $request->header('X-Integration-Key') ?? $request->input('integration_key', '');
        $expected = config('nashmi.integration_key', '');

        if (empty($expected) || !hash_equals($expected, $provided)) {
            Log::channel('integration')->warning('Integration key rejected', [
                'ip'     => $request->ip(),
                'path'   => $request->path(),
                'ua'     => $request->userAgent(),
            ]);

            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        Log::channel('integration')->info('Integration key accepted', [
            'ip'   => $request->ip(),
            'path' => $request->path(),
        ]);

        return $next($request);
    }
}
