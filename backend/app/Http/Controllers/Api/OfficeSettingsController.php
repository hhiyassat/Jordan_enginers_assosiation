<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Engineer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OfficeSettingsController — JORD-77
 *
 * Rewrite of OrganizationSettingsController per the office-scoped
 * refactor. An "engineering office" is a User with role='applicant';
 * one Organization can host many. This controller lets admins:
 *   • List every office in their org (picker page).
 *   • Load one office's boost flags + engineer roster.
 *   • PATCH the three boost flags on a specific office.
 *   • PATCH is_specialization_head on an engineer scoped to a office.
 *
 * Cross-org lookup blocked by scoping every query through
 * $request->user()->organization_id (admin must belong to the same
 * org as the office they're editing).
 */
class OfficeSettingsController extends Controller
{
    /** GET /admin/offices → all offices (applicant users) in the admin's org. */
    public function index(Request $request): JsonResponse
    {
        $offices = User::where('organization_id', $request->user()->organization_id)
            ->where('role', 'applicant')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active',
                   'has_excellence_award', 'is_bit_khibra', 'has_iso_cert'])
            ->map(function ($u) {
                $u->engineer_count = Engineer::where('office_user_id', $u->id)->count();
                return $u;
            });

        return response()->json(['offices' => $offices]);
    }

    /** GET /admin/offices/{id} → one office's flags + engineer roster. */
    public function show(Request $request, int $id): JsonResponse
    {
        $office = $this->findOffice($request, $id);
        $engineers = Engineer::where('office_user_id', $office->id)
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'name_en', 'membership_number',
                   'specialization', 'is_specialization_head']);

        return response()->json([
            'office' => [
                'id'                   => $office->id,
                'name'                 => $office->name,
                'email'                => $office->email,
                'has_excellence_award' => (bool) $office->has_excellence_award,
                'is_bit_khibra'        => (bool) $office->is_bit_khibra,
                'has_iso_cert'         => (bool) $office->has_iso_cert,
            ],
            'engineers' => $engineers,
        ]);
    }

    /** PATCH /admin/offices/{id} → update any subset of the 3 flags. */
    public function update(Request $request, int $id): JsonResponse
    {
        $office = $this->findOffice($request, $id);
        $data = $request->validate([
            'has_excellence_award' => ['sometimes', 'boolean'],
            'is_bit_khibra'        => ['sometimes', 'boolean'],
            'has_iso_cert'         => ['sometimes', 'boolean'],
        ]);
        $office->update($data);

        return response()->json([
            'office'  => [
                'id'                   => $office->id,
                'has_excellence_award' => (bool) $office->has_excellence_award,
                'is_bit_khibra'        => (bool) $office->is_bit_khibra,
                'has_iso_cert'         => (bool) $office->has_iso_cert,
            ],
            'message' => 'تم تحديث إعدادات المكتب.',
        ]);
    }

    /**
     * PATCH /admin/offices/{officeId}/engineers/{engineerId}
     * → toggle is_specialization_head on an engineer that belongs to
     * the given office.
     */
    public function updateEngineer(Request $request, int $officeId, int $engineerId): JsonResponse
    {
        $office = $this->findOffice($request, $officeId);
        $data = $request->validate([
            'is_specialization_head' => ['required', 'boolean'],
        ]);

        // Engineer must belong to THIS office. Cross-office attempt 404s.
        $engineer = Engineer::where('office_user_id', $office->id)
            ->findOrFail($engineerId);
        $engineer->update($data);

        return response()->json([
            'engineer' => $engineer->fresh(),
            'message'  => 'تم تحديث إعدادات المهندس.',
        ]);
    }

    private function findOffice(Request $request, int $id): User
    {
        // Office = applicant user in the admin's own organization.
        // Cross-org / non-applicant lookups 404 (attack path closed).
        return User::where('organization_id', $request->user()->organization_id)
            ->where('role', 'applicant')
            ->findOrFail($id);
    }
}
