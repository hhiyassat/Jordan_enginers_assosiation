<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * TokenInactivityCheck — expires Sanctum tokens after a configurable idle period.
 *
 * §8.1 Identity and Access: "session timeout" enforcement.
 *
 * How it works:
 *   - Sanctum tokens have a `last_used_at` column updated on every authenticated request.
 *   - This middleware checks if `last_used_at` is older than SESSION_TIMEOUT_MINUTES.
 *   - If so, it deletes the token and returns 401 with `session_expired` error code
 *     so the frontend can redirect to the login screen.
 *
 * Configuration:
 *   SESSION_TIMEOUT_MINUTES=30  (default)
 *
 * MODEE Annex 4.7 §7.2 — Identity and Access Layer controls.
 */
class TokenInactivityCheck
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $token = $user->currentAccessToken();
            $timeoutMinutes = (int) env('SESSION_TIMEOUT_MINUTES', 30);

            if ($token && $token->last_used_at) {
                $idleMinutes = $token->last_used_at->diffInMinutes(now());

                if ($idleMinutes >= $timeoutMinutes) {
                    $token->delete();

                    Log::channel('security')->info('session_timeout', [
                        'user_id'       => $user->id,
                        'idle_minutes'  => $idleMinutes,
                        'timeout_limit' => $timeoutMinutes,
                        'ts'            => now()->toIso8601String(),
                    ]);

                    return response()->json([
                        'error'   => 'session_expired',
                        'message' => 'انتهت جلستك بسبب عدم النشاط. يرجى تسجيل الدخول مجدداً.',
                    ], 401);
                }
            }
        }

        return $next($request);
    }
}
