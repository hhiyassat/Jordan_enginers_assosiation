<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Concerns\RequiresAdminTier;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AdminDashboardController — Platform admin surface.
 *
 * H-07 (session 3): the JEA-specific `dashboard()` and
 * `allApplications()` methods moved to
 * `Modules\JeaServices\Http\Controllers\JeaAdminDashboardController`
 * so this Platform controller no longer imports any JEA model.
 *
 * Owns only:
 *   GET  /admin/audit-logs   (paginated Platform audit trail)
 *
 * The JEA-owned dashboard + admin/applications endpoints are declared
 * in `backend/modules/JeaServices/routes.php` (unchanged URL surface).
 * Removing `jea-services` from `config/modules.enabled` drops both;
 * this controller keeps serving the audit-log endpoint independently.
 */
class AdminDashboardController extends Controller
{
    use RequiresAdminTier;

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
