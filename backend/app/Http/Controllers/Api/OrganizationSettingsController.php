<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Engineer;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OrganizationSettingsController — JORD-76
 *
 * Small admin surface for the three org-level ceiling-boost flags
 * (has_excellence_award / is_bit_khibra / has_iso_cert) and the
 * per-engineer specialization-head flag. Both feed QuotaLedger's
 * boost math (JORD-70) — before this controller they had to be
 * toggled via seeder or manual DB edit.
 *
 * Admin-only per the manual: these flags affect effective quotas
 * for every submission the office makes, so they're a JEA-level
 * assertion (award granted / bit-khibra granted / ISO valid), not
 * office-self-service.
 */
class OrganizationSettingsController extends Controller
{
    /** GET /admin/organization → the current admin's org with boost flags + engineer roster. */
    public function show(Request $request): JsonResponse
    {
        $org = Organization::findOrFail($request->user()->organization_id);
        $engineers = Engineer::where('organization_id', $org->id)
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'name_en', 'membership_number', 'specialization', 'is_specialization_head']);

        return response()->json([
            'organization' => [
                'id'                   => $org->id,
                'name_ar'              => $org->name_ar,
                'name_en'              => $org->name_en,
                'has_excellence_award' => (bool) $org->has_excellence_award,
                'is_bit_khibra'        => (bool) $org->is_bit_khibra,
                'has_iso_cert'         => (bool) $org->has_iso_cert,
            ],
            'engineers' => $engineers,
        ]);
    }

    /**
     * PATCH /admin/organization → update any subset of the 3 flags.
     * Only the flags are writable here — org name/slug edits live
     * elsewhere and shouldn't share a form with quota policy.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'has_excellence_award' => ['sometimes', 'boolean'],
            'is_bit_khibra'        => ['sometimes', 'boolean'],
            'has_iso_cert'         => ['sometimes', 'boolean'],
        ]);

        $org = Organization::findOrFail($request->user()->organization_id);
        $org->update($data);

        return response()->json([
            'organization' => [
                'id'                   => $org->id,
                'has_excellence_award' => (bool) $org->has_excellence_award,
                'is_bit_khibra'        => (bool) $org->is_bit_khibra,
                'has_iso_cert'         => (bool) $org->has_iso_cert,
            ],
            'message' => 'تم تحديث إعدادات المكتب.',
        ]);
    }

    /**
     * PATCH /admin/engineers/{id} → toggle is_specialization_head.
     * Cross-org lookup blocked by the organization_id scope; a
     * request that names an engineer from another office 404s.
     */
    public function updateEngineer(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'is_specialization_head' => ['required', 'boolean'],
        ]);

        $engineer = Engineer::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);
        $engineer->update($data);

        return response()->json([
            'engineer' => $engineer->fresh(),
            'message'  => 'تم تحديث إعدادات المهندس.',
        ]);
    }
}
