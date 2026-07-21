<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Sanction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ComplaintController — JORD-81
 *
 * The disciplinary spine per JEA manual Ch.7:
 *   • POST /complaints                   — intake (applicant/citizen files)
 *   • GET  /admin/complaints             — admin lists all in the org
 *   • POST /admin/complaints/{id}/decide — admin issues sanction or dismisses
 *
 * Decide() produces either:
 *   • A Sanction row (warning / suspension_1yr / suspension_2yr /
 *     deregistration) tied to the target office, effective_from
 *     today, effective_until computed from the ladder.
 *   • Or a status=dismissed on the complaint with no sanction.
 *
 * The 30-day investigation SLA is stored on complaint.investigation_deadline
 * at intake — a follow-up cron (out of this ticket) can nag admins
 * before it lapses.
 */
class ComplaintController extends Controller
{
    /** POST /complaints — anyone authenticated can file. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_office_user_id' => ['required', 'integer', 'exists:users,id'],
            'kind'                  => ['required', 'in:fee_undercutting,contracting_ban,safety_violation,other'],
            'description'           => ['required', 'string', 'min:20', 'max:5000'],
            // Optional display name when reporter is external
            // (municipal officer, contractor without a login yet).
            'reporter_display'      => ['nullable', 'string', 'max:128'],
        ]);

        // Cross-org guard: target must belong to the reporter's org.
        // Prevents someone from filing against a different JEA
        // installation's office by ID guess.
        $target = User::where('id', $data['target_office_user_id'])
            ->where('organization_id', $request->user()->organization_id)
            ->where('role', 'applicant')
            ->firstOrFail();

        $complaint = Complaint::create([
            'organization_id'        => $request->user()->organization_id,
            'target_office_user_id'  => $target->id,
            'reporter_user_id'       => $request->user()->id,
            'reporter_display'       => $data['reporter_display'] ?? null,
            'kind'                   => $data['kind'],
            'description'            => $data['description'],
            'status'                 => Complaint::STATUS_OPEN,
            // C-01: 30-day investigation SLA per manual Ch.7.
            'investigation_deadline' => now()->addDays(30)->toDateString(),
        ]);

        return response()->json([
            'complaint' => $complaint,
            'message'   => 'تم استلام الشكوى. سيتم البت خلال 30 يوماً.',
        ], 201);
    }

    /** GET /admin/complaints — list all in the admin's org. */
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $complaints = Complaint::where('organization_id', $orgId)
            ->with(['targetOffice:id,name', 'reporter:id,name', 'sanctions'])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json(['complaints' => $complaints]);
    }

    /**
     * POST /admin/complaints/{id}/decide
     *   { decision: 'sanction'|'dismiss', sanction_kind?, notes?, reason? }
     *
     * `sanction_kind` required when decision=sanction. Effective_until
     * derived from the ladder (1yr/2yr/permanent).
     */
    public function decide(Request $request, int $id): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $complaint = Complaint::where('organization_id', $orgId)
            ->findOrFail($id);

        if ($complaint->status === Complaint::STATUS_DECIDED
            || $complaint->status === Complaint::STATUS_DISMISSED) {
            return response()->json([
                'message' => 'الشكوى محسومة سابقاً — لا يمكن اتخاذ قرار جديد.',
            ], 422);
        }

        $data = $request->validate([
            'decision'      => ['required', 'in:sanction,dismiss'],
            'sanction_kind' => ['required_if:decision,sanction', 'in:warning,suspension_1yr,suspension_2yr,deregistration'],
            'reason'        => ['required_if:decision,sanction', 'string', 'max:2000'],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ]);

        $sanction = null;

        if ($data['decision'] === 'sanction') {
            $effectiveUntil = match ($data['sanction_kind']) {
                Sanction::KIND_SUSPENSION_1YR => now()->addYear()->toDateString(),
                Sanction::KIND_SUSPENSION_2YR => now()->addYears(2)->toDateString(),
                Sanction::KIND_WARNING        => now()->toDateString(), // same-day (informational)
                Sanction::KIND_DEREGISTRATION => null,                  // permanent
                default                       => null,
            };

            $sanction = Sanction::create([
                'organization_id'   => $orgId,
                'office_user_id'    => $complaint->target_office_user_id,
                'complaint_id'      => $complaint->id,
                'kind'              => $data['sanction_kind'],
                'effective_from'    => now()->toDateString(),
                'effective_until'   => $effectiveUntil,
                'reason'            => $data['reason'],
                'issued_by_user_id' => $request->user()->id,
            ]);
        }

        $complaint->update([
            'status'             => $data['decision'] === 'sanction'
                                       ? Complaint::STATUS_DECIDED
                                       : Complaint::STATUS_DISMISSED,
            'decided_at'         => now(),
            'decided_by_user_id' => $request->user()->id,
            'decision_notes'     => $data['notes'] ?? null,
        ]);

        return response()->json([
            'complaint' => $complaint->fresh(),
            'sanction'  => $sanction,
            'message'   => $data['decision'] === 'sanction'
                ? 'تم إصدار العقوبة.'
                : 'تم رفض الشكوى.',
        ]);
    }
}
