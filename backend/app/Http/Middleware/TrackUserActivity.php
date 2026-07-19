<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * JORD-24: bump users.last_seen_at on every authenticated request.
 *
 * Coalesces writes to at most once per minute per user — otherwise
 * every navigation or unread-count poll would issue a DB UPDATE and
 * flood the write side. Threshold is short enough that the presence
 * status stays accurate but long enough that the write rate is
 * bounded.
 *
 * Runs INSIDE the `auth:sanctum` group so an unauthenticated request
 * never hits this. Falls through silently if there's no user or the
 * update fails — the presence signal is nice-to-have, never critical.
 */
class TrackUserActivity
{
    private const BUMP_THRESHOLD_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null) {
            $seen = $user->last_seen_at;
            if ($seen === null || $seen->diffInSeconds(now()) >= self::BUMP_THRESHOLD_SECONDS) {
                // Direct UPDATE (not $user->update) skips model events +
                // updated_at churn so the write cost is exactly one row.
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['last_seen_at' => now()]);
            }
        }

        return $next($request);
    }
}
