<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnforcePasswordPolicy — blocks requests when the user must change their password.
 *
 * Policy (env-configurable):
 *   PASSWORD_EXPIRY_DAYS  — days before password expires (default: 90)
 *   PASSWORD_FORCE_CHANGE — if true, users with must_change_password=true are blocked
 *
 * The middleware is a "soft" blocker: it returns 403 with a structured error
 * so the frontend can redirect the user to the change-password screen.
 *
 * MODEE Annex 4.7 §7.3: Password policy enforcement.
 */
class EnforcePasswordPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Allow password-change and logout routes through. Both the
        // un-versioned paths and the /v1/ paths are listed because routes
        // moved to the versioned group and the old paths are still declared
        // in tests + legacy scripts.
        $allowedPaths = [
            'api/auth/password/change',
            'api/auth/logout',
            'api/auth/me',
            'api/v1/auth/password/change',
            'api/v1/auth/logout',
            'api/v1/auth/me',
        ];

        foreach ($allowedPaths as $path) {
            if ($request->is($path)) {
                return $next($request);
            }
        }

        // 1. Admin-forced change (e.g., after account creation or security incident)
        if ($user->must_change_password) {
            return response()->json([
                'error'   => 'password_change_required',
                'message' => 'يجب تغيير كلمة المرور قبل المتابعة.',
            ], 403);
        }

        // 2. Password expiry check
        $expiryDays = (int) env('PASSWORD_EXPIRY_DAYS', 90);

        if ($expiryDays > 0 && $user->password_changed_at) {
            $expiredAt = $user->password_changed_at->addDays($expiryDays);

            if (now()->isAfter($expiredAt)) {
                return response()->json([
                    'error'      => 'password_expired',
                    'message'    => 'انتهت صلاحية كلمة المرور. يرجى تغييرها للمتابعة.',
                    'expired_at' => $expiredAt->toIso8601String(),
                ], 403);
            }
        }

        return $next($request);
    }
}
