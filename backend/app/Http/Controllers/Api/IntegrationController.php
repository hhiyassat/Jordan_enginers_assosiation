<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntegrationCycle;
use App\Services\NashmiIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * IntegrationController
 *
 * All routes are protected by ValidateIntegrationKey middleware (X-Integration-Key header).
 * Routes are registered OUTSIDE the v1/Sanctum group — Sanctum does not apply here.
 *
 * Endpoints:
 *   POST /api/integration/receive-requirements     (inbound from Nashmi)
 *   POST /api/integration/receive-feedback         (inbound feedback from Nashmi)
 *   POST /api/integration/cycles/{id}/notify-done  (outbound, admin-triggered)
 *   GET  /api/integration/cycles                   (list)
 *   GET  /api/integration/cycles/{id}              (detail)
 *   GET  /api/integration/cycles/{id}/pdf          (download)
 */
class IntegrationController extends Controller
{
    public function __construct(private NashmiIntegrationService $nashmi) {}

    // ── 1. INBOUND: Receive requirements from Nashmi ──────────────────────────

    public function receiveRequirements(Request $request): \Illuminate\Http\JsonResponse
    {
        // Accept both 'service_name' (ESP field) and 'project_name' (Nashmi field)
        $serviceName = $request->input('service_name') ?? $request->input('project_name');

        $request->validate([
            'project_description' => 'nullable|string|max:5000',
            'source_system'       => 'nullable|string|max:100',
            'pdf_file'            => 'nullable|file|mimes:pdf|max:15360',
            'meta'                => 'nullable|array',
        ]);

        if (empty($serviceName)) {
            return response()->json([
                'message' => 'Field service_name (or project_name) is required.',
                'errors'  => ['service_name' => ['The service_name or project_name field is required.']],
            ], 422);
        }

        // Store PDF if provided
        $pdfPath = null;
        if ($request->hasFile('pdf_file')) {
            $pdfPath = $request->file('pdf_file')->store('integration/requirements', 'local');
        }

        $ref = 'ESP-CYCLE-' . str_pad(IntegrationCycle::count() + 1, 4, '0', STR_PAD_LEFT);

        $cycle = IntegrationCycle::create([
            'cycle_ref'                => $ref,
            'service_name'             => $serviceName,
            'requirements_source'      => $request->input('source_system', 'nashmi-requirement-ai'),
            'requirements_file_path'   => $pdfPath,
            'requirements_meta'        => array_merge(
                $request->input('meta', []),
                [
                    'description' => $request->input('project_description'),
                    'received_ip' => $request->ip(),
                ]
            ),
            'status'                   => 'requirements_received',
            'requirements_received_at' => now(),
        ]);

        Log::channel('integration')->info('Requirements received', [
            'cycle_ref'    => $ref,
            'service_name' => $serviceName,
            'source'       => $cycle->requirements_source,
            'ip'           => $request->ip(),
        ]);

        return response()->json([
            'message'   => 'Requirements received. ESP team will begin analysis.',
            'cycle_ref' => $ref,
            'cycle_id'  => $cycle->id,
            'status'    => $cycle->status,
            'next_step' => 'ESP team will notify nashmi when code is complete via POST /api/integration/cycles/{id}/notify-done',
        ], 201);
    }

    // ── 2. INBOUND: Receive feedback from Nashmi ──────────────────────────────

