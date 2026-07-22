<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Engine\WorkflowEngine;
use App\Http\Controllers\Controller;
use App\Http\Requests\DecideApplicationRequest;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ReviewQueueController — Workstream 5B extraction from
 * ApplicationController.
 *
 * Owns the reviewer queue + the two act-on-a-queued-item endpoints:
 *   GET  /review/queue
 *   POST /applications/{id}/claim
 *   POST /applications/{id}/decide
 *
 * All three methods moved verbatim from ApplicationController.
 * Sibling ReviewDashboardController owns the summary widget.
 *
 * Tag: SM (JEA reviewer surface — role-scoped, workflow-engine-driven).
 */
class ReviewQueueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Admin, staff, and auditors can all see the queue
        if (! ($user->isReviewer() || $user->isAdmin())) {
            abort(403, 'المراجعون فقط يمكنهم الوصول لهذه القائمة.');
        }

        $org = $user->organization_id;

        // Queue = unassigned submitted apps  PLUS  apps currently claimed by this user
        $submitted = Application::forOrganization($org)
            ->with(['serviceDefinition:id,code,name_ar,name_en,schema', 'applicant:id,name,email'])
            ->where('status', Application::STATUS_SUBMITTED)
            ->whereNull('assigned_reviewer_id')
            ->orderBy('sla_deadline');

        $myInProgress = Application::forOrganization($org)
            ->with(['serviceDefinition:id,code,name_ar,name_en,schema', 'applicant:id,name,email'])
            ->where('status', Application::STATUS_UNDER_REVIEW)
            ->where('assigned_reviewer_id', $user->id)
            ->orderBy('sla_deadline');

        // Merge both sets, my in-progress first
        $applications = $myInProgress->get()->merge($submitted->get());

        // Filter to what THIS reviewer can actually act on. Admin sees
        // everything (they're the tiebreaker); every other reviewer sees
        // only applications whose current stage's role matches theirs.
        // Without this, staff opened auditor-owned stages and hit
        // "Stage 'X' requires role 'auditor'" with no way to know
        // upfront that the queue lied to them.
        if (! $user->isAdmin()) {
            $applications = $applications->filter(function ($app) use ($user) {
                $service = $app->serviceDefinition;
                if (! $service) return false;
                $stage = $service->getStage($app->current_stage ?? '');
                if (! $stage) return true; // no stage info = don't hide; let claim() decide
                return ($stage['role'] ?? null) === $user->role;
            });
        }

        // Attach a `can_claim` hint per row so the UI can grey out the
        // Claim button on a stale card instead of firing a broken request.
        $applications = $applications->map(function ($app) use ($user) {
            $service = $app->serviceDefinition;
            $stage   = $service?->getStage($app->current_stage ?? '');
            $canClaim = $user->isAdmin()
                || ($stage && ($stage['role'] ?? null) === $user->role);
            $arr = $app->toArray();
            $arr['can_claim'] = $canClaim;
            $arr['current_stage_role'] = $stage['role'] ?? null;
            return $arr;
        });

        return response()->json(['applications' => $applications->values()]);
    }

    public function claim(Request $request, int $id): JsonResponse
    {
        $app    = Application::forOrganization($request->user()->organization_id)->findOrFail($id);
        $engine = new WorkflowEngine($app->serviceDefinition);
        $app    = $engine->claim($app, $request->user());

        return response()->json(['application' => $app]);
    }

    public function decide(DecideApplicationRequest $request, int $id): JsonResponse
    {
        $app    = Application::forOrganization($request->user()->organization_id)->findOrFail($id);
        $engine = new WorkflowEngine($app->serviceDefinition);
        $review = $engine->decide(
            $app,
            $request->user(),
            $request->decision,
            $request->notes,
            $request->annotations ?? [],
        );

        return response()->json(['review' => $review, 'application' => $app->fresh()]);
    }
}
