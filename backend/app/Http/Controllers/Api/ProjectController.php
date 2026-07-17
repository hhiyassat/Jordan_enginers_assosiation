<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Engineer;
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
 * Business rule: every project is attributed to a specific Engineer under the
 * office. store() requires engineer_id and enforces that engineer's annual
 * m² quota. NULL quota on the engineer = unlimited for that engineer.
 */
class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::where('owner_user_id', $request->user()->id)
            ->with('engineer:id,name_ar,name_en,membership_number')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['projects' => $projects]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'engineer_id' => ['required', 'integer', 'exists:engineers,id'],
            'name_ar'     => ['required', 'string', 'max:255'],
            'name_en'     => ['nullable', 'string', 'max:255'],
            'type'        => ['nullable', 'string', 'max:50'],
            'area_m2'     => ['nullable', 'integer', 'min:1'],
            'city'        => ['nullable', 'string', 'max:100'],
            'contract_no' => ['nullable', 'string', 'max:50'],
        ]);

        // Ownership check: engineer must belong to the current office.
        $engineer = Engineer::where('id', $data['engineer_id'])
            ->where('office_user_id', $request->user()->id)
            ->first();
        if (!$engineer) {
            return response()->json([
                'message' => 'المهندس المحدد غير مسجل ضمن هذا المكتب.',
                'errors'  => ['engineer_id' => ['المهندس غير موجود ضمن مكتبك.']],
            ], 422);
        }

        // Enforce the engineer's annual quota when both area and quota exist.
        $newArea = (int) ($data['area_m2'] ?? 0);
        if ($engineer->annual_quota_m2 !== null && $newArea > 0) {
            $usedM2 = (int) Project::where('engineer_id', $engineer->id)
                ->where('created_at', '>=', now()->startOfYear())
                ->sum('area_m2');
            if ($usedM2 + $newArea > $engineer->annual_quota_m2) {
                $remaining = max(0, $engineer->annual_quota_m2 - $usedM2);
                return response()->json([
                    'message' => "المشروع يتجاوز رصيد المهندس {$engineer->name_ar}. الرصيد المتبقي {$remaining} م² من أصل {$engineer->annual_quota_m2} م² للسنة الحالية.",
                    'errors'  => ['area_m2' => [
                        "الرصيد المتبقي للمهندس {$remaining} م² فقط للسنة الحالية.",
                    ]],
                    'quota_exceeded' => true,
                    'engineer_id'    => $engineer->id,
                    'engineer_name'  => $engineer->name_ar,
                    'quota'          => $engineer->annual_quota_m2,
                    'used'           => $usedM2,
                    'remaining'      => $remaining,
                    'attempted'      => $newArea,
                ], 422);
            }
        }

        $project = Project::create([
            ...$data,
            'organization_id' => $request->user()->organization_id,
            'owner_user_id'   => $request->user()->id,
            'status'          => 'pending',
        ]);

        return response()->json(['project' => $project->fresh('engineer')], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $project = Project::where('owner_user_id', $request->user()->id)
            ->with('engineer')
            ->findOrFail($id);

        return response()->json(['project' => $project]);
    }

    /**
     * GET /api/v1/projects/quota
     *
     * Aggregate view for the whole office: per-engineer breakdown plus a
     * summed totals row so the dashboard can show one big number or list.
     *
     * @return JsonResponse with { year, totals, engineers[] }
     */
    public function quota(Request $request): JsonResponse
    {
        $engineers = Engineer::where('office_user_id', $request->user()->id)
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->get();

        $breakdown = $engineers
            ->map(fn(Engineer $e) => EngineerController::buildQuota($e))
            ->values();

        $totalQuota = 0;
        $totalUsed  = 0;
        $hasUnlim   = false;
        $projects   = 0;
        foreach ($breakdown as $row) {
            $totalUsed += $row['used_m2'];
            $projects  += $row['projects_count'];
            if ($row['unlimited']) $hasUnlim = true;
            else $totalQuota += (int) $row['quota_m2'];
        }
        $totalRemaining = $hasUnlim ? null : max(0, $totalQuota - $totalUsed);
        $totalPercent   = $hasUnlim ? null : ($totalQuota > 0 ? min(100, (int) round(($totalUsed / $totalQuota) * 100)) : 0);

        return response()->json([
            'year'      => (int) now()->year,
            'totals'    => [
                'quota_m2'       => $hasUnlim ? null : $totalQuota,
                'used_m2'        => $totalUsed,
                'remaining_m2'   => $totalRemaining,
                'percent_used'   => $totalPercent,
                'projects_count' => $projects,
                'unlimited'      => $hasUnlim,
                'engineers_count' => $breakdown->count(),
            ],
            'engineers' => $breakdown,
        ]);
    }
}
