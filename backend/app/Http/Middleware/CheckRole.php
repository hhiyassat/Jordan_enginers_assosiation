<?php

namespace App\Http\Middleware;

use App\Support\SecurityEvents;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(...$roles)) {
            // P0-E-2: emit authorization_denied to the security channel
            // so ops can spot lateral-movement attempts (a compromised
            // low-tier account hammering admin routes).
            SecurityEvents::authorizationDenied($request, $roles);
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
