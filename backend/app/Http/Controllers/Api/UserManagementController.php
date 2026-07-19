<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * UserManagementController — tiered user-account CRUD.
 *
 * Two roles can enter this surface:
 *   • superuser → manages every role, including other superusers (subject
 *                 to the "no self-delete" and "no last-active superuser"
 *                 guards, plus the CLI-only credentials rule for post-init
 *                 superusers).
 *   • admin     → manages only applicant/staff/auditor accounts. Cannot
 *                 create, edit, or delete admin or superuser rows, and
 *                 cannot promote anyone to those tiers.
 *
 * The tier boundary is expressed in User::canManageRole(). The controller
 * consults it on every mutation so a request that leaked past the route
 * middleware still can't cross tiers.
 */
class UserManagementController extends Controller
{
    private function guardCanManageUsers(Request $request): void
    {
        if (! $request->user()->canManageUsers()) {
            abort(403, 'إدارة المستخدمين محصورة بالمستخدم الأعلى والمدير.');
        }
    }

    private function refuseTierCrossing(Request $request, string $targetRole): ?JsonResponse
    {
        if (! $request->user()->canManageRole($targetRole)) {
            return response()->json([
                'message' => 'ليس لديك صلاحية لإدارة حسابات بهذا المستوى — العملية محصورة بالمستخدم الأعلى.',
            ], 403);
        }
        return null;
    }

    /**
     * JORD-24: bucket a timestamp into online / idle / offline.
     *   • online  — seen within the last 5 minutes
     *   • idle    — seen within the last 30 minutes
     *   • offline — otherwise (or null = never seen since login)
     */
    private function presenceBucket(?\Carbon\Carbon $seen): string
    {
        if ($seen === null) return 'offline';
        $seconds = $seen->diffInSeconds(now());
        if ($seconds <= 300)  return 'online';
        if ($seconds <= 1800) return 'idle';
        return 'offline';
    }

    public function index(Request $request): JsonResponse
    {
        $this->guardCanManageUsers($request);

        $q = User::where('organization_id', $request->user()->organization_id)
            ->orderBy('name');

        // Admin sees only the tiers they can manage — hiding admin/superuser
        // rows they can't touch keeps the list actionable and avoids the
        // "why is this greyed out?" confusion. Superuser sees everyone.
        if ($request->user()->isAdmin() && ! $request->user()->isSuperuser()) {
            $q->whereIn('role', ['applicant', 'staff', 'auditor']);
        }

        $users = $q->get(['id', 'name', 'email', 'role', 'is_active', 'must_change_password', 'created_at', 'last_seen_at'])
            // JORD-24: annotate each row with a bucketed presence status
            // so the UI can render the coloured dot without any client-
            // side clock arithmetic (browser clocks drift). Serialize
            // to array so the presence field lands in the JSON payload.
            ->map(function (User $u): array {
                $arr = $u->toArray();
                $arr['presence'] = $this->presenceBucket($u->last_seen_at);
                return $arr;
            });
        return response()->json(['users' => $users]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->guardCanManageUsers($request);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers()],
            'role'     => ['required', 'in:applicant,staff,auditor,admin,superuser'],
            'phone'    => ['nullable', 'string', 'max:20'],
        ]);

        if ($refusal = $this->refuseTierCrossing($request, $data['role'])) return $refusal;

        $user = User::create([
            ...$data,
            'organization_id'      => $request->user()->organization_id,
            'password'             => Hash::make($data['password']),
            'must_change_password' => true,
            'password_changed_at'  => null,
            'is_active'            => true,
        ]);

        return response()->json(['user' => $user], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->guardCanManageUsers($request);

        $target = User::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        // Actor cannot touch a target above their tier at all.
        if ($refusal = $this->refuseTierCrossing($request, $target->role)) return $refusal;

        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:255'],
            'email'     => ['sometimes', 'email', 'unique:users,email,' . $target->id],
            'role'      => ['sometimes', 'in:applicant,staff,auditor,admin,superuser'],
            'is_active' => ['sometimes', 'boolean'],
            'password'  => ['sometimes', Password::min(8)->mixedCase()->numbers()],
        ]);

        // Nor can they promote a target into a tier above what they can manage.
        if (isset($data['role']) && $refusal = $this->refuseTierCrossing($request, $data['role'])) {
            return $refusal;
        }

        // Post-init superuser's CREDENTIALS are CLI-only. Non-credential
        // fields (name, role, is_active) remain manageable so another
        // superuser can still deactivate or demote a stale one — subject
        // to the last-active check below.
        $touchesCredentials = isset($data['email']) || isset($data['password']);
        if ($target->isSuperuser() && ! $target->must_change_password && $touchesCredentials) {
            return response()->json([
                'message' => 'بيانات المستخدم الأعلى تُدار حصراً من سطر الأوامر: '
                           . 'php artisan user:credentials ' . $target->email,
            ], 403);
        }

        // Never leave the org without a superuser.
        if ($target->isSuperuser()) {
            $demoting     = isset($data['role'])      && $data['role']      !== 'superuser';
            $deactivating = isset($data['is_active']) && $data['is_active'] === false;
            if (($demoting || $deactivating) && $this->lastActiveSuperuser($request, $target)) {
                return response()->json([
                    'message' => 'لا يمكن إلغاء آخر مستخدم أعلى نشط في المؤسسة.',
                ], 422);
            }
        }

        if (isset($data['password'])) {
            $data['password']             = Hash::make($data['password']);
            $data['must_change_password'] = true;
            $data['password_changed_at']  = null;
        }

        $target->update($data);
        return response()->json(['user' => $target]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->guardCanManageUsers($request);

        $target = User::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        if ($refusal = $this->refuseTierCrossing($request, $target->role)) return $refusal;

        if ($target->id === $request->user()->id) {
            return response()->json([
                'message' => 'لا يمكن حذف حسابك من داخل النظام.',
            ], 422);
        }

        if ($target->isSuperuser() && $this->lastActiveSuperuser($request, $target)) {
            return response()->json([
                'message' => 'لا يمكن حذف آخر مستخدم أعلى نشط في المؤسسة.',
            ], 422);
        }

        $target->delete();
        return response()->json(['message' => 'تم حذف المستخدم.']);
    }

    /**
     * True when `$target` is the only active superuser left in the org.
     */
    private function lastActiveSuperuser(Request $request, User $target): bool
    {
        $count = User::where('organization_id', $request->user()->organization_id)
            ->where('role', 'superuser')
            ->where('is_active', true)
            ->where('id', '!=', $target->id)
            ->count();

        return $count === 0;
    }
}
