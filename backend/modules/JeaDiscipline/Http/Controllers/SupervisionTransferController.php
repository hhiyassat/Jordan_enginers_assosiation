<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\JeaDiscipline\Models\SupervisionTransfer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function assign(Request $request, int $id): JsonResponse
    {
        $transfer = $this->findTransfer($request, $id);

        if ($transfer->status !== SupervisionTransfer::STATUS_PENDING
            && $transfer->status !== SupervisionTransfer::STATUS_DECLINED) {
            return response()->json([
                'message' => 'لا يمكن تعيين مكتب مستلم — الطلب في حالة نهائية.',
            ], 422);
        }

        $data = $request->validate([
            'target_office_user_id' => ['required', 'integer', 'exists:users,id'],
            'notes'                 => ['nullable', 'string', 'max:2000'],
        ]);

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

        $transfer->update([
            'target_office_user_id' => $target->id,
            'status'                => SupervisionTransfer::STATUS_ASSIGNED,
            'notes'                 => $data['notes'] ?? $transfer->notes,
            'assigned_at'           => now(),
        ]);

        return response()->json([
            'transfer' => $transfer->fresh(),
            'message'  => 'تم تعيين المكتب المستلم.',
        ]);
    }

    /** POST /admin/supervision-transfers/{id}/accept-decline  { outcome: 'accept'|'decline', notes? } */
    public function acceptOrDecline(Request $request, int $id): JsonResponse
    {
        $transfer = $this->findTransfer($request, $id);

        if ($transfer->status !== SupervisionTransfer::STATUS_ASSIGNED) {
            return response()->json([
                'message' => 'لا يمكن قبول أو رفض طلب لم يُعيَّن مكتب مستلم له.',
            ], 422);
        }

        $data = $request->validate([
            'outcome' => ['required', 'in:accept,decline'],
            'notes'   => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['outcome'] === 'accept') {
            $transfer->update([
                'status'      => SupervisionTransfer::STATUS_ACCEPTED,
                'accepted_at' => now(),
                'notes'       => $data['notes'] ?? $transfer->notes,
            ]);
            $message = 'تم قبول نقل الإشراف.';
        } else {
            // Decline: clear the target so the row returns to the
            // pending queue for reassignment. Preserves fee_waived +
            // triggering_sanction_id so the audit chain stays intact.
            $transfer->update([
                'status'                => SupervisionTransfer::STATUS_DECLINED,
                'target_office_user_id' => null,
                'assigned_at'           => null,
                'notes'                 => $data['notes'] ?? $transfer->notes,
            ]);
            $message = 'تم رفض النقل — الطلب متاح لإعادة التعيين.';
        }

        return response()->json([
            'transfer' => $transfer->fresh(),
            'message'  => $message,
        ]);
    }

    private function findTransfer(Request $request, int $id): SupervisionTransfer
    {
        return SupervisionTransfer::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);
    }
}
