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
use App\Models\ApplicationReview;
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
        //
        // Certificate select includes `qr_token` so the frontend can build
        // the signed PDF download URL inline (see MyApplications row).
        // The token is only exposed to the applicant themselves — the
        // applicant-scoped where-clause below is what makes that safe.
        // JORD-62: eager-load `reviews` (just id + decision + created_at)
        // so the supervision_expiry / output_validity_expiry accessors
        // (in $appends on Application) can read from an already-loaded
        // collection instead of firing one extra query per row.
        // `parent_code` is also required — the supervision accessor
        // gates on parent_code === 'JEA-PROJ'; omitting it silently
        // returned null for every drawing app on the list endpoint.
        $query = Application::forOrganization($request->user()->organization_id)
            ->with([
                'serviceDefinition:id,code,parent_code,name_ar,name_en,schema',
                'certificate:id,application_id,certificate_number,qr_token,status',
                'reviews:id,application_id,decision,created_at',
            ])
            ->orderByDesc('created_at');

        // Applicants see only their own; staff/admin see all
        if ($request->user()->isApplicant()) {
            $query->where('applicant_id', $request->user()->id);
        }

        $applications = $query->get()->map(function ($app) {
            $arr = $app->toArray();
            $arr['certificate_pdf_url'] = $app->certificate
                ? url("/api/v1/certificates/{$app->certificate->certificate_number}/pdf?token={$app->certificate->qr_token}")
                : null;
            return $arr;
        });

        return response()->json(['applications' => $applications]);
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

        // Attach a signed PDF download URL when the certificate exists.
        // The token stays on the server → we return it in the URL so the
        // applicant's browser can navigate directly to download without
        // needing a session. Anyone with the URL can download — same
        // security posture as the QR on the printed certificate.
        $certificatePdfUrl = null;
        if ($app->certificate) {
            $certificatePdfUrl = url("/api/v1/certificates/{$app->certificate->certificate_number}/pdf?token={$app->certificate->qr_token}");
        }

        // JORD-65: itemized fee breakdown (base + surcharges + total).
        // Live-computed from the app's stored form data + service fee
        // schema; safe because the calculator is stateless and cheap.
        // Frontend uses this to render the fee-preview line items on
        // the review / summary screen. Returns null when the service
        // has no fee config or the calc throws (a bad schema shouldn't
        // 500 the whole show response — the applicant needs to see
        // documents / status even if the fee is misauthored).
        $feeBreakdown = null;
        if ($service instanceof \App\Models\ServiceDefinition) {
            try {
                $feeBreakdown = (new \App\Engine\FeeCalculator($service))
                    ->calculateBreakdown(is_array($app->data) ? $app->data : []);

                // JORD-72: overflow surcharge for per-project-cap breach.
                // Computed live from the application (not the schema)
                // because the cap lives on OfficeCeiling and depends on
                // the applicant's office+discipline, not the service.
                // Appended AFTER the schema surcharges so it renders
                // last in the itemized preview.
                $overflow = app(\App\Engine\QuotaLedger::class)->overflowSurchargeFor($app);
                if ($overflow !== null) {
                    $feeBreakdown['surcharges'][] = $overflow;
                    $feeBreakdown['total'] = round(
                        (float) $feeBreakdown['total'] + (float) $overflow['amount'],
                        2,
                    );
                }
            } catch (\Throwable) {
                $feeBreakdown = null;
            }
        }

        return response()->json([
            'application'         => $app,
            'available_actions'   => $available,
            'certificate_pdf_url' => $certificatePdfUrl,
            'fee_breakdown'       => $feeBreakdown,
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
        $projectId  = null;
        // JORD-14: applications inherit the project's contract_no at
        // create time. Prior to this, contract_no lived only on the
        // Project row and the applicant couldn't see it on their
        // application detail without cross-referencing.
        $contractNo = null;
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
            $projectId  = $project->id;
            $contractNo = $project->contract_no;
        }

        $fee = (new FeeCalculator($service))->calculate($request->data);

        $app = Application::create([
            'reference_number'      => Application::generateReference($service),
            'contract_no'           => $contractNo,
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

        // JORD-69: capacity gate — engineer's yearly discipline quota AND
        // the office's yearly ceiling must have room for this submission's
        // area_m2. Only fires on services that declare an area_m2 field;
        // returns [] (pass-through) for everything else.
        $capacityErrors = app(\App\Engine\CapacityGuard::class)->validate($app);
        if ($capacityErrors) {
            return response()->json([
                'message' => 'الرصيد الهندسي غير كافٍ. يرجى مراجعة الحصة والسقف السنوي.',
                'errors'  => $capacityErrors,
            ], 422);
        }

        // JORD-81: sanction gate — an office with an active blocking
        // sanction (suspension_1yr / suspension_2yr / deregistration)
        // cannot submit ANY application until the sanction lapses.
        // Fires last so field / doc / capacity issues surface first
        // (fixable in-place), and the sanction message is a hard stop.
        $sanctionErrors = app(\App\Engine\SanctionGuard::class)->validate($app);
        if ($sanctionErrors) {
            return response()->json([
                'message' => 'لا يمكن تقديم الطلب بسبب عقوبة تأديبية نافذة على المكتب.',
                'errors'  => $sanctionErrors,
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

    // Workstream 5B: reviewer queue + review dashboard + claim + decide
    // + confirmPayment + issueCertificate + verifyCertificate +
    // downloadCertificatePdf moved to purpose-built controllers.
    // Routes updated to match. ApplicationController now owns only the
    // JEA application-lifecycle CRUD (index, show, store, update,
    // submit, uploadDocument).

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
