<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Engineer;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * EngineerController
 *
 * Manages the engineers registered under the current office (applicant
 * user). Each engineer carries an annual m² quota which the project
 * creation flow enforces (see ProjectController::store).
 */
class EngineerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $engineers = Engineer::where('office_user_id', $request->user()->id)
            ->orderBy('name_ar')
            ->get();

        return response()->json(['engineers' => $engineers]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_ar'           => ['required', 'string', 'max:255'],
            'name_en'           => ['nullable', 'string', 'max:255'],
            'membership_number' => ['required', 'string', 'max:50'],
            'specialization'    => ['nullable', 'string', 'max:50'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'email'             => ['nullable', 'email', 'max:255'],
            'annual_quota_m2'   => ['nullable', 'integer', 'min:0'],
        ]);

        // Membership number is unique per office (composite unique).
        $existing = Engineer::withTrashed()
            ->where('office_user_id', $request->user()->id)
            ->where('membership_number', $data['membership_number'])
            ->first();
        if ($existing) {
            return response()->json([
                'message' => 'رقم العضوية مستخدم بالفعل ضمن هذا المكتب.',
                'errors'  => ['membership_number' => ['هذا الرقم مسجل مسبقاً.']],
            ], 422);
        }

        $engineer = Engineer::create([
            ...$data,
            'organization_id' => $request->user()->organization_id,
            'office_user_id'  => $request->user()->id,
            'is_active'       => true,
        ]);

        return response()->json(['engineer' => $engineer], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $engineer = Engineer::where('office_user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['engineer' => $engineer]);
    }

    /**
     * GET /api/v1/engineers/{id}/quota — per-engineer quota status.
     *
     * @return JsonResponse with { year, engineer_id, quota_m2, used_m2,
     *                             remaining_m2, percent_used, projects_count,
     *                             unlimited }
     */
    public function quota(Request $request, int $id): JsonResponse
    {
        $engineer = Engineer::where('office_user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($this->buildQuota($engineer));
    }

    /**
     * @return array{
     *     engineer_id: int, engineer_name_ar: string, year: int,
     *     quota_m2: int|null, used_m2: int, remaining_m2: int|null,
     *     percent_used: int|null, projects_count: int, unlimited: bool
     * }
     */
    public static function buildQuota(Engineer $engineer): array
    {
        $used = (int) Project::where('engineer_id', $engineer->id)
            ->where('created_at', '>=', now()->startOfYear())
            ->sum('area_m2');

        $projectsCount = (int) Project::where('engineer_id', $engineer->id)
            ->where('created_at', '>=', now()->startOfYear())
            ->count();

        $quota = $engineer->annual_quota_m2;
        if ($quota === null) {
            return [
                'engineer_id'      => $engineer->id,
                'engineer_name_ar' => $engineer->name_ar,
                'year'             => (int) now()->year,
                'quota_m2'         => null,
                'used_m2'          => $used,
                'remaining_m2'     => null,
                'percent_used'     => null,
                'projects_count'   => $projectsCount,
                'unlimited'        => true,
            ];
        }

        $remaining = max(0, $quota - $used);
        $percent   = $quota > 0 ? min(100, (int) round(($used / $quota) * 100)) : 0;

        return [
            'engineer_id'      => $engineer->id,
            'engineer_name_ar' => $engineer->name_ar,
            'year'             => (int) now()->year,
            'quota_m2'         => $quota,
            'used_m2'          => $used,
            'remaining_m2'     => $remaining,
            'percent_used'     => $percent,
            'projects_count'   => $projectsCount,
            'unlimited'        => false,
        ];
    }
}
