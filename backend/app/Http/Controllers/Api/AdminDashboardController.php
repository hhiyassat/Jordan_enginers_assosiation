<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Concerns\RequiresAdminTier;
use App\Http\Controllers\Controller;
use Modules\JeaServices\Models\Application;
use App\Models\AuditLog;
use Modules\JeaServices\Models\Certificate;
use Modules\JeaServices\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AdminDashboardController — Workstream 5C extraction from
 * AdminController.
 *
 * Owns the platform-admin surface:
 *   GET  /admin/dashboard       (headline stats + status breakdown + recent)
 *   GET  /admin/applications    (org-wide paginated + searchable)
 *   GET  /admin/audit-logs      (paginated audit trail)
 *
 * All three methods moved verbatim from AdminController; the private
 * `requireAdmin()` guard became the shared RequiresAdminTier trait.
 *
 * Tag: PC (platform admin dashboard). The domain-specific slice
 * (allApplications shows JEA applications) belongs here today because
 * the endpoint is a generic org-wide "list this org's applications"
 * with no JEA-specific enum/schema knowledge — the JEA-ness lives in
 * the Application model itself, not this listing.
 */
class AdminDashboardController extends Controller
{
    use RequiresAdminTier;

    public function dashboard(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        // JORD-11: real dashboard — beyond the six stat tiles, the admin
        // wants to see WHAT is happening on the platform. Add a status
        // breakdown and the 5 most recent applications so the page can
        // link deep into the review + admin surfaces without the admin
        // having to hunt through filters.
        $byStatus = Application::forOrganization($orgId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $recent = Application::forOrganization($orgId)
            ->with(['serviceDefinition:id,code,name_ar,name_en', 'applicant:id,name'])
            // Tie-break on id desc — bulk-created rows share the same
            // created_at second and would otherwise fall back to
            // database-dependent ordering.
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
     *
     * Query params:
     *   • status    — exact match on Application.status
     *   • q         — free-text; matches reference_number, applicant name/
     *                 email, service code, service name_ar/name_en.
     *   • page      — 1-indexed page number (Laravel default)
     *   • per_page  — 10 / 20 / 50 (clamped)
     *
     * Search runs as a single UNION-free WHERE with OR clauses; every
     * matched column has a b-tree index (see 2026_07_19 migration on
     * applications.reference_number + applicants.email). q is
     * lowercased on both sides so the match is case-insensitive on
     * MySQL and SQLite alike.
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

        // per_page is clamped so a malicious ?per_page=100000 can't ask
        // the backend for the whole table.
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(5, min(50, $perPage));

        return response()->json($query->orderByDesc('created_at')->paginate($perPage));
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $this->requireAdminTier($request);

        $logs = AuditLog::with('user:id,name,email')
            ->where('organization_id', $request->user()->organization_id)
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($logs);
    }
}
