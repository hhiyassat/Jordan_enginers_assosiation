<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\JeaDiscipline\Models\Complaint;
use Modules\JeaDiscipline\Models\Sanction;

/**
 * MyDisciplineController — JORD-84 (complaints + sanctions slice).
 *
 * Applicant-facing (office-user) self-service:
 *   • GET /my/complaints — complaints filed AGAINST this office
 *   • GET /my/sanctions  — sanctions ON this office (active + historical)
 *
 * Workstream 8B: extracted from App\Http\Controllers\Api\MyOfficeController
 * (which itself was already stripped of dues() → Modules\JeaDues in
 * Workstream 7). The original MyOfficeController class is now gone —
 * every applicant self-service endpoint lives in its owning module.
 *
 * Read-only. Decide stays admin-only per manual policy (the office
 * can see what's been filed / imposed but can't self-authorize a
 * sanction reversal).
 */
class MyDisciplineController extends Controller
{
    /** GET /my/complaints */
    public function complaints(Request $request): JsonResponse
    {
        $complaints = Complaint::where('target_office_user_id', $request->user()->id)
            ->with(['reporter:id,name', 'sanctions'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function ($c) {
                // Strip reporter_display in the applicant view — external
                // reporter identity is admin-only per manual (p. 278,
                // reporter confidentiality until decision). Keep reporter
                // relation because if they picked a JEA-scoped account,
                // the applicant already knows who they are through the
                // service context.
                $arr = $c->toArray();
                unset($arr['reporter_display']);
                return $arr;
            });

        return response()->json(['complaints' => $complaints]);
    }

    /** GET /my/sanctions */
    public function sanctions(Request $request): JsonResponse
    {
        $sanctions = Sanction::where('office_user_id', $request->user()->id)
            ->orderByDesc('effective_from')
            ->get();

        return response()->json(['sanctions' => $sanctions]);
    }
}
