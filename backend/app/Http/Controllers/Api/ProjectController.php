<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProjectController
 *
 * User's engineering projects. Scoped to the authenticated user's ownership.
 * Applicants list/create/view their own projects; each project is a container
 * for service applications (e.g. drawing approvals).
 *
 * Business rule: each engineering office has an annual square-metre quota
 * (users.annual_quota_m2). used_m2 = sum(projects.area_m2) for the office
 * from Jan 1 of the current year to today. store() rejects any create that
 * would push used_m2 over quota_m2. NULL quota = unlimited (staff/auditor/admin
 * or any user without a configured cap).
 */
class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::where('owner_user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['projects' => $projects]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_ar'     => ['required', 'string', 'max:255'],
            'name_en'     => ['nullable', 'string', 'max:255'],
            'type'        => ['nullable', 'string', 'max:50'],
            'area_m2'     => ['nullable', 'integer', 'min:1'],
            'city'        => ['nullable', 'string', 'max:100'],
            'contract_no' => ['nullable', 'string', 'max:50'],
        ]);

        // Enforce annual quota when the request has an area and the user has
        // a configured cap. Compute used_m2 for the current year on demand —
        // no cached counter to keep in sync.
        $user      = $request->user();
        $newArea   = (int) ($data['area_m2'] ?? 0);
        $quota     = $user->annual_quota_m2;

        if ($quota !== null && $newArea > 0) {
            $usedM2 = $this->usedThisYear($user->id);
            if ($usedM2 + $newArea > $quota) {
                $remaining = max(0, $quota - $usedM2);
                return response()->json([
                    'message' => "المشروع يتجاوز الرصيد المتاح. الرصيد المتبقي {$remaining} م² من أصل {$quota} م² للسنة الحالية.",
                    'errors'  => ['area_m2' => [
                        "الرصيد المتبقي {$remaining} م² فقط للسنة الحالية.",
                    ]],
                    'quota_exceeded' => true,
                    'quota'          => $quota,
                    'used'           => $usedM2,
                    'remaining'      => $remaining,
                    'attempted'      => $newArea,
                ], 422);
            }
        }

        $project = Project::create([
            ...$data,
            'organization_id' => $user->organization_id,
            'owner_user_id'   => $user->id,
            'status'          => 'pending',
        ]);

        return response()->json(['project' => $project], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $project = Project::where('owner_user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['project' => $project]);
    }

    /**
     * GET /api/v1/projects/quota
     *
     * @return JsonResponse with { year, quota_m2, used_m2, remaining_m2,
     *                             percent_used, projects_count, unlimited }
     */
    public function quota(Request $request): JsonResponse
    {
        $user      = $request->user();
        $used      = $this->usedThisYear($user->id);
        $projects  = Project::where('owner_user_id', $user->id)
                        ->where('created_at', '>=', now()->startOfYear())
                        ->count();
        $quota     = $user->annual_quota_m2;

        if ($quota === null) {
            return response()->json([
                'year'           => (int) now()->year,
                'quota_m2'       => null,
                'used_m2'        => $used,
                'remaining_m2'   => null,
                'percent_used'   => null,
                'projects_count' => $projects,
                'unlimited'      => true,
            ]);
        }

        $remaining = max(0, $quota - $used);
        $percent   = $quota > 0 ? min(100, (int) round(($used / $quota) * 100)) : 0;

        return response()->json([
            'year'           => (int) now()->year,
            'quota_m2'       => $quota,
            'used_m2'        => $used,
            'remaining_m2'   => $remaining,
            'percent_used'   => $percent,
            'projects_count' => $projects,
            'unlimited'      => false,
        ]);
    }

    private function usedThisYear(int $userId): int
    {
        return (int) Project::where('owner_user_id', $userId)
            ->where('created_at', '>=', now()->startOfYear())
            ->sum('area_m2');
    }
}
