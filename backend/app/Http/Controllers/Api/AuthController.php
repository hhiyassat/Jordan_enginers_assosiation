<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

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

        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'تم تسجيل الخروج.']);
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

        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ], 201);
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

    private function userPayload(User $user): array
    {
        return [
            'id'                   => $user->id,
            'name'                 => $user->name,
            'email'                => $user->email,
            'role'                 => $user->role,
            'organization_id'      => $user->organization_id,
            // Frontend uses this to route to the change-credentials screen
            // and to gate the /admin/users nav link.
            'must_change_password' => (bool) $user->must_change_password,
            'can_manage_users'     => $user->canManageUsers(),
        ];
    }
}
