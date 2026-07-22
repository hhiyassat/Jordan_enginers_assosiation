<?php

declare(strict_types=1);

namespace Modules\JeaDues\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\JeaDues\Models\RecurringObligation;
use Modules\JeaDues\Services\RecurringDuesService;

/**
 * MyDuesController — JORD-84 (dues slice)
 *
 * Applicant-facing (office-user) self-service. The office sees their
 * OWN obligations + the platform rate table so the frontend can
 * render "your tier pays X" without a second lookup.
 *
 * Workstream 7: extracted from App\Http\Controllers\Api\MyOfficeController::dues().
 * The other two slices (complaints, sanctions) stay on
 * MyOfficeController until the jea-discipline module lands
 * (Workstream 8).
 *
 * Read-only. Pay + decide stay admin-only per manual policy — the
 * office can see what's owed but can't self-authorize a payment
 * record.
 */
class MyDuesController extends Controller
{
    /** GET /my/dues */
    public function index(Request $request): JsonResponse
    {
        $me = $request->user();

        $obligations = RecurringObligation::where('office_user_id', $me->id)
            ->orderByDesc('period_year')
            ->orderByDesc('kind')
            ->get();

        return response()->json([
            'me' => [
                'id'                    => $me->id,
                'name'                  => $me->name,
                'office_classification' => $me->office_classification,
            ],
            'obligations' => $obligations,
            'rate_table'  => RecurringDuesService::RATES,
        ]);
    }
}
