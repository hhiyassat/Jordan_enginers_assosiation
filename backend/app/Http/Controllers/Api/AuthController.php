<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ReadTokenFromCookie;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * AuthController — authentication endpoints
 *
 * SEC-009: Rate limiting applied at route level (throttle:5,1 for login).
 * SEC-002: All requests logged via LogApiAccess middleware.
 * SEC-003: Tokens expire after SESSION_TIMEOUT_MINUTES (TokenInactivityCheck).
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])
            ->where('is_active', true)
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'بيانات الاعتماد غير صحيحة.'], 401);
        }

        // Revoke all existing tokens (single-session policy)
        $user->tokens()->delete();

        $token = $user->createToken('esp-token')->plainTextToken;

        // JORD-30: token still returned in JSON for backward compat
        // with any lingering bearer-header consumer, but the CANONICAL
        // storage is now a httpOnly + SameSite=Strict cookie that the
        // browser sends automatically. AuthProvider.tsx no longer reads
        // this JSON token.
        return response()
            ->json([
                'token' => $token,
                'user'  => $this->userPayload($user),
            ])
            ->withCookie($this->buildSessionCookie($token));
    }

    /**
     * JORD-84 (PM): identity probe. Returns 200 in every case:
     *   • {user: <payload>} when a valid session cookie/token resolves.
     *   • {user: null}      when there's no session (guest first load).
     *
     * Sitting outside the auth:sanctum group means an unauthenticated
     * probe no longer paints a red 401 in the browser console — the
     * frontend's AuthProvider always got a 200 back and just checked
     * whether `user` was populated. We resolve the user manually via
     * Sanctum's guard because the middleware isn't in the stack for
     * this route.
     */
    public function me(Request $request): JsonResponse
    {
        // Try the token/cookie promoted by ReadTokenFromCookie (global).
        // sanctum guard reads the Authorization header and returns null
        // for guests instead of throwing / redirecting.
        $user = auth('sanctum')->user();
        return response()->json([
            'user' => $user instanceof User ? $this->userPayload($user) : null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()
            ->json(['message' => 'تم تسجيل الخروج.'])
            // JORD-30: clear the httpOnly cookie so a stolen browser
            // tab can't replay against the (now-revoked) token row.
            ->withCookie(Cookie::forget(ReadTokenFromCookie::COOKIE_NAME, '/'));
    }

    /**
     * JORD-30 / JORD-52 (PM): build the httpOnly session cookie.
     *
     *   • name:      esp_session (COOKIE_NAME on the reader middleware)
     *   • value:     the Sanctum plain-text token
     *   • lifetime:  configurable via ESP_SESSION_LIFETIME_MINUTES
     *                (default 480 = 8h). "reload kicks to login" was
     *                often traced to a very short default here — ops
     *                now tunes this per-tenant without a code change.
     *   • path:      '/' so both /api and the SPA see it
     *   • domain:    null → current host (subdomain-safe)
     *   • secure:    ESP_SESSION_COOKIE_SECURE:
     *                  'auto' (default) → true only under APP_ENV=production
     *                  'true'  → force Secure on (production over HTTPS)
     *                  'false' → force Secure off (production over HTTP —
     *                            e.g. behind a TLS-terminating LB where
     *                            the browser still sees plain http://)
     *   • httpOnly:  true — invisible to JavaScript, defeats XSS
     *   • sameSite:  'strict' — browser refuses to attach on cross-
     *                site navigations, so classic CSRF POSTs from
     *                evil.com don't forge authenticated requests
     */
    private function buildSessionCookie(string $token): SymfonyCookie
    {
        return Cookie::make(
            name:     ReadTokenFromCookie::COOKIE_NAME,
            value:    $token,
            minutes:  self::sessionLifetimeMinutes(),
            path:     '/',
            domain:   null,
            secure:   self::cookieSecureFlag(),
            httpOnly: true,
            raw:      false,
            sameSite: 'strict',
        );
    }

    /**
     * JORD-52 (PM): lifetime resolution — env var wins, otherwise
     * 8 hours. Clamped to a sane range so a config typo can't
     * emit a 100-year cookie or a 0-minute one.
     */
    private static function sessionLifetimeMinutes(): int
    {
        $raw = (int) env('ESP_SESSION_LIFETIME_MINUTES', 480);
        return max(30, min($raw, 60 * 24 * 30)); // 30 min .. 30 days
    }

    /**
     * JORD-52 (PM): tri-state secure flag. 'auto' means "production
     * yes, dev no" — the safe default. Ops can override when the
     * app runs behind a TLS-terminating LB where the browser sees
     * http:// (Secure cookies wouldn't come back).
     */
    private static function cookieSecureFlag(): bool
    {
        $mode = strtolower((string) env('ESP_SESSION_COOKIE_SECURE', 'auto'));
        return match ($mode) {
            'true', '1', 'yes'  => true,
            'false', '0', 'no'  => false,
            default             => app()->environment('production'),
        };
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'email', 'unique:users,email'],
            'password'         => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'organization_id'  => ['required', 'exists:organizations,id'],
            'phone'            => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'organization_id'    => $data['organization_id'],
            'name'               => $data['name'],
            'email'              => $data['email'],
            'password'           => Hash::make($data['password']),
            'role'               => 'applicant',
            'password_changed_at' => now(),
        ]);

        $token = $user->createToken('esp-token')->plainTextToken;

        // JORD-30: same cookie treatment as login().
        return response()
            ->json([
                'token' => $token,
                'user'  => $this->userPayload($user),
            ], 201)
            ->withCookie($this->buildSessionCookie($token));
    }

    /**
     * Handles both the first-login credential rotation (superuser only: may
     * also change their own email) and ordinary password change. After a
     * superuser has cleared the must_change_password gate once, this endpoint
     * refuses to touch their credentials again — from that point on the only
     * legitimate rotation path is `php artisan user:credentials`. This makes
     * a stolen superuser token useless for lateral escalation.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        // Superusers who already changed initial creds can't rotate via API.
        if ($user->isSuperuser() && ! $user->must_change_password) {
            return response()->json([
                'message' => 'يمكن تغيير بيانات المستخدم الأعلى فقط من خلال سطر الأوامر: '
                           . 'php artisan user:credentials ' . $user->email,
            ], 403);
        }

        $rules = [
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ];
        // On first login the superuser may pick their own login email.
        if ($user->isSuperuser() && $user->must_change_password) {
            $rules['email'] = ['sometimes', 'email', 'unique:users,email,' . $user->id];
        }
        $data = $request->validate($rules);

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'كلمة المرور الحالية غير صحيحة.'], 422);
        }

        $update = [
            'password'             => Hash::make($data['password']),
            'must_change_password' => false,
            'password_changed_at'  => now(),
        ];
        if (isset($data['email'])) {
            $update['email'] = $data['email'];
        }
        $user->update($update);

        return response()->json(['message' => 'تم تغيير كلمة المرور.']);
    }

    /**
     * JORD-10: PATCH /auth/me — the caller updates their OWN profile.
     *
     * Scope is deliberately narrow: name + phone only. Email is
     * gated by the credential-change flow (see changePassword() above)
     * because rotating an email is a security-sensitive act that also
     * changes the login identity. Role, organization_id, is_active, and
     * must_change_password stay off-limits — those are admin-only fields
     * mutated via /admin/users.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);
        $user->update($data);

        return response()->json(['user' => $this->userPayload($user->fresh())]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id'                   => $user->id,
            'name'                 => $user->name,
            'email'                => $user->email,
            'phone'                => $user->phone,
            'role'                 => $user->role,
            'organization_id'      => $user->organization_id,
            // Frontend uses this to route to the change-credentials screen
            // and to gate the /admin/users nav link.
            'must_change_password' => (bool) $user->must_change_password,
            'can_manage_users'     => $user->canManageUsers(),
        ];
    }
}
