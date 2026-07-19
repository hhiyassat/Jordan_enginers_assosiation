<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Engine\FeeCalculator;
use App\Engine\SchemaValidator;
use App\Engine\WorkflowEngine;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmPaymentRequest;
use App\Http\Requests\DecideApplicationRequest;
use App\Http\Requests\StoreApplicationRequest;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Certificate;
use App\Models\ServiceDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * ApplicationController
 *
 * BUILD CONTRACT compliance:
 *   ✅ P-3: No inline $request->validate() — FormRequest classes used
 *   ✅ P-5: All queries scoped by organization_id via scopeForOrganization()
 *   ✅ WF-001: State mutations go through WorkflowEngine only
 *   ✅ EDA-10: Validation failures return 422 with field errors (never silently stripped)
 */
class ApplicationController extends Controller
{
    // ── List applications ─────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        // Include `schema` on the service_definition so MyApplications can
        // render a per-row stage timeline (past → current → future) without
        // a follow-up round-trip per row. The `schema` column is JSON — the
        // frontend picks off workflow.stages[] — so the size cost is small
        // for a typical catalog.
        $query = Application::forOrganization($request->user()->organization_id)
            ->with([
                'serviceDefinition:id,code,name_ar,name_en,schema',
                'certificate:id,application_id,certificate_number,status',
            ])
            ->orderByDesc('created_at');

        // Applicants see only their own; staff/admin see all
        if ($request->user()->isApplicant()) {
            $query->where('applicant_id', $request->user()->id);
        }