    public function receiveFeedback(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'cycle_ref'      => 'required|string',
            'overall_status' => 'required|in:approved,rejected,needs_fixes',
            'reviewer_notes' => 'nullable|string',
            'tester_notes'   => 'nullable|string',
            'qa_notes'       => 'nullable|string',
            'action_items'   => 'nullable|array',
            'score'          => 'nullable|integer|min:0|max:100',
        ]);

        $cycle = IntegrationCycle::where('cycle_ref', $request->input('cycle_ref'))->firstOrFail();

        $feedback = [
            'overall_status' => $request->input('overall_status'),
            'score'          => $request->input('score'),
            'reviewer_notes' => $request->input('reviewer_notes'),
            'tester_notes'   => $request->input('tester_notes'),
            'qa_notes'       => $request->input('qa_notes'),
            'action_items'   => $request->input('action_items', []),
            'received_at'    => now()->toISOString(),
        ];

        $newStatus = match ($request->input('overall_status')) {
            'approved'    => 'closed',
            default       => 'feedback_received',
        };

        $cycle->update([
            'status'               => $newStatus,
            'feedback'             => $feedback,
            'feedback_received_at' => now(),
        ]);

        Log::channel('integration')->info('Feedback received from Nashmi', [
            'cycle_ref' => $cycle->cycle_ref,
            'status'    => $request->input('overall_status'),
            'score'     => $request->input('score'),
        ]);

        return response()->json([
            'message'   => 'Feedback recorded. ESP team notified.',
            'cycle_ref' => $cycle->cycle_ref,
            'status'    => $cycle->status,
        ]);
    }

    // ── 3. OUTBOUND: Notify Nashmi that code is done (admin-triggered) ─────────

    public function notifyCodeDone(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $cycle = IntegrationCycle::findOrFail($id);

        if (!$cycle->canTransitionTo('code_done')) {
            return response()->json([
                'message' => "Cannot transition from '{$cycle->status}' to 'code_done'.",
            ], 422);
        }

        $request->validate([
            'git_branch'     => 'nullable|string|max:255',
            'git_commit'     => 'nullable|string|max:255',
            'files_changed'  => 'nullable|array',
            'api_endpoints'  => 'nullable|array',
            'frontend_pages' => 'nullable|array',
            'db_tables'      => 'nullable|array',
            'notes'          => 'nullable|string|max:2000',
        ]);

        $codeSummary = [
            'git_branch'     => $request->input('git_branch', 'main'),
            'git_commit'     => $request->input('git_commit'),
            'files_changed'  => $request->input('files_changed', []),
            'api_endpoints'  => $request->input('api_endpoints', []),
            'frontend_pages' => $request->input('frontend_pages', []),
            'db_tables'      => $request->input('db_tables', []),
            'notes'          => $request->input('notes'),
            'completed_at'   => now()->toISOString(),
        ];

        $result = $this->nashmi->notifyCodeDone($cycle, $codeSummary);

        if (!$result['success']) {
            return response()->json(['message' => 'Failed to notify Nashmi: ' . $result['error']], 502);
        }

        $cycle->update([
            'status'                => 'code_done',
            'code_summary'          => $codeSummary,
            'nashmi_project_id'     => $result['data']['project']['id'] ?? $cycle->nashmi_project_id,
            'code_done_notified_at' => now(),
        ]);

        Log::channel('integration')->info('Code-done notification sent', [
            'cycle_ref' => $cycle->cycle_ref,
        ]);

        return response()->json([
            'message'        => 'Nashmi notified. Reviewer/tester/QA tasks will be distributed.',
            'cycle_ref'      => $cycle->cycle_ref,
            'nashmi_project' => $result['data']['project']           ?? null,
            'ai_status'      => $result['data']['ai_pipeline_status'] ?? null,
        ]);
    }

    // ── 4. List cycles ────────────────────────────────────────────────────────

    public function cycles(): \Illuminate\Http\JsonResponse
    {
        $cycles = IntegrationCycle::latest()->get();
        return response()->json(['data' => $cycles]);
    }

    // ── 5. Single cycle ───────────────────────────────────────────────────────

    public function cycle(int $id): \Illuminate\Http\JsonResponse
    {
        return response()->json(['data' => IntegrationCycle::findOrFail($id)]);
    }

    // ── 6. Download requirements PDF ──────────────────────────────────────────

    public function downloadPdf(int $id): mixed
    {
        $cycle = IntegrationCycle::findOrFail($id);

        if (!$cycle->requirements_file_path) {
            abort(404, 'No PDF attached to this cycle.');
        }

        if (!Storage::disk('local')->exists($cycle->requirements_file_path)) {
            abort(404, 'PDF file not found on disk.');
        }

        $filename = 'nashmi_' . $cycle->cycle_ref . '_requirements.pdf';

        return response()->streamDownload(function () use ($cycle) {
            echo Storage::disk('local')->get($cycle->requirements_file_path);
        }, $filename, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
