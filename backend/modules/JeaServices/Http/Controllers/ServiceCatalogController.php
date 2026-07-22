<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Controllers;

use Modules\JeaServices\Engine\SchemaStructureValidator;
use App\Http\Concerns\RespondsWithLockedService;
use App\Http\Controllers\Controller;
use Modules\JeaServices\Models\ServiceDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ServiceCatalogController
 *
 * FR-001: Browse active service catalog.
 * FR-017: Admin creates new service definitions.
 * P-5: All queries scoped by organization_id.
 *
 * SCHEMA VALIDATION:
 *   Every store()/update() call that includes a schema runs it through
 *   SchemaStructureValidator before persisting. This ensures all engine
 *   components (SchemaValidator, WorkflowEngine, FeeCalculator) can run
 *   against the stored schema without silent failures — for both the demo
 *   service AND every AI-generated service.
 */
class ServiceCatalogController extends Controller
{
    use RespondsWithLockedService;

    /**
     * Canonical category display order — mirrors ServicePlan2026Seeder's
     * services() array so the admin page groups the same way the plan
     * PDF does. Keep in sync with the seeder if the plan ever reorders.
     */
    private const CATEGORY_ORDER = [
        'JEA-PROJ',   // خدمات تصديق المخططات الهندسية
        'JEA-SURV',   // استطلاع الموقع
        'JEA-FIN',    // الخدمات المالية
        'JEA-CERT',   // الشهادات
        'JEA-ENG',    // المهندسون في المكاتب
        'JEA-DEC',    // قرارات هيئة المكاتب
        'JEA-MISC',   // خدمات أخرى
    ];

    // ── Admin: all services (active + draft + inactive) ──────────────────

