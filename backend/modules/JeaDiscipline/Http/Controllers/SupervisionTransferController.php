<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\JeaDiscipline\Http\Requests\AssignSupervisionTransferRequest;
use Modules\JeaDiscipline\Http\Requests\DecideSupervisionTransferRequest;
use Modules\JeaDiscipline\Models\SupervisionTransfer;

/**
 * SupervisionTransferController — JORD-83
 *
 * Admin queue for the C-07 supervision-transfer flow. Rows land here
 * automatically when ComplaintController::decide issues a
 * suspension_2yr or deregistration sanction against an office with
 * pending supervision contracts.
 *
 *   GET  /admin/supervision-transfers                       — list all
 *   POST /admin/supervision-transfers/{id}/assign           — set target office
 *   POST /admin/supervision-transfers/{id}/accept-decline   — target accepts / declines
 */
class SupervisionTransferController extends Controller
{
    /** GET /admin/supervision-transfers?status=pending|assigned|accepted|declined */
    public function index(Request $request): JsonResponse
    {
        $q = SupervisionTransfer::where('organization_id', $request->user()->organization_id)
            ->with([
                'application:id,reference_number,status,service_definition_id',
                'application.serviceDefinition:id,code,name_ar,name_en',
                'sourceOffice:id,name,email',
                'targetOffice:id,name,email',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        return response()->json(['transfers' => $q->limit(200)->get()]);
    }

    /** POST /admin/supervision-transfers/{id}/assign  { target_office_user_id, notes? } */
    public function assign(AssignSupervisionTransferRequest $request, int $id): JsonResponse
    {
        $transfer = $this->findTransfer($request, $id);

        if ($transfer->status !== SupervisionTransfer::STATUS_PENDING
            && $transfer->status !== SupervisionTransfer::STATUS_DECLINED) {
            return response()->json([
                'message' => 'لا يمكن تعيين مكتب مستلم — الطلب في حالة نهائية.',
            ], 422);
        }

        $data = $request->validated();

        // Target must be another applicant office IN THE SAME org.
        // Otherwise the transfer crosses tenants (nonsense) or
        // targets a non-office user (also nonsense).
        $target = User::where('organization_id', $request->user()->organization_id)
            ->where('role', 'applicant')
            ->where('is_active', true)
            ->findOrFail($data['target_office_user_id']);

        // Same-office reassignment doesn't make sense — the source
        // office is exactly the one being replaced.
        if ($target->id === $transfer->source_office_user_id) {
            return response()->json([
                'message' => 'المكتب المستلم لا يمكن أن يكون هو المكتب المصدر.',
            ], 422);
        }

        $prevStatus = $transfer->status;

        DB::transaction(function () use ($request, $transfer, $target, $data, $prevStatus) {
            $transfer->update([
                'target_office_user_id' => $target->id,
                'status' => SupervisionTransfer::STATUS_ASSIGNED,
                'notes' => $data['notes'] ?? $transfer->notes,
                'assigned_at' => now(),
            ]);

            AuditLog::record(
                user: $request->user(),
                subject: $transfer,
                action: 'supervision_transfer.assigned',
                extra: [
                    'rule_id' => 'ESP-DISC-005',
                    'from_status' => $prevStatus,
                    'to_status' => SupervisionTransfer::STATUS_ASSIGNED,
                    'input_snapshot' => [
                        'target_office_user_id' => $target->id,
                        'notes' => $data['notes'] ?? null,
                    ],
                ],
            );
        });

        return response()->json([
            'transfer' => $transfer->fresh(),
            'message' => 'تم تعيين المكتب المستلم.',
        ]);
    }

    /** POST /admin/supervision-transfers/{id}/accept-decline  { outcome: 'accept'|'decline', notes? } */
    public function acceptOrDecline(DecideSupervisionTransferRequest $request, int $id): JsonResponse
    {
        $transfer = $this->findTransfer($request, $id);

        if ($transfer->status !== SupervisionTransfer::STATUS_ASSIGNED) {
            return response()->json([
                'message' => 'لا يمكن قبول أو رفض طلب لم يُعيَّن مكتب مستلم له.',
            ], 422);
        }

        $data = $request->validated();

        $prevStatus = $transfer->status;

        $message = DB::transaction(function () use ($request, $transfer, $data, $prevStatus) {
            if ($data['outcome'] === 'accept') {
                $transfer->update([
                    'status' => SupervisionTransfer::STATUS_ACCEPTED,
                    'accepted_at' => now(),
                    'notes' => $data['notes'] ?? $transfer->notes,
                ]);
                $newStatus = SupervisionTransfer::STATUS_ACCEPTED;
                $message = 'تم قبول نقل الإشراف.';
            } else {
                // Decline: clear the target so the row returns to the
                // pending queue for reassignment. Preserves fee_waived +
                // triggering_sanction_id so the audit chain stays intact.
                $transfer->update([
                    'status' => SupervisionTransfer::STATUS_DECLINED,
                    'target_office_user_id' => null,
                    'assigned_at' => null,
                    'notes' => $data['notes'] ?? $transfer->notes,
                ]);
                $newStatus = SupervisionTransfer::STATUS_DECLINED;
                $message = 'تم رفض النقل — الطلب متاح لإعادة التعيين.';
            }

            AuditLog::record(
                user: $request->user(),
                subject: $transfer,
                action: 'supervision_transfer.decision',
                extra: [
                    'rule_id' => 'ESP-DISC-006',
                    'from_status' => $prevStatus,
                    'to_status' => $newStatus,
                    'input_snapshot' => [
                        'outcome' => $data['outcome'],
                        'notes' => $data['notes'] ?? null,
                    ],
                ],
            );

            return $message;
        });

        return response()->json([
            'transfer' => $transfer->fresh(),
            'message' => $message,
        ]);
    }

    private function findTransfer(Request $request, int $id): SupervisionTransfer
    {
        return SupervisionTransfer::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);
    }
}
