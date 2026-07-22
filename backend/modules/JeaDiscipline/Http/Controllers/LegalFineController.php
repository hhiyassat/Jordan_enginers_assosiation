<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Modules\JeaDiscipline\Models\LegalFine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * LegalFineController — JORD-82
 *
 * Admin-only endpoints for the C-05 legal-fines subsystem.
 *   GET  /admin/legal-fines               — list all in org
 *   POST /admin/legal-fines               — issue new (range-validated)
 *   POST /admin/legal-fines/{id}/pay      — mark paid
 *
 * Range validation is enforced here (not on the model) so a
 * schema-authored amendment to the manual doesn't require a
 * migration — bumping LegalFine::BOUNDS is the whole change.
 */
class LegalFineController extends Controller
{
    /** GET /admin/legal-fines */
    public function index(Request $request): JsonResponse
    {
        $fines = LegalFine::where('organization_id', $request->user()->organization_id)
            ->with(['issuedBy:id,name', 'application:id,reference_number'])
            ->orderByDesc('issued_at')
            ->limit(500)
            ->get();

        return response()->json([
            'fines'  => $fines,
            'bounds' => LegalFine::BOUNDS,
        ]);
    }

    /**
     * POST /admin/legal-fines
     *   { kind, amount_jod, target_display, project_area_m2?,
     *     application_id?, reason }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind'            => ['required', 'in:unlicensed_contractor_small,unlicensed_contractor_large'],
            'amount_jod'      => ['required', 'numeric', 'min:0'],
            'target_display'  => ['required', 'string', 'max:255'],
            'project_area_m2' => ['nullable', 'integer', 'min:1'],
            'application_id'  => ['nullable', 'integer', 'exists:applications,id'],
            'reason'          => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        // Range check — amount must fall within the kind's bounds.
        $bounds = LegalFine::BOUNDS[$data['kind']];
        if ($data['amount_jod'] < $bounds['min'] || $data['amount_jod'] > $bounds['max']) {
            return response()->json([
                'message' => sprintf(
                    'قيمة الغرامة يجب أن تكون بين %d و %d دينار لهذا النوع.',
                    $bounds['min'], $bounds['max'],
                ),
                'errors'  => ['amount_jod' => sprintf(
                    'خارج النطاق %d–%d', $bounds['min'], $bounds['max'],
                )],
            ], 422);
        }

        // Area consistency check — small kind should be ≤ threshold,
        // large kind should be >. Admin gets a nudge if they mis-select.
        if (!empty($data['project_area_m2'])) {
            $threshold = $bounds['area_threshold_m2'];
            $areaOk = $data['kind'] === LegalFine::KIND_UNLICENSED_SMALL
                ? $data['project_area_m2'] <= $threshold
                : $data['project_area_m2'] >  $threshold;
            if (!$areaOk) {
                return response()->json([
                    'message' => 'نوع الغرامة لا يتوافق مع مساحة المشروع (الحد 250 م²).',
                    'errors'  => ['kind' => 'مساحة المشروع تشير إلى النوع الآخر.'],
                ], 422);
            }
        }

        // Cross-org guard on application_id if provided.
        if (!empty($data['application_id'])) {
            $app = Application::where('organization_id', $request->user()->organization_id)
                ->findOrFail($data['application_id']);
            $data['application_id'] = $app->id;
        }

        $fine = LegalFine::create([
            ...$data,
            'organization_id'   => $request->user()->organization_id,
            'issued_by_user_id' => $request->user()->id,
            'issued_at'         => now(),
        ]);

        return response()->json([
            'fine'    => $fine,
            'message' => 'تم إصدار الغرامة.',
        ], 201);
    }

    /** POST /admin/legal-fines/{id}/pay  { payment_reference } */
    public function pay(Request $request, int $id): JsonResponse
    {
        $fine = LegalFine::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        if ($fine->isPaid()) {
            return response()->json([
                'message' => 'الغرامة مدفوعة سابقاً — لا يمكن الدفع مرتين.',
            ], 422);
        }

        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:128'],
        ]);

        $fine->update([
            'paid_at'           => now(),
            'payment_reference' => $data['payment_reference'],
        ]);

        return response()->json([
            'fine'    => $fine->fresh(),
            'message' => 'تم تسجيل الدفع.',
        ]);
    }
}
