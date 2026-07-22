<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\ApplicationReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ReviewDashboardController — Workstream 5B extraction from
 * ApplicationController.
 *
 * Owns the reviewer summary widget:
 *   GET  /review/dashboard
 *
 * JORD-88 (PM): the reviewer landing page's headline tiles + decisions
 * breakdown + recent decisions + top-5 in-progress. Non-admin scoping
 * mirrors ReviewQueueController (role must match the current stage).
 *
 * Method moved verbatim from ApplicationController — behaviour is
 * identical.
 *
 * Tag: SM (JEA reviewer dashboard).
 */
class ReviewDashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isReviewer() || $user->isAdmin())) {
            abort(403, 'المراجعون فقط يمكنهم الوصول للوحة التحكم.');
        }

        $orgId = $user->organization_id;

        // 1. My in-progress + queue-available raw sets. Filter by
        //    role-can-act-on for non-admins (same rule as reviewQueue).
        $myInProgress = Application::forOrganization($orgId)
            ->with(['serviceDefinition:id,code,name_ar,name_en,schema'])
            ->where('status', Application::STATUS_UNDER_REVIEW)
            ->where('assigned_reviewer_id', $user->id)
            ->orderBy('sla_deadline')
            ->get();

        $submitted = Application::forOrganization($orgId)
            ->with(['serviceDefinition:id,code,name_ar,name_en,schema'])
            ->where('status', Application::STATUS_SUBMITTED)
            ->whereNull('assigned_reviewer_id')
            ->get();

        if (! $user->isAdmin()) {
            $submitted = $submitted->filter(function ($app) use ($user) {
                $stage = $app->serviceDefinition?->getStage($app->current_stage ?? '');
                return $stage && ($stage['role'] ?? null) === $user->role;
            });
            $myInProgress = $myInProgress->filter(function ($app) use ($user) {
                // The reviewer might have claimed the app before their role
                // was changed (rare edge case) — keep the filter symmetric
                // with the submitted-side gate so counts stay honest.
                $stage = $app->serviceDefinition?->getStage($app->current_stage ?? '');
                return ! $stage || ($stage['role'] ?? null) === $user->role;
            });
        }

        $overdue = $myInProgress->filter(fn ($app) =>
            $app->sla_deadline && now()->greaterThan($app->sla_deadline)
        )->count();

        // 2. My decisions counted from application_reviews. `decided_this_week`
        //    is Monday→now to match how ops read a working week; the "month"
        //    version bounds the by_decision breakdown so a slow week doesn't
        //    surface an empty pie.
        $weekStart  = now()->startOfWeek();
        $monthStart = now()->startOfMonth();

        $decidedThisWeek  = ApplicationReview::where('reviewer_id', $user->id)
            ->where('created_at', '>=', $weekStart)->count();
        $decidedThisMonth = ApplicationReview::where('reviewer_id', $user->id)
            ->where('created_at', '>=', $monthStart)->count();

        $byDecisionRows = ApplicationReview::where('reviewer_id', $user->id)
            ->where('created_at', '>=', $monthStart)
            ->selectRaw('decision, COUNT(*) as c')
            ->groupBy('decision')
            ->pluck('c', 'decision');
        // Normalise: always emit the three decision keys, zero-filled.
        $byDecision = [
            'approved'                => (int) ($byDecisionRows['approved'] ?? 0),
            'rejected'                => (int) ($byDecisionRows['rejected'] ?? 0),
            'modifications_requested' => (int) ($byDecisionRows['modifications_requested'] ?? 0),
        ];

        // 3. Recent decisions (last 5) so the dashboard shows what the
        //    reviewer just closed. Includes the reference so a card
        //    click can deep-link to the review panel.
        $recent = ApplicationReview::where('reviewer_id', $user->id)
            ->with(['application.serviceDefinition:id,code,name_ar,name_en'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id'             => $r->id,
                'application_id' => $r->application_id,
                'reference'      => $r->application?->reference_number,
                'service_name_ar' => $r->application?->serviceDefinition?->name_ar,
                'service_name_en' => $r->application?->serviceDefinition?->name_en,
                'decision'       => $r->decision,
                'created_at'     => $r->created_at?->toIso8601String(),
            ]);

        // 4. Top 5 of my in-progress apps so the reviewer can jump
        //    straight from the dashboard to the highest-SLA-risk case.
        $myInProgressTop = $myInProgress->take(5)->map(fn ($app) => [
            'id'                 => $app->id,
            'reference'          => $app->reference_number,
            'service_name_ar'    => $app->serviceDefinition?->name_ar,
            'service_name_en'    => $app->serviceDefinition?->name_en,
            'sla_deadline'       => $app->sla_deadline?->toIso8601String(),
            'sla_breached'       => (bool) ($app->sla_breached ?? false),
        ])->values();

        return response()->json([
            'stats' => [
                'my_in_progress'    => $myInProgress->count(),
                'queue_available'   => $submitted->count(),
                'overdue'           => $overdue,
                'decided_this_week' => $decidedThisWeek,
                'decided_this_month'=> $decidedThisMonth,
            ],
            'by_decision_this_month' => $byDecision,
            'recent_decisions'       => $recent,
            'my_in_progress'         => $myInProgressTop,
        ]);
    }
}
