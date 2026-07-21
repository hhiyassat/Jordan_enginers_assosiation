<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecurringObligation;
use App\Models\User;
use App\Services\RecurringDuesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RecurringDuesController — JORD-79
 *
 * Admin surface for the F-04 registration + F-05 annual dues
 * subsystem. Scoped through the admin's own organization so
 * cross-org peeks 404. Manual payment recording only — no gateway
 * integration in this ticket; the reference is stored as an opaque
 * string (e.g. bank transfer id) that a real payment gateway
 * would fill later.
 *
 * Routes:
 *   GET  /admin/offices/{id}/dues           → list all obligations for an office
 *   POST /admin/dues/{obligationId}/pay     → mark paid (computes late surcharge)
 *   POST /admin/offices/{id}/dues/register  → seed the one-time F-04
 */
class RecurringDuesController extends Controller
{
    public function __construct(private readonly RecurringDuesService $svc) {}

    /**
     * GET /admin/offices/{id}/dues
     */
    public function index(Request $request, int $officeId): JsonResponse
    {
        $office = $this->findOffice($request, $officeId);
        $obligations = RecurringObligation::where('office_user_id', $office->id)
            ->orderByDesc('period_year')
            ->orderByDesc('kind')
            ->get();

        return response()->json([
            'office'      => [
                'id'                    => $office->id,
                'name'                  => $office->name,
                'office_classification' => $office->office_classification,
            ],
            'obligations' => $obligations,
            'rate_table'  => RecurringDuesService::RATES,
        ]);
    }

    /**
     * POST /admin/dues/{obligationId}/pay
     * Body: { payment_reference: string }
     */
    public function pay(Request $request, int $obligationId): JsonResponse
    {
        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:128'],
        ]);

        $obligation = RecurringObligation::findOrFail($obligationId);
        // Cross-org guard: obligation must belong to an office in
        // the admin's own organization.
        if ($obligation->organization_id !== $request->user()->organization_id) {
            abort(404);
        }
        if ($obligation->isPaid()) {
            return response()->json([
                'message' => 'هذه الرسوم مدفوعة سابقاً — لا يمكن الدفع مرتين.',
            ], 422);
        }

        $obligation = $this->svc->markPaid($obligation, $data['payment_reference']);

        return response()->json([
            'obligation' => $obligation,
            'message'    => 'تم تسجيل الدفع.',
        ]);
    }

    /**
     * POST /admin/offices/{id}/dues/register
     * Idempotent — returns the existing registration if one already
     * exists for this office + year.
     */
    public function seedRegistration(Request $request, int $officeId): JsonResponse
    {
        $office = $this->findOffice($request, $officeId);
        $obligation = $this->svc->ensureRegistrationFee($office);
        return response()->json([
            'obligation' => $obligation,
            'message'    => $obligation->wasRecentlyCreated
                ? 'تم إنشاء رسوم التسجيل.'
                : 'رسوم التسجيل موجودة بالفعل لهذه السنة.',
        ]);
    }

    private function findOffice(Request $request, int $id): User
    {
        return User::where('organization_id', $request->user()->organization_id)
            ->where('role', 'applicant')
            ->findOrFail($id);
    }
}
