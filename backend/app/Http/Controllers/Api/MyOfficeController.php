<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Sanction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MyOfficeController — JORD-84
 *
 * Applicant-facing (office-user) self-service endpoints.
 * The admin surface for these subsystems already exists:
 *   • RecurringDuesController → admin views/pays other offices' dues
 *   • ComplaintController     → admin lists/decides complaints
 * This controller lets the office see their OWN data:
 *   • GET /my/dues       — obligations owed by this office
 *   • GET /my/complaints — complaints filed AGAINST this office
 *   • GET /my/sanctions  — sanctions ON this office (active + historical)
 *
 * Read-only. Pay + decide stay admin-only per manual policy (the
 * office can see what's owed / pending but can't self-authorize
 * either payment record or sanction reversal).
 */
class MyOfficeController extends Controller
{
    // Workstream 7: dues() moved to Modules\JeaDues\Http\Controllers\
    // MyDuesController. This controller now owns only the complaints
    // + sanctions slices (candidates for the jea-discipline module
    // in Workstream 8).

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
