<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Controllers;

use App\Http\Concerns\RequiresAdminTier;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\Certificate;
use Modules\JeaServices\Models\ServiceDefinition;

/**
 * JeaAdminDashboardController — H-07 lift of the JEA-specific
 * dashboard + applications-listing endpoints out of the Platform's
 * AdminDashboardController.
 *
 * Owns:
 *   GET  /admin/dashboard      — JEA stats + status breakdown + recent apps
 *   GET  /admin/applications   — org-wide paginated + searchable
 *
 * Platform's AdminDashboardController retains only `/admin/audit-logs`
 * (audit_logs is a Platform primitive). The two routes below are
 * loaded by JeaServicesServiceProvider via routes.php — removing
 * `jea-services` from config/modules.enabled drops both endpoints.
 */
class JeaAdminDashboardController extends Controller
{
    use RequiresAdminTier;

    public function dashboard(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $byStatus = Application::forOrganization($orgId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $recent = Application::forOrganization($orgId)
            ->with(['serviceDefinition:id,code,name_ar,name_en', 'applicant:id,name'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'reference_number', 'status', 'created_at', 'service_definition_id', 'applicant_id']);

        return response()->json([
            'stats' => [
                'total_applications'  => Application::forOrganization($orgId)->count(),
                'pending_review'      => Application::forOrganization($orgId)
                    ->where('status', Application::STATUS_SUBMITTED)->count(),
                'under_review'        => Application::forOrganization($orgId)
                    ->where('status', Application::STATUS_UNDER_REVIEW)->count(),
                'approved_today'      => Application::forOrganization($orgId)
                    ->where('status', Application::STATUS_APPROVED)
                    ->whereDate('updated_at', today())->count(),
                'certificates_issued' => Certificate::where('organization_id', $orgId)->count(),
                'active_services'     => ServiceDefinition::where('organization_id', $orgId)
                    ->where('status', 'active')->count(),
                'total_users'         => User::where('organization_id', $orgId)->count(),
            ],
            'by_status' => $byStatus,
            'recent'    => $recent,
        ]);
    }

    /**
     * JORD-35: server-side pagination + free-text search.
     */
    public function allApplications(Request $request): JsonResponse
    {
        $this->requireAdminTier($request);

        $query = Application::forOrganization($request->user()->organization_id)
            ->with(['serviceDefinition:id,code,name_ar,name_en', 'applicant:id,name,email']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $needle = '%' . strtolower(trim((string) $request->string('q'))) . '%';
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(reference_number) LIKE ?', [$needle])
                  ->orWhereHas('applicant', function ($a) use ($needle) {
                      $a->whereRaw('LOWER(name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$needle]);
                  })
                  ->orWhereHas('serviceDefinition', function ($s) use ($needle) {
                      $s->whereRaw('LOWER(code) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(name_ar) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(name_en) LIKE ?', [$needle]);
                  });
            });
        }

        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(5, min(50, $perPage));

        return response()->json($query->orderByDesc('created_at')->paginate($perPage));
    }
}
