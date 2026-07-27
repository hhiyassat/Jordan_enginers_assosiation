<?php

declare(strict_types=1);

namespace Modules\JeaDiscipline\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\JeaDiscipline\Http\Requests\PayLegalFineRequest;
use Modules\JeaDiscipline\Http\Requests\StoreLegalFineRequest;
use Modules\JeaDiscipline\Models\LegalFine;
use Modules\JeaServices\Models\Application;

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
            'fines' => $fines,
            'bounds' => LegalFine::BOUNDS,
        ]);
    }

    /**
     * POST /admin/legal-fines
     *   { kind, amount_jod, target_display, project_area_m2?,
     *     application_id?, reason }
     */
    public function store(StoreLegalFineRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Range check — amount must fall within the kind's bounds.
        $bounds = LegalFine::BOUNDS[$data['kind']];
        if ($data['amount_jod'] < $bounds['min'] || $data['amount_jod'] > $bounds['max']) {
            return response()->json([
                'message' => sprintf(
                    'قيمة الغرامة يجب أن تكون بين %d و %d دينار لهذا النوع.',
                    $bounds['min'], $bounds['max'],
                ),
                'errors' => ['amount_jod' => sprintf(
                    'خارج النطاق %d–%d', $bounds['min'], $bounds['max'],
                )],
            ], 422);
        }

        // Area consistency check — small kind should be ≤ threshold,
        // large kind should be >. Admin gets a nudge if they mis-select.
        if (! empty($data['project_area_m2'])) {
            $threshold = $bounds['area_threshold_m2'];
            $areaOk = $data['kind'] === LegalFine::KIND_UNLICENSED_SMALL
                ? $data['project_area_m2'] <= $threshold
                : $data['project_area_m2'] > $threshold;
            if (! $areaOk) {
                return response()->json([
                    'message' => 'نوع الغرامة لا يتوافق مع مساحة المشروع (الحد 250 م²).',
                    'errors' => ['kind' => 'مساحة المشروع تشير إلى النوع الآخر.'],
                ], 422);
            }
        }

        // Cross-org guard on application_id if provided.
        if (! empty($data['application_id'])) {
            $app = Application::where('organization_id', $request->user()->organization_id)
                ->findOrFail($data['application_id']);
            $data['application_id'] = $app->id;
        }

        $fine = DB::transaction(function () use ($request, $data) {
            $fine = LegalFine::create([
                ...$data,
                'organization_id' => $request->user()->organization_id,
                'issued_by_user_id' => $request->user()->id,
                'issued_at' => now(),
            ]);

            AuditLog::record(
                user: $request->user(),
                subject: $fine,
                action: 'legal_fine.issued',
                extra: [
                    'rule_id' => 'ESP-DISC-003',
                    'input_snapshot' => [
                        'kind' => $data['kind'],
                        'amount_jod' => $data['amount_jod'],
                        'target_display' => $data['target_display'],
                        'application_id' => $data['application_id'] ?? null,
                        'reason' => $data['reason'],
                    ],
                ],
            );

            return $fine;
        });

        return response()->json([
            'fine' => $fine,
            'message' => 'تم إصدار الغرامة.',
        ], 201);
    }

    /** POST /admin/legal-fines/{id}/pay  { payment_reference } */
    public function pay(PayLegalFineRequest $request, int $id): JsonResponse
    {
        $fine = LegalFine::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        if ($fine->isPaid()) {
            return response()->json([
                'message' => 'الغرامة مدفوعة سابقاً — لا يمكن الدفع مرتين.',
            ], 422);
        }

        $data = $request->validated();

        DB::transaction(function () use ($request, $fine, $data) {
            $fine->update([
                'paid_at' => now(),
                'payment_reference' => $data['payment_reference'],
            ]);

            AuditLog::record(
                user: $request->user(),
                subject: $fine,
                action: 'legal_fine.paid',
                extra: [
                    'rule_id' => 'ESP-DISC-004',
                    'input_snapshot' => [
                        'payment_reference' => $data['payment_reference'],
                    ],
                ],
            );
        });

        return response()->json([
            'fine' => $fine->fresh(),
            'message' => 'تم تسجيل الدفع.',
        ]);
    }
}