    /**
     * List every actual service in the org, grouped by parent category
     * and ordered canonically. We exclude parent_code=NULL rows because
     * those are the seven category "tiles" (JEA-PROJ, JEA-SURV, …) —
     * they are folders in the taxonomy, not bookable services, so they
     * inflated the admin count from 56 → 63 and appeared as junk cards
     * on the management page. The tiles are still returned separately
     * as `categories` so the frontend can render group headers.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        // Sort inside SQL by the canonical category order (FIELD/CASE),
        // then by code within the category. Ordering client-side would
        // require the frontend to know the plan order too, which we
        // avoid by making it the API contract.
        $orderCases = collect(self::CATEGORY_ORDER)
            ->map(fn (string $code, int $i) => "WHEN '{$code}' THEN {$i}")
            ->implode(' ');
        $orderExpr = "CASE parent_code {$orderCases} ELSE 99 END";

        $services = ServiceDefinition::where('organization_id', $orgId)
            ->whereNotNull('parent_code')
            ->orderByRaw($orderExpr)
            ->orderBy('code')
            ->get([
                'id', 'code', 'parent_code',
                'subcategory_ar', 'subcategory_en',
                'name_ar', 'name_en',
                'status', 'currency', 'base_fee', 'sla_hours',
                'phase', 'is_locked',
                'created_at', 'updated_at',
            ]);

        // Category tiles for group headers — same canonical order as above.
        $tiles = ServiceDefinition::where('organization_id', $orgId)
            ->whereNull('parent_code')
            ->whereIn('code', self::CATEGORY_ORDER)
            ->get(['code', 'name_ar', 'name_en'])
            ->keyBy('code');
        $categories = collect(self::CATEGORY_ORDER)
            ->map(fn (string $code) => $tiles->get($code))
            ->filter()
            ->values();

        return response()->json([
            'services'   => $services,
            'categories' => $categories,
        ]);
    }

    public function adminShow(Request $request, int $id): JsonResponse
    {
        $service = ServiceDefinition::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        return response()->json(['service' => $service]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->canEditServices()) {
            abort(403, 'المسؤولون والمستخدم الأعلى فقط يمكنهم تغيير حالة الخدمة.');
        }

        $service = ServiceDefinition::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        if ($service->isLocked()) {
            return $this->lockedResponse($service);
        }

        $data = $request->validate([
            'status' => ['required', 'in:active,inactive,draft'],
        ]);

        $service->update(['status' => $data['status']]);

        return response()->json(['service' => $service]);
    }

    /**
     * Flip is_locked=false so subsequent update / updateStatus / chat-schema
     * calls are accepted. Kept as its own endpoint (rather than an
     * `is_locked` field on update()) so an admin cannot ACCIDENTALLY unlock
     * as a side-effect of a normal edit — unlocking is always an explicit
     * intentional call.
     */
    public function unlock(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->canEditServices()) {
            abort(403, 'المسؤولون والمستخدم الأعلى فقط يمكنهم فتح قفل الخدمة.');
        }
        $service = ServiceDefinition::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);
        $service->update(['is_locked' => false]);
        return response()->json(['service' => $service, 'message' => 'تم فتح قفل الخدمة — يمكن الآن تعديل تفاصيلها.']);
    }

    public function lock(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->canEditServices()) {
            abort(403, 'المسؤولون والمستخدم الأعلى فقط يمكنهم إقفال الخدمة.');
        }
        $service = ServiceDefinition::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);
        $service->update(['is_locked' => true]);
        return response()->json(['service' => $service, 'message' => 'تم إقفال الخدمة — أصبحت للقراءة فقط.']);
    }

    // lockedResponse() moved to App\Http\Concerns\RespondsWithLockedService
    // (Workstream 5). Both ServiceCatalogController and ServiceFeesController
    // consume the trait so the 423 envelope stays consistent.

    // ── Public catalog (active only) ──────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $services = ServiceDefinition::where('organization_id', $request->user()->organization_id)
            ->where('status', 'active')
            ->get([
                'id', 'code', 'parent_code',
                'subcategory_ar', 'subcategory_en',
                'name_ar', 'name_en',
                'description_ar', 'description_en', 'currency', 'base_fee', 'sla_hours',
                'phase', 'schema',
            ])
            // Trim the schema down to a lightweight variant_keys list so the
            // frontend can show a "Modify" CTA without pulling the full
            // workflow tree on every catalog listing.
            ->map(function (ServiceDefinition $s) {
                $variants = data_get($s->schema, 'workflow.variants', []);
                $arr = $s->only([
                    'id', 'code', 'parent_code',
                    'subcategory_ar', 'subcategory_en',
                    'name_ar', 'name_en',
                    'description_ar', 'description_en', 'currency', 'base_fee', 'sla_hours', 'phase',
                ]);
                $arr['variant_keys'] = is_array($variants) ? array_keys($variants) : [];
                return $arr;
            });

        return response()->json(['services' => $services]);
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $service = ServiceDefinition::where('organization_id', $request->user()->organization_id)
            ->where('code', $code)
            ->where('status', 'active')
            ->firstOrFail();

        return response()->json(['service' => $service]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->canEditServices()) {
            abort(403, 'المسؤولون والمستخدم الأعلى فقط يمكنهم إنشاء خدمات جديدة.');
        }

        $data = $request->validate([
            'code'           => ['required', 'string', 'max:20', 'unique:service_definitions,code'],
            'name_ar'        => ['required', 'string', 'max:255'],
            'name_en'        => ['required', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'currency'       => ['nullable', 'string', 'size:3'],
            'schema'         => ['required', 'array'],
            'status'         => ['nullable', 'in:active,inactive,draft'],
        ]);

        // ESP-SCHEMA-001: validate schema structure before persisting.
        // This ensures WorkflowEngine, SchemaValidator, and FeeCalculator
        // can run against ANY generated or manually-authored service schema.
        $schemaErrors = (new SchemaStructureValidator())->validate($data['schema']);
        if ($schemaErrors) {
            return response()->json([
                'message' => 'المخطط لا يتوافق مع بنية ESP v2. يرجى مراجعة الأخطاء أدناه.',
                'errors'  => $schemaErrors,
            ], 422);
        }

        $service = ServiceDefinition::create([
            ...$data,
            'organization_id' => $request->user()->organization_id,
            'status'          => $data['status'] ?? 'draft',
        ]);

        return response()->json(['service' => $service], 201);
    }

    // Workstream 5: adminFeesIndex() + updateFee() moved to
    // ServiceFeesController. Routes were updated to match.

    public function update(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->canEditServices()) {
            abort(403, 'المسؤولون والمستخدم الأعلى فقط يمكنهم تعديل الخدمات.');
        }

        $service = ServiceDefinition::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        if ($service->isLocked()) {
            return $this->lockedResponse($service);
        }

        $data = $request->validate([
            'name_ar'        => ['sometimes', 'string', 'max:255'],
            'name_en'        => ['sometimes', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'schema'         => ['sometimes', 'array'],
            'status'         => ['sometimes', 'in:active,inactive,draft'],
        ]);

        // ESP-SCHEMA-001: validate schema structure on update if schema is being changed.
        if (isset($data['schema'])) {
            $schemaErrors = (new SchemaStructureValidator())->validate($data['schema']);
            if ($schemaErrors) {
                return response()->json([
                    'message' => 'المخطط لا يتوافق مع بنية ESP v2. يرجى مراجعة الأخطاء أدناه.',
                    'errors'  => $schemaErrors,
                ], 422);
            }
        }

        $service->update($data);

        return response()->json(['service' => $service]);
    }
}
