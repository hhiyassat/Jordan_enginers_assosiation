<?php

namespace App\Http\Controllers\Api;

use App\Engine\SchemaStructureValidator;
use App\Http\Controllers\Controller;
use App\Models\ServiceDefinition;
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
    // ── Admin: all services (active + draft + inactive) ──────────────────

    public function adminIndex(Request $request): JsonResponse
    {
        $services = ServiceDefinition::where('organization_id', $request->user()->organization_id)
            ->orderByDesc('created_at')
            ->get(['id', 'code', 'name_ar', 'name_en', 'status', 'currency', 'created_at', 'updated_at']);

        return response()->json(['services' => $services]);
    }

    public function adminShow(Request $request, int $id): JsonResponse
    {
        $service = ServiceDefinition::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        return response()->json(['service' => $service]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            abort(403, 'المسؤولون فقط يمكنهم تغيير حالة الخدمة.');
        }

        $service = ServiceDefinition::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:active,inactive,draft'],
        ]);

        $service->update(['status' => $data['status']]);

        return response()->json(['service' => $service]);
    }

    // ── Public catalog (active only) ──────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $services = ServiceDefinition::where('organization_id', $request->user()->organization_id)
            ->where('status', 'active')
            ->get([
                'id', 'code', 'parent_code', 'name_ar', 'name_en',
                'description_ar', 'description_en', 'currency', 'base_fee', 'sla_hours',
            ]);

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
        if (! $request->user()->isAdmin()) {
            abort(403, 'المسؤولون فقط يمكنهم إنشاء خدمات جديدة.');
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

    public function update(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            abort(403, 'المسؤولون فقط يمكنهم تعديل الخدمات.');
        }

        $service = ServiceDefinition::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

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
