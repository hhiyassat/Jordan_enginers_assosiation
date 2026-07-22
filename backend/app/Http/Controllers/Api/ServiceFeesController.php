<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Concerns\RespondsWithLockedService;
use App\Http\Controllers\Controller;
use App\Models\ServiceDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ServiceFeesController — Workstream 5 extraction from
 * ServiceCatalogController.
 *
 * Owns the admin fee-editor surface (JORD-85 PM):
 *   • GET  /admin/service-fees          — compact fee-only listing
 *   • PATCH /admin/services/{id}/fee    — focused fee editor
 *
 * The methods moved verbatim from ServiceCatalogController — behaviour
 * is identical, only the containing class changes. The old class's
 * `lockedResponse` private helper became a shared trait
 * (RespondsWithLockedService) so both controllers emit the same 423
 * envelope. Duplicated CATEGORY_ORDER const will consolidate into a
 * JEA constants class in Workstream 8 when the module folder exists.
 *
 * Tag: SM (JEA-specific — fee schema shape + Arabic 423 copy).
 */
class ServiceFeesController extends Controller
{
    use RespondsWithLockedService;

    /**
     * Duplicated from ServiceCatalogController until Workstream 8
     * moves both controllers into modules/jea-services/ and this
     * becomes a shared const.
     */
    private const CATEGORY_ORDER = [
        'JEA-PROJ',
        'JEA-SURV',
        'JEA-FIN',
        'JEA-CERT',
        'JEA-ENG',
        'JEA-DEC',
        'JEA-MISC',
    ];

    /**
     * JORD-85: compact fee-only listing for the admin fee editor page.
     * Projects schema.fee into a lightweight row so we don't have to
     * ship every service's full schema back for a fee grid.
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $orderCases = collect(self::CATEGORY_ORDER)
            ->map(fn (string $code, int $i) => "WHEN '{$code}' THEN {$i}")
            ->implode(' ');
        $orderExpr = "CASE parent_code {$orderCases} ELSE 99 END";

        $rows = ServiceDefinition::where('organization_id', $orgId)
            ->whereNotNull('parent_code')
            ->orderByRaw($orderExpr)
            ->orderBy('code')
            ->get(['id', 'code', 'parent_code', 'name_ar', 'name_en', 'status', 'is_locked', 'schema'])
            ->map(fn ($s) => [
                'id'          => $s->id,
                'code'        => $s->code,
                'parent_code' => $s->parent_code,
                'name_ar'     => $s->name_ar,
                'name_en'     => $s->name_en,
                'status'      => $s->status,
                'is_locked'   => (bool) $s->is_locked,
                'fee'         => $s->schema['fee'] ?? null,
            ]);

        return response()->json(['fees' => $rows]);
    }

    /**
     * JORD-85: focused fee editor. Merges a compact {type, amount,
     * currency, basis?, notes?} payload into schema.fee without
     * requiring the admin to send the entire schema back. Locked
     * services still refuse — fee is part of the schema and shares
     * the lock invariant.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->canEditServices()) {
            abort(403, 'المسؤولون والمستخدم الأعلى فقط يمكنهم تعديل رسوم الخدمة.');
        }

        $service = ServiceDefinition::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        if ($service->isLocked()) {
            return $this->lockedResponse($service);
        }

        $data = $request->validate([
            'type'     => ['required', 'in:fixed,per_unit,free'],
            'amount'   => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'basis'    => ['nullable', 'string', 'max:64'],
            'rate'     => ['nullable', 'numeric', 'min:0'],
            'notes'    => ['nullable', 'string', 'max:500'],
        ]);

        $schema = $service->schema ?? [];
        $existingFee = $schema['fee'] ?? [];

        // per_unit needs basis + rate; fixed needs amount; free is a flag.
        $fee = ['type' => $data['type'], 'currency' => $data['currency'] ?? 'JOD'];
        if ($data['type'] === 'fixed') {
            $fee['amount'] = (float) ($data['amount'] ?? 0);
        } elseif ($data['type'] === 'per_unit') {
            if (empty($data['basis']) || !isset($data['rate'])) {
                return response()->json([
                    'message' => 'per_unit يتطلب basis + rate.',
                ], 422);
            }
            $fee['basis'] = $data['basis'];
            $fee['rate']  = (float) $data['rate'];
        }
        // free: no amount / basis / rate — a flag-only block.

        // Preserve surcharges + audit source across edits; append the
        // admin-set marker so the seeder's placeholder rule (which
        // treats fixed:0 as placeholder) will not overwrite this row.
        if (!empty($existingFee['surcharges'])) $fee['surcharges'] = $existingFee['surcharges'];
        $fee['source'] = ($data['notes'] ?? null)
            ?: ('Admin-set via fee editor on ' . now()->toDateString() . ' by user #' . $request->user()->id);

        $schema['fee'] = $fee;
        $service->update(['schema' => $schema]);

        return response()->json(['service' => $service->fresh()]);
    }
}