        return response()->json(['applications' => $query->get()]);
    }

    // ── Show single application ───────────────────────────────────────

    public function show(Request $request, int $id): JsonResponse
    {
        $app = $this->findAccessible($request, $id);
        $app->load(['serviceDefinition', 'project', 'applicant:id,name,email', 'documents', 'reviews.reviewer:id,name,role', 'certificate']);

        // Attach the schema-driven action set for the caller's role at the
        // application's current stage. The frontend renders one button per
        // available action; unknown ids from a drifted schema are skipped.
        $service = $app->serviceDefinition;
        $available = $service instanceof \App\Models\ServiceDefinition
            ? \App\Engine\StageActions::forApplication($app, $service, $request->user()?->role)
            : [];

        return response()->json([
            'application'       => $app,
            'available_actions' => $available,
        ]);
    }

    // ── Create draft ──────────────────────────────────────────────────

    public function store(StoreApplicationRequest $request): JsonResponse
    {
        // SEC: Only applicants may create applications — staff/admin/auditor must not
        if (! $request->user()->isApplicant()) {
            return response()->json(['message' => 'المسؤولون والموظفون لا يمكنهم تقديم طلبات.'], 403);
        }

        // P-5: Organization-scoped service lookup
        $service = ServiceDefinition::where('organization_id', $request->user()->organization_id)
            ->where('code', $request->service_code)
            ->where('status', 'active')
            ->firstOrFail();

        // If project_id was passed, verify it belongs to the actor's org AND
        // is owned by them. Cross-org or cross-user access is an escalation
        // vector we close at the controller boundary — the FormRequest's
        // `exists:` rule only proves the row exists globally, not that this
        // user may read it.
        $projectId = null;
        if ($request->filled('project_id')) {
            $project = \App\Models\Project::where('id', (int) $request->project_id)
                ->where('organization_id', $request->user()->organization_id)
                ->where('owner_user_id', $request->user()->id)
                ->first();
            if (! $project) {
                return response()->json([
                    'message' => 'المشروع غير مرتبط بحسابك.',
                    'errors'  => ['project_id' => ['المشروع غير موجود أو لا يخصك.']],
                ], 422);
            }
            $projectId = $project->id;
        }

        $fee = (new FeeCalculator($service))->calculate($request->data);

        $app = Application::create([
            'reference_number'      => Application::generateReference($service),
            'organization_id'       => $request->user()->organization_id,
            'service_definition_id' => $service->id,
            'project_id'            => $projectId,
            'applicant_id'          => $request->user()->id,
            'status'                => Application::STATUS_DRAFT,
            'data'                  => $request->data,
            'fee_amount'            => $fee,
            'payment_status'        => $fee > 0 ? 'pending' : 'waived',
        ]);

        return response()->json(['application' => $app], 201);
    }

    // ── Update draft ──────────────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $app = $this->findAccessible($request, $id);

        if (! $app->isEditable()) {
            return response()->json(['message' => 'يمكن تعديل الطلبات في مرحلة المسودة أو طلب التعديل فقط.'], 422);
        }

        // `present`, not `required` — same reasoning as StoreApplicationRequest:
        // an empty {} is a legitimate draft state, per-field enforcement runs
        // in SchemaValidator on POST /submit. Explicit Arabic message so the
        // Apply banner isn't in English on the frontend.
        $data = $request->validate(
            ['data' => ['present', 'array']],
            [
                'data.present' => 'حقل بيانات الطلب مفقود من الطلب.',
                'data.array'   => 'بيانات الطلب يجب أن تكون كائناً.',
            ]
        );
        $fee  = (new FeeCalculator($app->serviceDefinition))->calculate($data['data']);

        $app->update(['data' => $data['data'], 'fee_amount' => $fee]);

        return response()->json(['application' => $app]);
    }

    // ── Submit ────────────────────────────────────────────────────────

    /**
     * EDA-10 / WF-005: Validation failure returns 422 with field-level errors.
     * The application stays in draft — case identity preserved.
     * Frontend navigates back to form step and shows errors inline.
     *
     * BUILD CONTRACT P-1: Validation rules are NEVER removed to unblock this flow.
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $app     = $this->findAccessible($request, $id);
        $service = $app->serviceDefinition;

        // EDA B-4 / WF-005: validate schema fields
        $dataErrors = (new SchemaValidator($service))->validateData($app->data ?? []);
        if ($dataErrors) {
            // EDA-10: Correctable Defect — return field errors, application stays in draft
            return response()->json([
                'message' => 'يوجد أخطاء في البيانات. يرجى مراجعة الحقول المحددة.',
                'errors'  => $dataErrors,
            ], 422);
        }

        // WF-006: validate required documents
        $uploadedIds = $app->documents->pluck('document_id')->toArray();
        $docErrors   = (new SchemaValidator($service))->validateDocuments($uploadedIds, $app->data ?? []);
        if ($docErrors) {
            return response()->json([
                'message' => 'يوجد مستندات مطلوبة غير مرفوعة.',
                'errors'  => $docErrors,
            ], 422);
        }

        // WF-001: delegate to WorkflowEngine (EDA B-5, B-9)
        $engine = new WorkflowEngine($service);
        $app    = $engine->submit($app, $request->user());

        return response()->json(['application' => $app]);
    }

    // ── Upload document ───────────────────────────────────────────────

    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        $app = $this->findAccessible($request, $id);

        if (! $app->isEditable()) {
            return response()->json(['message' => 'المستندات تُرفع فقط للطلبات في مرحلة المسودة.'], 422);
        }

        $request->validate([
            'document_id' => ['required', 'string'],
            // SEC-008 hardened: only PDF drawings and DWG source files are
            // accepted as application attachments. The PdfOrDwgFile rule
            // inspects the leading bytes (not just the extension) to reject
            // renamed executables. 50 MB outer cap matches the schema-level
            // per-slot cap enforced downstream by SchemaValidator.
            'file'        => ['required', 'file', 'max:51200', new \App\Rules\PdfOrDwgFile()],
        ]);

        $file = $request->file('file');
        // NFR-010: store on the configured default disk. Production must set
        // FILESYSTEM_DISK=s3 (or another object-storage driver); dev may fall
        // back to 'local' via .env. StorageServiceProvider fails fast at boot
        // in production if the disk is not object storage.
        $disk   = config('filesystems.default');
        $stored = $file->storeAs(
            "uploads/applications/{$app->id}",
            \Illuminate\Support\Str::uuid() . '.' . $file->getClientOriginalExtension(),
            ['disk' => $disk]
        );

        // Replace existing upload for this document slot
        ApplicationDocument::where('application_id', $app->id)
            ->where('document_id', $request->document_id)
            ->delete();

        $doc = ApplicationDocument::create([
            'application_id'    => $app->id,
            'document_id'       => $request->document_id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename'   => basename($stored),
            'disk'              => $disk,
            'path'              => $stored,
            'mime_type'         => $file->getMimeType(),
            'size_bytes'        => $file->getSize(),
            'uploaded_by'       => $request->user()->id,
        ]);

        return response()->json(['document' => $doc], 201);
    }

    // ── Reviewer queue ────────────────────────────────────────────────

    public function reviewQueue(Request $request): JsonResponse
    {
        $user = $request->user();

        // Admin, staff, and auditors can all see the queue
        if (! ($user->isReviewer() || $user->isAdmin())) {
            abort(403, 'المراجعون فقط يمكنهم الوصول لهذه القائمة.');
        }

        $org = $user->organization_id;

        // Queue = unassigned submitted apps  PLUS  apps currently claimed by this user
        $submitted = Application::forOrganization($org)
            ->with(['serviceDefinition:id,code,name_ar,name_en,schema', 'applicant:id,name,email'])
            ->where('status', Application::STATUS_SUBMITTED)
            ->whereNull('assigned_reviewer_id')
            ->orderBy('sla_deadline');

        $myInProgress = Application::forOrganization($org)
            ->with(['serviceDefinition:id,code,name_ar,name_en,schema', 'applicant:id,name,email'])
            ->where('status', Application::STATUS_UNDER_REVIEW)
            ->where('assigned_reviewer_id', $user->id)
            ->orderBy('sla_deadline');

        // Merge both sets, my in-progress first
        $applications = $myInProgress->get()->merge($submitted->get());

        // Filter to what THIS reviewer can actually act on. Admin sees
        // everything (they're the tiebreaker); every other reviewer sees
        // only applications whose current stage's role matches theirs.
        // Without this, staff opened auditor-owned stages and hit
        // "Stage 'X' requires role 'auditor'" with no way to know
        // upfront that the queue lied to them.
        if (! $user->isAdmin()) {
            $applications = $applications->filter(function ($app) use ($user) {
                $service = $app->serviceDefinition;
                if (! $service) return false;
                $stage = $service->getStage($app->current_stage ?? '');
                if (! $stage) return true; // no stage info = don't hide; let claim() decide
                return ($stage['role'] ?? null) === $user->role;
            });
        }

        // Attach a `can_claim` hint per row so the UI can grey out the
        // Claim button on a stale card instead of firing a broken request.
        $applications = $applications->map(function ($app) use ($user) {
            $service = $app->serviceDefinition;
            $stage   = $service?->getStage($app->current_stage ?? '');
            $canClaim = $user->isAdmin()
                || ($stage && ($stage['role'] ?? null) === $user->role);
            $arr = $app->toArray();
            $arr['can_claim'] = $canClaim;
            $arr['current_stage_role'] = $stage['role'] ?? null;
            return $arr;
        });

        return response()->json(['applications' => $applications->values()]);
    }

    // ── Claim ─────────────────────────────────────────────────────────

    public function claim(Request $request, int $id): JsonResponse
    {
        $app    = Application::forOrganization($request->user()->organization_id)->findOrFail($id);
        $engine = new WorkflowEngine($app->serviceDefinition);
        $app    = $engine->claim($app, $request->user());

        return response()->json(['application' => $app]);
    }

    // ── Decide ────────────────────────────────────────────────────────

    public function decide(DecideApplicationRequest $request, int $id): JsonResponse
    {
        $app    = Application::forOrganization($request->user()->organization_id)->findOrFail($id);
        $engine = new WorkflowEngine($app->serviceDefinition);
        $review = $engine->decide(
            $app,
            $request->user(),
            $request->decision,
            $request->notes,
            $request->annotations ?? [],
        );

        return response()->json(['review' => $review, 'application' => $app->fresh()]);
    }

    // ── Confirm payment ───────────────────────────────────────────────

    public function confirmPayment(ConfirmPaymentRequest $request, int $id): JsonResponse
    {
        $app    = Application::forOrganization($request->user()->organization_id)->findOrFail($id);
        $engine = new WorkflowEngine($app->serviceDefinition);
        $app    = $engine->confirmPayment($app, $request->user(), $request->payment_reference);

        return response()->json(['application' => $app]);
    }

    // ── Issue certificate ─────────────────────────────────────────────

    public function issueCertificate(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin() && ! $request->user()->isStaff()) {
            abort(403, 'المسؤولون والموظفون فقط يمكنهم إصدار الشهادات.');
        }

        $app    = Application::forOrganization($request->user()->organization_id)->findOrFail($id);
        $engine = new WorkflowEngine($app->serviceDefinition);
        $cert   = $engine->issueCertificate($app, $request->user());

        return response()->json(['certificate' => $cert, 'application' => $app->fresh()]);
    }

    // ── Public certificate verification ───────────────────────────────

    public function verifyCertificate(string $certNumber): JsonResponse
    {
        $cert = Certificate::with([
            'application.serviceDefinition:id,name_ar,name_en',
            'issuedTo:id,name',
        ])->where('certificate_number', $certNumber)->firstOrFail();

        return response()->json([
            'valid'              => $cert->status === 'active',
            'certificate_number' => $cert->certificate_number,
            'issued_to'          => $cert->issuedTo->name,
            'service'            => $cert->application->serviceDefinition->name_ar,
            'issued_date'        => $cert->issued_date,
            'expiry_date'        => $cert->expiry_date,
            'status'             => $cert->status,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────

    /**
     * P-5: Always scope by organization_id.
     * Applicants additionally scoped to their own applications.
     */
    private function findAccessible(Request $request, int $id): Application
    {
        $query = Application::forOrganization($request->user()->organization_id);

        if ($request->user()->isApplicant()) {
            $query->where('applicant_id', $request->user()->id);
        }

        return $query->with(['serviceDefinition', 'documents'])->findOrFail($id);
    }
}
