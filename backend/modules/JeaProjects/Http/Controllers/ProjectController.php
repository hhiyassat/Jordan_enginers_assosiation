<?php

declare(strict_types=1);

namespace Modules\JeaProjects\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\JeaProjects\Models\Engineer;
use Modules\JeaProjects\Models\Project;
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
 * office. store() requires engineer_id.
 *
 * JORD-12: the m² quota is now pooled at the OFFICE level (the owning
 * user's annual_quota_m2), not divided per-engineer. Any engineer under
 * the office draws from the same shared bucket. Individual
 * Engineer.annual_quota_m2 is still displayed for informational purposes
 * so a big office can see who's authored the most work, but nothing
 * enforces per-engineer caps anymore. NULL quota on the office = unlimited.
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

        // JORD-12: enforce the OFFICE quota (pooled), not the engineer's.
        // Any engineer under the office draws from the same bucket, and
        // usage is summed across every project the office owns this year.
        $office = $request->user();
        $newArea = (int) ($data['area_m2'] ?? 0);
        if ($office->annual_quota_m2 !== null && $newArea > 0) {
            $usedM2 = (int) Project::where('owner_user_id', $office->id)
                ->where('created_at', '>=', now()->startOfYear())
                ->sum('area_m2');
            if ($usedM2 + $newArea > $office->annual_quota_m2) {
                $remaining = max(0, $office->annual_quota_m2 - $usedM2);
                return response()->json([
                    'message' => "المشروع يتجاوز رصيد المكتب. الرصيد المتبقي {$remaining} م² من أصل {$office->annual_quota_m2} م² للسنة الحالية.",
                    'errors'  => ['area_m2' => [
                        "الرصيد المتبقي للمكتب {$remaining} م² فقط للسنة الحالية.",
                    ]],
                    'quota_exceeded' => true,
                    // Kept for backward compat with any consumer that
                    // reads these on the 422 payload; engineer_id + name
                    // now identify the engineer that TRIED to spend the
                    // quota, not the one that owns it.
                    'engineer_id'    => $engineer->id,
                    'engineer_name'  => $engineer->name_ar,
                    'quota'          => $office->annual_quota_m2,
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
     * JORD-12: `totals` now comes from the OFFICE user's annual_quota_m2
     * and the sum of every project the office owns this year — the
     * authoritative pool. Per-engineer breakdown is still returned so
     * the UI can show who's spent how much of the shared budget, but
     * those rows are informational only; enforcement runs against the
     * office totals in store().
     *
     * @return JsonResponse with { year, totals, engineers[] }
     */
    public function quota(Request $request): JsonResponse
    {
        $office = $request->user();

        $engineers = Engineer::where('office_user_id', $office->id)
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->get();

        $breakdown = $engineers
            ->map(fn(Engineer $e) => EngineerController::buildQuota($e))
            ->values();

        // JORD-12: office pool = user.annual_quota_m2 + SUM(all projects).
        // Engineers[] is informational; do NOT sum from it (that gave
        // an artificially high total when engineers had their own
        // legacy per-engineer quotas set).
        $officeQuota = $office->annual_quota_m2;
        $officeUsed  = (int) Project::where('owner_user_id', $office->id)
            ->where('created_at', '>=', now()->startOfYear())
            ->sum('area_m2');
        $officeProjects = (int) Project::where('owner_user_id', $office->id)
            ->where('created_at', '>=', now()->startOfYear())
            ->count();
        $hasUnlim = $officeQuota === null;
        $officeRemaining = $hasUnlim ? null : max(0, $officeQuota - $officeUsed);
        $officePercent   = $hasUnlim
            ? null
            : ($officeQuota > 0 ? min(100, (int) round(($officeUsed / $officeQuota) * 100)) : 0);

        return response()->json([
            'year'      => (int) now()->year,
            'totals'    => [
                'quota_m2'       => $officeQuota,
                'used_m2'        => $officeUsed,
                'remaining_m2'   => $officeRemaining,
                'percent_used'   => $officePercent,
                'projects_count' => $officeProjects,
                'unlimited'      => $hasUnlim,
                'engineers_count' => $breakdown->count(),
            ],
            'engineers' => $breakdown,
        ]);
    }
}
