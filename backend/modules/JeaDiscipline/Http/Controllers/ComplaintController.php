<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\JeaDiscipline\Http\Requests\DecideComplaintRequest;
use Modules\JeaDiscipline\Http\Requests\StoreComplaintRequest;
use Modules\JeaDiscipline\Models\Complaint;
use Modules\JeaDiscipline\Models\Sanction;
use Modules\JeaDiscipline\Services\SupervisionTransferService;

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
    public function store(StoreComplaintRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Cross-org guard: target must belong to the reporter's org.
        // Prevents someone from filing against a different JEA
        // installation's office by ID guess.
        $target = User::where('id', $data['target_office_user_id'])
            ->where('organization_id', $request->user()->organization_id)
            ->where('role', 'applicant')
            ->firstOrFail();

        $complaint = Complaint::create([
            'organization_id' => $request->user()->organization_id,
            'target_office_user_id' => $target->id,
            'reporter_user_id' => $request->user()->id,
            'reporter_display' => $data['reporter_display'] ?? null,
            'kind' => $data['kind'],
            'description' => $data['description'],
            'status' => Complaint::STATUS_OPEN,
            // C-01: 30-day investigation SLA per manual Ch.7.
            'investigation_deadline' => now()->addDays(30)->toDateString(),
        ]);

        return response()->json([
            'complaint' => $complaint,
            'message' => 'تم استلام الشكوى. سيتم البت خلال 30 يوماً.',
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
    public function decide(DecideComplaintRequest $request, int $id): JsonResponse
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

        $data = $request->validated();

        $sanction = null;
        $prevStatus = $complaint->status;
        $newStatus = $data['decision'] === 'sanction'
            ? Complaint::STATUS_DECIDED
            : Complaint::STATUS_DISMISSED;
        $transfersOpened = 0;

        // A disciplinary decision (sanction + complaint status + any
        // resulting supervision transfers) is one atomic unit — a
        // failure partway through must not leave a sanction issued
        // against an office with no matching complaint status, or a
        // deregistration recorded with no audit trail of who decided it.
        DB::transaction(function () use (
            $request, $data, $complaint, $orgId, $newStatus, $prevStatus,
            &$sanction, &$transfersOpened,
        ) {
            if ($data['decision'] === 'sanction') {
                $effectiveUntil = match ($data['sanction_kind']) {
                    Sanction::KIND_SUSPENSION_1YR => now()->addYear()->toDateString(),
                    Sanction::KIND_SUSPENSION_2YR => now()->addYears(2)->toDateString(),
                    Sanction::KIND_WARNING => now()->toDateString(), // same-day (informational)
                    Sanction::KIND_DEREGISTRATION => null,                  // permanent
                    default => null,
                };

                $sanction = Sanction::create([
                    'organization_id' => $orgId,
                    'office_user_id' => $complaint->target_office_user_id,
                    'complaint_id' => $complaint->id,
                    'kind' => $data['sanction_kind'],
                    'effective_from' => now()->toDateString(),
                    'effective_until' => $effectiveUntil,
                    'reason' => $data['reason'],
                    'issued_by_user_id' => $request->user()->id,
                ]);

                AuditLog::record(
                    user: $request->user(),
                    subject: $sanction,
                    action: 'sanction.issued',
                    extra: [
                        'rule_id' => 'ESP-DISC-002',
                        'input_snapshot' => [
                            'complaint_id' => $complaint->id,
                            'office_user_id' => $complaint->target_office_user_id,
                            'kind' => $data['sanction_kind'],
                            'reason' => $data['reason'],
                        ],
                    ],
                );
            }

            $complaint->update([
                'status' => $newStatus,
                'decided_at' => now(),
                'decided_by_user_id' => $request->user()->id,
                'decision_notes' => $data['notes'] ?? null,
            ]);

            AuditLog::record(
                user: $request->user(),
                subject: $complaint,
                action: 'complaint.decided',
                extra: [
                    'rule_id' => 'ESP-DISC-001',
                    'from_status' => $prevStatus,
                    'to_status' => $newStatus,
                    'input_snapshot' => [
                        'decision' => $data['decision'],
                        'notes' => $data['notes'] ?? null,
                    ],
                ],
            );

            // JORD-83: if the sanction is severe enough (long suspension
            // or deregistration), the manual (p.30) requires every
            // pending supervision contract to be transferred to another
            // office. Auto-flag them all here so the admin queue has
            // work waiting when they view the transfers page.
            if ($sanction) {
                $svc = app(SupervisionTransferService::class);
                if ($svc->sanctionRequiresTransfer($sanction)) {
                    $target = User::find($complaint->target_office_user_id);
                    if ($target) {
                        $transfersOpened = $svc->openTransfersFor($target, $sanction);
                    }
                }
            }
        });

        return response()->json([
            'complaint' => $complaint->fresh(),
            'sanction' => $sanction,
            'transfers_opened' => $transfersOpened,
            'message' => $data['decision'] === 'sanction'
                ? 'تم إصدار العقوبة.'.($transfersOpened > 0
                    ? " تم فتح {$transfersOpened} طلب نقل إشراف."
                    : '')
                : 'تم رفض الشكوى.',
        ]);
    }
}
