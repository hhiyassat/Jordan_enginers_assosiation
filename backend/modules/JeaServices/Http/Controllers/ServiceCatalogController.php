<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Controllers;

use Modules\JeaServices\Engine\SchemaStructureValidator;
use Modules\JeaServices\Governance\ServiceAvailabilityPolicy;
use Modules\JeaServices\Http\Concerns\RespondsWithLockedService;
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

    // lockedResponse() moved to Modules\JeaServices\Http\Concerns\RespondsWithLockedService
    // (Workstream 5). Both ServiceCatalogController and ServiceFeesController
    // consume the trait so the 423 envelope stays consistent.

    // ── Public catalog (active only) ──────────────────────────────────
    //
    // JEA-CATALOG: The service catalog is JEA-owned and shared across every
    // office tenant. `service_definitions.code` is globally UNIQUE, which is
    // the schema-level statement that codes describe a single JEA catalog —
    // not one catalog per tenant. So the read paths bypass the per-org global
    // scope via ::withoutOrgScope(). Admin write paths above keep the scope,
    // so only the org that seeded the catalog (demo/JEA) can edit it.
    //
    // Without this, every newly-approved office would see an empty catalog
    // and be unable to submit any service — since the office-registration
    // approval flow does not (and should not) clone service definitions.

    public function index(Request $request): JsonResponse
    {
        // SG-02 · Consult ServiceAvailabilityPolicy for each service. Under
        // LENIENT default mode, legacy `status='active'` services remain
        // visible so no existing catalog entry disappears; RETIRED and
        // SUSPENDED services are hidden from applicants and visible to
        // admins. See JDG-SG02-01 for the preference order.
        $actorIsAdmin = (bool) ($request->user()?->isAdmin() ?? false);
        $policy       = app(ServiceAvailabilityPolicy::class);

        $rows = ServiceDefinition::withoutOrgScope()
            ->get([
                'id', 'code', 'parent_code',
                'subcategory_ar', 'subcategory_en',
                'name_ar', 'name_en',
                'description_ar', 'description_en', 'currency', 'base_fee', 'sla_hours',
                'phase', 'schema', 'status', 'publication_status', 'uat_status',
                'effective_from',
            ]);

        $services = $rows
            ->filter(fn (ServiceDefinition $s) => $policy->evaluate($s, $actorIsAdmin)->catalogVisible)
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
            })
            ->values();

        return response()->json(['services' => $services]);
    }

    public function show(Request $request, string $code): JsonResponse
    {
        // JEA-CATALOG: shared catalog — see index() comment above.
        // SG-02: consult availability policy; return 404 if hidden.
        $service = ServiceDefinition::withoutOrgScope()
            ->where('code', $code)
            ->firstOrFail();

        $actorIsAdmin = (bool) ($request->user()?->isAdmin() ?? false);
        $verdict = app(ServiceAvailabilityPolicy::class)->evaluate($service, $actorIsAdmin);

        if (!$verdict->catalogVisible) {
            abort(404);
        }

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
