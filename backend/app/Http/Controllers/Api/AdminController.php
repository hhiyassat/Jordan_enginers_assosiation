<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\ServiceDefinition;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rules\Password;

/**
 * AdminController
 *
 * FR-014: Dashboard stats.
 * FR-015: User management.
 * FR-016: Audit log access.
 * P-5: All queries scoped by organization_id.
 */
class AdminController extends Controller
{
    private function requireAdmin(Request $request): void
    {
        // Both admin and superuser are "admin-tier" for the endpoints on
        // this controller — the tier-only distinction lives in
        // UserManagementController.
        if (! $request->user()->canEditServices()) {
            abort(403, 'المسؤولون والمستخدم الأعلى فقط يمكنهم الوصول لهذه الوظيفة.');
        }
    }

    public function dashboard(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        return response()->json([
            'stats' => [
                'total_applications'  => Application::forOrganization($orgId)->count(),
                'pending_review'      => Application::forOrganization($orgId)
                    ->where('status', Application::STATUS_SUBMITTED)->count(),
                'under_review'        => Application::forOrganization($orgId)
                    ->where('status', Application::STATUS_UNDER_REVIEW)->count(),
                'approved_today'      => Application::forOrganization($orgId)
                    ->where('status', Application::STATUS_APPROVED)
                    ->whereDate('updated_at', today())->count(),
                'certificates_issued' => Certificate::where('organization_id', $orgId)->count(),
                'active_services'     => ServiceDefinition::where('organization_id', $orgId)
                    ->where('status', 'active')->count(),
                'total_users'         => User::where('organization_id', $orgId)->count(),
            ],
        ]);
    }

    public function listUsers(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $users = User::where('organization_id', $request->user()->organization_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_active', 'created_at']);

        return response()->json(['users' => $users]);
    }

    public function createUser(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers()],
            'role'     => ['required', 'in:applicant,staff,auditor,admin'],
            'phone'    => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            ...$data,
            'organization_id'    => $request->user()->organization_id,
            'password'           => Hash::make($data['password']),
            'must_change_password' => true, // SEC-004: force change on first login
            'password_changed_at'  => null,
        ]);

        return response()->json(['user' => $user], 201);
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $this->requireAdmin($request);

        $user = User::where('organization_id', $request->user()->organization_id)->findOrFail($id);

        $data = $request->validate([
            'name'               => ['sometimes', 'string', 'max:255'],
            'role'               => ['sometimes', 'in:applicant,staff,auditor,admin'],
            'is_active'          => ['sometimes', 'boolean'],
            'must_change_password' => ['sometimes', 'boolean'],
            'password'           => ['sometimes', Password::min(8)->mixedCase()->numbers()],
        ]);

        if (isset($data['password'])) {
            $data['password']            = Hash::make($data['password']);
            $data['must_change_password'] = true;
        }

        $user->update($data);

        return response()->json(['user' => $user]);
    }

    /**
     * JORD-35: server-side pagination + free-text search.
     *
     * Query params:
     *   • status    — exact match on Application.status
     *   • q         — free-text; matches reference_number, applicant name/
     *                 email, service code, service name_ar/name_en.
     *   • page      — 1-indexed page number (Laravel default)
     *   • per_page  — 10 / 20 / 50 (clamped)
     *
     * Search runs as a single UNION-free WHERE with OR clauses; every
     * matched column has a b-tree index (see 2026_07_19 migration on
     * applications.reference_number + applicants.email). q is
     * lowercased on both sides so the match is case-insensitive on
     * MySQL and SQLite alike.
     */
    public function allApplications(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $query = Application::forOrganization($request->user()->organization_id)
            ->with(['serviceDefinition:id,code,name_ar,name_en', 'applicant:id,name,email']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $needle = '%' . strtolower(trim((string) $request->string('q'))) . '%';
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(reference_number) LIKE ?', [$needle])
                  ->orWhereHas('applicant', function ($a) use ($needle) {
                      $a->whereRaw('LOWER(name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$needle]);
                  })
                  ->orWhereHas('serviceDefinition', function ($s) use ($needle) {
                      $s->whereRaw('LOWER(code) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(name_ar) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(name_en) LIKE ?', [$needle]);
                  });
            });
        }

        // per_page is clamped so a malicious ?per_page=100000 can't ask
        // the backend for the whole table.
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(5, min(50, $perPage));

        return response()->json($query->orderByDesc('created_at')->paginate($perPage));
    }

    // ── Schema Generator ──────────────────────────────────────────────

    /**
     * POST /api/v1/admin/services/generate-schema
     *
     * Converts SRS text → ESP v2 JSON schema via Claude API.
     * Applies full Hukm Governance Layer:
     *   1. Blocker detection before generation (Phase 3)
     *   2. RequirementHukmIR Lite extraction for richer context (Phase 2)
     *   3. requirement_source traceability on every schema node (Phase 1)
     *   4. Sahih/Fasid/Batil validation after generation (Phase 4+5)
     *   5. Generation audit record in response (Phase 6)
     *
     * SEC: API key stays server-side — never exposed to the frontend.
     */

    /**
     * Extract plain text from an uploaded SRS file (DOCX or PDF),
     * then delegate to generateSchema() with the extracted text.
     *
     * POST /admin/services/generate-schema-from-file
     * multipart/form-data: srs_file (required), nfr_file (optional), mode, service_code, cycle_id
     *
     * Accepts up to two documents: a functional SRS and an optional Non-Functional Requirements
     * document. Both are extracted, labelled, and concatenated before being sent to Claude.
     */
    public function generateSchemaFromFile(Request $request): JsonResponse
    {
        set_time_limit(180);
        $this->requireAdmin($request);

        $request->validate([
            'srs_file'     => ['required', 'file', 'max:10240', 'mimes:docx,pdf,doc,txt'],
            'nfr_file'     => ['nullable', 'file', 'max:10240', 'mimes:docx,pdf,doc,txt'],
            'mode'         => ['nullable', 'in:azimah,rukhsa'],
            'service_code' => ['nullable', 'string', 'max:20'],
            'cycle_id'     => ['nullable', 'integer'],
        ]);

        $text = $this->extractFileText($request->file('srs_file'));

        // Append NFR document if provided
        if ($request->hasFile('nfr_file')) {
            $nfrText = $this->extractFileText($request->file('nfr_file'));
            if ($nfrText !== '') {
                $text .= "\n\n---\n## المتطلبات غير الوظيفية (NFR)\n\n" . $nfrText;
            }
        }

        $text = trim($text);
        if (strlen($text) < 50) {
            return response()->json([
                'message' => 'لم يتمكن النظام من استخراج نص كافٍ من الملف. تأكد أن الملف يحتوي على نص قابل للقراءة (ليس صور مسحوحة ضوئياً).',
            ], 422);
        }

        // Merge into the existing request so generateSchema() works unchanged
        $request->merge([
            'srs_text' => mb_substr($text, 0, 20000),
        ]);

        return $this->generateSchema($request);
    }

    /**
     * Extract plain text from an uploaded file (DOCX, PDF, or TXT).
     */
    private function extractFileText(\Illuminate\Http\UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext === 'txt') {
            return (string) file_get_contents($file->getRealPath());
        } elseif (in_array($ext, ['docx', 'doc'])) {
            return $this->extractTextFromDocx($file->getRealPath());
        } elseif ($ext === 'pdf') {
            return $this->extractTextFromPdf($file->getRealPath());
        }

        return '';
    }

    /**
     * Extract plain text from a DOCX file using ZipArchive (no extra dependency).
     */
    private function extractTextFromDocx(string $path): string
    {
        if (! class_exists('ZipArchive')) {
            return '';
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $text = '';
        // word/document.xml contains the main body text
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return '';
        }

        // Strip XML tags, decode entities, normalise whitespace
        $text = strip_tags(str_replace(
            ['</w:p>', '</w:tr>', '<w:tab/>'],
            ["\n", "\n", "\t"],
            $xml
        ));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Extract text from PDF using pdftotext (poppler) if available,
     * falling back to raw byte extraction for simple PDFs.
     */
    private function extractTextFromPdf(string $path): string
    {
        // Try pdftotext (requires poppler-utils on server)
        $escaped = escapeshellarg($path);
        $out = shell_exec("pdftotext -layout {$escaped} - 2>/dev/null");
        if (!empty(trim((string) $out))) {
            return trim($out);
        }

        // Fallback: extract readable strings directly from PDF bytes
        $content = file_get_contents($path);
        preg_match_all('/\(([^\)]{3,})\)/', $content, $m);
        $lines = array_filter($m[1], fn($s) => preg_match('/[\p{L}\p{N}]{3,}/u', $s));
        return implode(' ', $lines);
    }

    public function generateSchema(Request $request): JsonResponse
    {
        // Claude API calls can take 25-40 seconds — raise limit above PHP default (30s)
        set_time_limit(120);

        $this->requireAdmin($request);

        $data = $request->validate([
            'srs_text'     => ['required', 'string', 'min:50', 'max:20000'],
            'cycle_id'     => ['nullable', 'integer'],
            'service_code' => ['nullable', 'string', 'max:20'],
            'mode'         => ['nullable', 'in:azimah,rukhsa'],
        ]);

        $apiKey = config('services.anthropic.api_key');
        $model  = config('services.anthropic.model', 'claude-opus-4-8');
        $mode   = $data['mode'] ?? 'azimah';
        $startedAt = now();

        if (empty($apiKey)) {
            return response()->json(['message' => 'ANTHROPIC_API_KEY not configured on server.'], 503);
        }

        // ── Phase 3: Blocker Detection ────────────────────────────────
        $blockers      = $this->detectBlockers($data['srs_text']);
        $fatalBlockers = array_values(array_filter($blockers, fn ($b) => $b['decision'] === 'halt_generation'));

        if (! empty($fatalBlockers) && $mode === 'azimah') {
            return response()->json([
                'message'  => 'التوليد متوقف: يوجد موانع يجب حلها في نص SRS قبل المتابعة.',
                'verdict'  => 'batil',
                'blockers' => $fatalBlockers,
            ], 422);
        }

        // ── Phase 2: RequirementHukmIR Lite Extraction ───────────────
        // Quick Claude pre-pass: extract structured requirements from SRS.
        // The resulting IR is injected as context into the main generation prompt,
        // so Claude can map requirement_source quotes accurately.
        $hukmIR = $this->extractHukmIR($data['srs_text'], $apiKey, $model);
        $hukmIRContext = $hukmIR
            ? "\n\n--- Extracted RequirementHukmIR (use for requirement_source mapping) ---\n"
              . json_encode($hukmIR, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : '';

        // Optionally pull requirements_meta from a Nashmi cycle for richer context
        $cycleContext = '';
        if (! empty($data['cycle_id'])) {
            $cycle = \App\Models\IntegrationCycle::find($data['cycle_id']);
            if ($cycle?->requirements_meta) {
                $cycleContext = "\n\nAdditional context from Nashmi integration cycle #{$cycle->cycle_ref}:\n"
                    . json_encode($cycle->requirements_meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
        }

        $serviceCodeHint = ! empty($data['service_code'])
            ? "Use this service code: {$data['service_code']}"
            : '';

        // ── Phase 1: Schema format with requirement_source on every node ──
        $schemaFormatReference = <<<'FORMAT'
ESP v2 JSON schema with Hukm traceability — produce ONLY valid JSON matching this structure exactly:
{
  "service_code": "STRING like ENG-REG-001",
  "name_ar": "Arabic service name",
  "name_en": "English service name",
  "version": "1.0",
  "requirement_source": {
    "requirement_id": "REQ-SVC-001",
    "section": "SRS §1.1",
    "quote": "exact verbatim quote from the SRS text that justifies this service"
  },
  "workflow": {
    "stages": [{
      "id": "stage_id",
      "label_ar": "...",
      "label_en": "...",
      "role": "staff|auditor|admin",
      "sla_hours": 24,
      "actions": ["approve", "reject", "request_modifications"],
      "requirement_source": {
        "requirement_id": "REQ-WF-001",
        "section": "SRS §X.X",
        "quote": "exact verbatim quote justifying this workflow stage"
      }
    }]
  },
  "fee": {
    "type": "fixed|tiered|formula",
    "amount": 100,
    "currency": "JOD",
    "requirement_source": {
      "requirement_id": "REQ-FEE-001",
      "section": "SRS §X.X",
      "quote": "exact verbatim quote justifying this fee"
    }
  },
  "fields": [{
    "id": "field_id",
    "label_ar": "...",
    "label_en": "...",
    "type": "text|textarea|select|radio|multiselect|checkbox_group|number|date|email",
    "required": true,
    "section": "section_id",
    "options": [{"value": "...", "label_ar": "...", "label_en": "..."}],
    "conditional": {"field": "...", "value": "..."},
    "requirement_source": {
      "requirement_id": "REQ-FORM-001",
      "section": "SRS §X.X",
      "quote": "exact verbatim quote justifying this field"
    }
  }],
  "sections": [{ "id": "...", "label_ar": "...", "label_en": "..." }],
  "documents": [{
    "id": "doc_id",
    "label_ar": "...",
    "label_en": "...",
    "required": true,
    "accept": ["pdf", "jpg"],
    "max_size_mb": 5,
    "acceptance_rule": "clear description of what makes this document acceptable",
    "conditional": {"field": "...", "value": "..."},
    "requirement_source": {
      "requirement_id": "REQ-DOC-001",
      "section": "SRS §X.X",
      "quote": "exact verbatim quote justifying this document requirement"
    }
  }],
  "certificate": {
    "validity_months": 12,
    "title_ar": "...",
    "title_en": "...",
    "fields_on_cert": ["field_id"],
    "requirement_source": {
      "requirement_id": "REQ-CERT-001",
      "section": "SRS §X.X",
      "quote": "exact verbatim quote justifying the certificate"
    }
  }
}

CRITICAL HUKM RULES — all non-waivable:
1. EVERY schema node (field, workflow stage, document, fee, certificate) MUST include requirement_source.
2. requirement_source.quote must be an exact verbatim excerpt from the SRS text — no paraphrasing.
3. A schema node without requirement_source is BATIL — do not generate it.
4. Only generate nodes explicitly justified by the SRS — no assumed or default nodes.
5. Supported field types ONLY: text, textarea, select, radio, multiselect, checkbox_group, number, date, email.
6. select/radio/multiselect fields MUST have a non-empty options array.
7. Every field must reference a valid section id from the sections array.
8. Every workflow stage MUST have role (staff|auditor|admin) and at least one action.
   CRITICAL: workflow stages represent PROCESSING STEPS performed by staff/admin/auditor — NOT application status transitions.
   FORBIDDEN stage IDs: submitted, under_review, approved, rejected, draft, pending — these are application statuses, not stages.
   GOOD stage ID examples: initial_review, compliance_check, technical_review, final_approval, fee_confirmation, director_approval.
   Every stage must have a human role (staff, auditor, or admin) who PERFORMS that step. Terminal states are NOT stages.
9. Documents MUST have an acceptance_rule describing what makes them valid.
10. Fixed fee MUST have an amount value. Formula fee MUST have a description.
11. Return ONLY raw JSON — no markdown fences, no explanations, no text outside the JSON.
FORMAT;

        $modeNote = $mode === 'rukhsa'
            ? "\n\nGeneration Mode: RUKHSA (prototype/demo only). Non-waivable rules still apply: requirement_source on every node, supported field types, valid JSON."
            : "\n\nGeneration Mode: AZIMAH (production standard). All rules strictly enforced.";

        $systemPrompt = "You are an expert e-government service designer for Eqratech, applying Hukm Governance methodology.\n"
            . "Convert service requirement specifications into fully traceable ESP v2 JSON schemas.\n"
            . "Every schema node must be traceable to an explicit SRS requirement via requirement_source.\n\n"
            . $schemaFormatReference
            . $modeNote;

        $userMessage = implode("\n", array_filter([
            "Generate a traceable ESP v2 JSON schema for the following government service.",
            $serviceCodeHint,
            "\n--- SRS / Service Description ---\n" . $data['srs_text'],
            $hukmIRContext,
            $cycleContext,
        ]));

        try {
            $response = Http::timeout(90)
                ->withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $model,
                    'max_tokens' => 8000,
                    'system'     => $systemPrompt,
                    'messages'   => [['role' => 'user', 'content' => $userMessage]],
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json([
                'message' => 'تعذّر الاتصال بخدمة الذكاء الاصطناعي. يرجى المحاولة مجدداً بعد لحظات.',
            ], 503);
        }

        if ($response->failed()) {
            return response()->json([
                'message' => 'Claude API error: ' . ($response->json('error.message') ?? $response->status()),
            ], 502);
        }

        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        // Strip markdown fences if Claude wrapped it
        $cleaned = preg_replace('/^```(?:json)?\n?/m', '', $text);
        $cleaned = preg_replace('/\n?```\s*$/m', '', $cleaned);
        $cleaned = trim($cleaned);

        $schema = json_decode($cleaned, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'message' => 'Claude returned invalid JSON: ' . json_last_error_msg(),
                'raw'     => substr($cleaned, 0, 500),
            ], 422);
        }

        // ── Role normalization: map common LLM synonyms to valid ESP roles ──
        // Claude sometimes generates reviewer/officer/employee/manager instead of staff/auditor/admin.
        $roleMap = [
            'reviewer'        => 'staff',
            'officer'         => 'staff',
            'employee'        => 'staff',
            'clerk'           => 'staff',
            'processor'       => 'staff',
            'handler'         => 'staff',
            'موظف'            => 'staff',
            'مراجع'           => 'staff',
            'مدقق_داخلي'      => 'auditor',
            'inspector'       => 'auditor',
            'auditor_role'    => 'auditor',
            'manager'         => 'admin',
            'supervisor'      => 'admin',
            'director'        => 'admin',
            'administrator'   => 'admin',
            'مدير'            => 'admin',
            'مسؤول'           => 'admin',
        ];
        if (isset($schema['workflow']['stages']) && is_array($schema['workflow']['stages'])) {
            foreach ($schema['workflow']['stages'] as &$stage) {
                $raw = strtolower(trim($stage['role'] ?? ''));
                if (isset($roleMap[$raw])) {
                    $stage['role'] = $roleMap[$raw];
                }
            }
            unset($stage);
        }

        // ── Phase 4+5: Schema Validation + Sahih/Fasid/Batil Verdict ──
        $validation = $this->validateSchema($schema, $mode);

        // ── Phase 6: Generation Audit Record ──────────────────────────
        $audit = $this->buildAuditRecord(
            schema:      $schema,
            hukmIR:      $hukmIR,
            blockers:    $blockers,
            validation:  $validation,
            mode:        $mode,
            model:       $model,
            tokensUsed:  $response->json('usage.output_tokens') ?? 0,
            startedAt:   $startedAt,
        );

        return response()->json([
            'schema'            => $schema,
            'verdict'           => $validation['verdict'],
            'validation_report' => $validation,
            'blockers'          => $blockers,
            'hukm_ir'           => $hukmIR,
            'generation_audit'  => $audit,
            'mode'              => $mode,
            'tokens_used'       => $response->json('usage.output_tokens'),
            'model'             => $model,
        ]);
    }

    // ── Hukm Governance: RequirementHukmIR Lite Extraction ──────────

    /**
     * Phase 2: Quick Claude pre-pass to extract structured RequirementHukmIR
     * from SRS text. This IR is injected into the main schema generation prompt
     * so Claude can populate requirement_source.quote accurately.
     *
     * Uses a smaller, focused prompt — typically 3-5s response time.
     * Returns null on failure (generation continues without IR context).
     */
    private function extractHukmIR(string $srsText, string $apiKey, string $model): ?array
    {
        $systemPrompt = <<<'PROMPT'
You are a requirements analyst. Extract structured RequirementHukmIR from the given SRS text.
Return ONLY a raw JSON array of requirement objects. No markdown, no explanation.

Each requirement object:
{
  "id": "REQ-XXX-001",
  "statement": "one clear sentence describing the requirement",
  "actor": "who performs this action",
  "sabab": "why this requirement exists (purpose)",
  "conditions": ["condition 1", "condition 2"],
  "blockers": ["potential blocker 1"],
  "effects": ["what happens as a result"],
  "quote": "exact verbatim excerpt from SRS that is the primary source for this requirement"
}

Rules:
- Extract only requirements that have a clear actor and effect.
- quote must be verbatim from the SRS text.
- Keep the list concise — 3 to 12 requirements maximum.
- Return ONLY the JSON array.
PROMPT;

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $model,
                    'max_tokens' => 2000,
                    'system'     => $systemPrompt,
                    'messages'   => [['role' => 'user', 'content' => "Extract RequirementHukmIR from this SRS:\n\n{$srsText}"]],
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return null; // Non-fatal — schema generation continues without IR context
        }

        if ($response->failed()) {
            return null;
        }

        $text    = collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode('');
        $cleaned = trim(preg_replace(['/^```(?:json)?\n?/m', '/\n?```\s*$/m'], '', $text));
        $ir      = json_decode($cleaned, true);

        return (json_last_error() === JSON_ERROR_NONE && is_array($ir)) ? $ir : null;
    }

    // ── Hukm Governance: Generation Audit Record ─────────────────────

    /**
     * Phase 6: Build a structured audit record for the generation run.
     * Included in the API response and can be stored later for traceability.
     */
    private function buildAuditRecord(
        array $schema,
        ?array $hukmIR,
        array $blockers,
        array $validation,
        string $mode,
        string $model,
        int $tokensUsed,
        \Illuminate\Support\Carbon $startedAt,
    ): array {
        $fields    = $schema['fields']    ?? [];
        $stages    = $schema['workflow']['stages'] ?? [];
        $documents = $schema['documents'] ?? [];

        // Count how many nodes have requirement_source (traceability coverage)
        $nodes        = array_merge($fields, $stages, $documents);
        $traced       = count(array_filter($nodes, fn ($n) => ! empty($n['requirement_source'])));
        $totalNodes   = count($nodes)
            + (empty($schema['fee'])         ? 0 : 1)
            + (empty($schema['certificate']) ? 0 : 1)
            + 1; // service root
        $tracedTotal  = $traced
            + (empty($schema['fee']['requirement_source'])         ? 0 : 1)
            + (empty($schema['certificate']['requirement_source']) ? 0 : 1)
            + (empty($schema['requirement_source'])                ? 0 : 1);

        $coveragePct = $totalNodes > 0
            ? round(($tracedTotal / $totalNodes) * 100)
            : 0;

        return [
            'generated_at'          => $startedAt->toIso8601String(),
            'duration_seconds'      => now()->diffInSeconds($startedAt),
            'model'                 => $model,
            'mode'                  => $mode,
            'verdict'               => $validation['verdict'],
            'can_publish'           => $validation['can_publish'],
            'tokens_used'           => $tokensUsed,
            'hukm_ir_extracted'     => $hukmIR !== null,
            'hukm_ir_count'         => is_array($hukmIR) ? count($hukmIR) : 0,
            'schema_stats'          => [
                'fields'         => count($fields),
                'workflow_stages'=> count($stages),
                'documents'      => count($documents),
                'has_fee'        => ! empty($schema['fee']),
                'has_certificate'=> ! empty($schema['certificate']),
            ],
            'traceability' => [
                'total_nodes'    => $totalNodes,
                'traced_nodes'   => $tracedTotal,
                'coverage_pct'   => $coveragePct,
                'fully_traced'   => $coveragePct === 100,
            ],
            'blockers_detected'     => count($blockers),
            'fatal_blockers'        => count(array_filter($blockers, fn ($b) => $b['decision'] === 'halt_generation')),
            'validation_issues'     => $validation['total_issues'],
            'batil_nodes'           => $validation['batil_nodes'],
            'fasid_nodes'           => $validation['fasid_nodes'],
        ];
    }

    // ── Hukm Governance: Blocker Detection ───────────────────────────

    /**
     * Phase 3: Detect blockers in SRS text before invoking Claude.
     * Returns blocker objects with severity and resolution guidance.
     * Blockers with decision=halt_generation stop generation in azimah mode.
     */
    private function detectBlockers(string $srsText): array
    {
        $blockers = [];

        // Fee mentioned but no amount or calculation rule
        if (preg_match('/\b(fee|fees|رسوم|رسم|تكلفة|مبلغ)\b/iu', $srsText)
            && ! preg_match('/\d+\s*(JOD|USD|دينار|دولار|%)|\bfree of charge\b|بدون رسوم|مجاناً/iu', $srsText)) {
            $blockers[] = [
                'type'       => 'missing_fee_rule',
                'severity'   => 'high',
                'decision'   => 'halt_generation',
                'message'    => 'SRS mentions fees but does not define amount or calculation rule.',
                'resolution' => 'Define a fixed amount (e.g. "50 JOD"), a formula, or state "free of charge".',
            ];
        }

        // Workflow review/approval mentioned but no reviewer role
        if (preg_match('/\b(review|approval|مراجعة|اعتماد|موافقة)\b/iu', $srsText)
            && ! preg_match('/\b(staff|auditor|admin|موظف|مدقق|مدير|مشرف)\b/iu', $srsText)) {
            $blockers[] = [
                'type'       => 'missing_workflow_actor',
                'severity'   => 'high',
                'decision'   => 'halt_generation',
                'message'    => 'SRS mentions review/approval but does not specify the reviewer role.',
                'resolution' => 'Specify which role performs the review: staff, auditor, or admin.',
            ];
        }

        // Document required but no acceptance criteria
        if (preg_match('/\b(document|attachment|وثيق|مرفق|صورة)\b/iu', $srsText)
            && ! preg_match('/\b(certified|original|valid|صادر|معتمد|أصلي|ساري|لا تزيد|خلال)\b/iu', $srsText)) {
            $blockers[] = [
                'type'       => 'missing_document_acceptance_rule',
                'severity'   => 'medium',
                'decision'   => 'warn',
                'message'    => 'SRS requires documents but does not define acceptance criteria.',
                'resolution' => 'Specify what makes each document acceptable (e.g. "certified copy", "issued within 3 months").',
            ];
        }

        // Certificate mentioned but no output fields specified
        if (preg_match('/\b(certificate|شهادة|وثيقة رسمية|إفادة)\b/iu', $srsText)
            && ! preg_match('/\b(shows|contains|includes|يظهر|يتضمن|يحتوي)\b/iu', $srsText)) {
            $blockers[] = [
                'type'       => 'missing_certificate_fields',
                'severity'   => 'medium',
                'decision'   => 'warn',
                'message'    => 'SRS mentions a certificate but does not specify what data it should display.',
                'resolution' => 'List the fields that should appear on the generated certificate.',
            ];
        }

        // Sensitive data collected without stated purpose
        if (preg_match('/\b(passport|income|medical|criminal|جواز|دخل|طبي|جنائي|صحي)\b/iu', $srsText)
            && ! preg_match('/\b(purpose|for the purpose|لأغراض|بغرض|لغرض)\b/iu', $srsText)) {
            $blockers[] = [
                'type'       => 'sensitive_data_without_purpose',
                'severity'   => 'high',
                'decision'   => 'halt_generation',
                'message'    => 'SRS references sensitive personal data without stating a clear collection purpose.',
                'resolution' => 'Add an explicit purpose statement (e.g. "passport number is collected for identity verification").',
            ];
        }

        // Auto-approval contradicts manual review
        if (preg_match('/\b(auto.?approv|تلقائي|automatic)\b/iu', $srsText)
            && preg_match('/\b(manual review|مراجعة يدوية|مراجعة بشرية)\b/iu', $srsText)) {
            $blockers[] = [
                'type'       => 'contradictory_workflow',
                'severity'   => 'high',
                'decision'   => 'halt_generation',
                'message'    => 'SRS contains contradictory workflow: both auto-approval and manual review are mentioned.',
                'resolution' => 'Clarify whether the workflow is automatic, manual, or conditional.',
            ];
        }

        return $blockers;
    }

    // ── Hukm Governance: Schema Validator ────────────────────────────

    /**
     * Phase 4+5: Validate generated schema against ESP v2 Hukm policy.
     * Returns verdict (sahih/fasid/batil) with categorised issue lists.
     *
     * Batil  = structural or traceability violation → reject, do not publish
     * Fasid  = quality defect, repairable           → repair then re-validate
     * Sahih  = all checks passed                    → publish eligible
     */
    private function validateSchema(array $schema, string $mode = 'azimah'): array
    {
        $batilNodes     = [];
        $fasidNodes     = [];
        $supportedTypes = ['text', 'textarea', 'select', 'radio', 'multiselect', 'checkbox_group', 'number', 'date', 'email'];

        // ── Service root ──────────────────────────────────────────────
        if (empty($schema['requirement_source'])) {
            $batilNodes[] = 'service_root: missing requirement_source';
        }
        if (empty($schema['name_ar'])) {
            $fasidNodes[] = 'service_root: missing name_ar';
        }
        if (empty($schema['service_code'])) {
            $fasidNodes[] = 'service_root: missing service_code';
        }

        // ── Sections (build index for orphan checks) ──────────────────
        $sectionIds = array_column($schema['sections'] ?? [], 'id');

        // ── Fields ────────────────────────────────────────────────────
        $fieldIds = array_column($schema['fields'] ?? [], 'id');
        foreach ($schema['fields'] ?? [] as $field) {
            $id   = $field['id'] ?? 'unknown';
            $type = $field['type'] ?? '';

            if (empty($field['requirement_source'])) {
                $batilNodes[] = "field:{$id}: missing requirement_source";
            }
            if (! in_array($type, $supportedTypes, true)) {
                $batilNodes[] = "field:{$id}: unsupported type '{$type}'";
            }
            if (in_array($type, ['select', 'radio', 'multiselect'], true) && empty($field['options'])) {
                $batilNodes[] = "field:{$id}: type '{$type}' requires options array";
            }
            if (! empty($field['section']) && ! empty($sectionIds) && ! in_array($field['section'], $sectionIds, true)) {
                $batilNodes[] = "field:{$id}: references non-existent section '{$field['section']}'";
            }
            if (empty($field['label_ar']) || empty($field['label_en'])) {
                $fasidNodes[] = "field:{$id}: missing label_ar or label_en";
            }
        }

        // ── Workflow stages ───────────────────────────────────────────
        $validRoles = ['staff', 'auditor', 'admin'];
        foreach ($schema['workflow']['stages'] ?? [] as $stage) {
            $id = $stage['id'] ?? 'unknown';

            if (empty($stage['requirement_source'])) {
                $batilNodes[] = "workflow_stage:{$id}: missing requirement_source";
            }
            if (empty($stage['role']) || ! in_array($stage['role'], $validRoles, true)) {
                $batilNodes[] = "workflow_stage:{$id}: missing or invalid role";
            }
            if (empty($stage['actions'])) {
                $fasidNodes[] = "workflow_stage:{$id}: missing actions/outcomes";
            }
            if (empty($stage['label_ar']) || empty($stage['label_en'])) {
                $fasidNodes[] = "workflow_stage:{$id}: missing label";
            }
        }

        // ── Fee ───────────────────────────────────────────────────────
        if (! empty($schema['fee'])) {
            if (empty($schema['fee']['requirement_source'])) {
                $batilNodes[] = 'fee: missing requirement_source';
            }
            if ($schema['fee']['type'] === 'fixed' && empty($schema['fee']['amount'])) {
                $batilNodes[] = 'fee: fixed type requires amount';
            }
            if (empty($schema['fee']['currency'])) {
                $fasidNodes[] = 'fee: missing currency';
            }
        }

        // ── Documents ─────────────────────────────────────────────────
        foreach ($schema['documents'] ?? [] as $doc) {
            $id = $doc['id'] ?? 'unknown';

            if (empty($doc['requirement_source'])) {
                $batilNodes[] = "document:{$id}: missing requirement_source";
            }
            if (empty($doc['acceptance_rule'])) {
                $fasidNodes[] = "document:{$id}: missing acceptance_rule";
            }
            if (empty($doc['label_ar']) || empty($doc['label_en'])) {
                $fasidNodes[] = "document:{$id}: missing label";
            }
        }

        // ── Certificate ───────────────────────────────────────────────
        if (! empty($schema['certificate'])) {
            if (empty($schema['certificate']['requirement_source'])) {
                $batilNodes[] = 'certificate: missing requirement_source';
            }
            if (empty($schema['certificate']['fields_on_cert'])) {
                $fasidNodes[] = 'certificate: missing fields_on_cert';
            }
            // Orphan check: cert fields must exist in fields array
            foreach ($schema['certificate']['fields_on_cert'] ?? [] as $cf) {
                if (! in_array($cf, $fieldIds, true)) {
                    $batilNodes[] = "certificate: fields_on_cert references unknown field '{$cf}'";
                }
            }
        }

        // ── Verdict ───────────────────────────────────────────────────
        $verdict = match (true) {
            ! empty($batilNodes) => 'batil',
            ! empty($fasidNodes) => 'fasid',
            default              => 'sahih',
        };

        return [
            'verdict'      => $verdict,
            'can_publish'  => $verdict === 'sahih',
            'can_repair'   => $verdict === 'fasid',
            'batil_nodes'  => $batilNodes,
            'fasid_nodes'  => $fasidNodes,
            'total_issues' => count($batilNodes) + count($fasidNodes),
            'mode'         => $mode,
        ];
    }

    // ── Schema Chat Update ────────────────────────────────────────────

    /**
     * POST /api/v1/admin/services/chat-schema
     *
     * Admin describes a change in natural language; Claude applies it to the
     * current schema and returns { updated_schema, explanation, changes[] }.
     *
     * The frontend merges updated_schema into the JSON editor — the admin
     * reviews the diff and saves manually. Nothing is persisted here.
     */
    public function chatUpdateSchema(Request $request): JsonResponse
    {
        set_time_limit(120);

        $this->requireAdmin($request);

        $data = $request->validate([
            'message'        => ['required', 'string', 'min:3', 'max:2000'],
            'current_schema' => ['required', 'array'],
            // Optional: when supplied, the endpoint refuses if the target
            // service is locked. Callers that produce a completely new
            // schema (no target yet) can omit this.
            'service_id'     => ['sometimes', 'integer'],
        ]);

        if (isset($data['service_id'])) {
            $target = ServiceDefinition::where('organization_id', $request->user()->organization_id)
                ->find($data['service_id']);
            if ($target && $target->isLocked()) {
                return response()->json([
                    'error'   => 'service_locked',
                    'message' => 'الخدمة مقفلة للتعديل — يجب فتح قفلها أولاً من قبل مسؤول.',
                    'service_code' => $target->code,
                ], 423);
            }
        }

        $apiKey = config('services.anthropic.api_key');
        $model  = config('services.anthropic.model', 'claude-opus-4-8');

        if (empty($apiKey)) {
            return response()->json(['message' => 'ANTHROPIC_API_KEY not configured on server.'], 503);
        }

        $schemaJson    = json_encode($data['current_schema'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $schemaFormatReference = <<<'FORMAT'
ESP v2 JSON schema structure:
{
  "service_code": "STRING", "name_ar": "...", "name_en": "...", "version": "1.0",
  "workflow": { "stages": [{ "id":"...", "label_ar":"...", "role":"staff|auditor|admin", "sla_hours":24, "actions":["approve","reject","request_modifications"] }] },
  "fee": { "type":"fixed|tiered|formula", "amount":100, "currency":"JOD" },
  "fields": [{ "id":"...", "label_ar":"...", "label_en":"...", "type":"text|textarea|select|radio|multiselect|checkbox_group|number|date|email", "required":true, "section":"section_id", "options":[{"value":"...","label_ar":"...","label_en":"..."}] }],
  "sections": [{ "id":"...", "label_ar":"...", "label_en":"..." }],
  "documents": [{ "id":"...", "label_ar":"...", "label_en":"...", "required":true, "accept":["pdf","jpg"], "max_size_mb":5 }],
  "certificate": { "validity_months":12, "title_ar":"...", "title_en":"...", "fields_on_cert":["field_id"] }
}
FORMAT;

        $systemPrompt = <<<PROMPT
You are an ESP v2 schema editor for Eqratech e-government platform.
The admin will describe a change to make to a service schema.
Apply ONLY the requested change — do not alter anything else.

{$schemaFormatReference}

IMPORTANT: Respond with ONLY a raw JSON object (no markdown fences) in this exact shape:
{
  "updated_schema": { /* the full modified schema */ },
  "explanation": "One sentence in Arabic describing what was changed.",
  "changes": ["Short English bullet point per change made"]
}
PROMPT;

        $userMessage = "Current schema:\n{$schemaJson}\n\nAdmin request: {$data['message']}";

        try {
            $response = Http::timeout(90)
                ->withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $model,
                    'max_tokens' => 8000,
                    'system'     => $systemPrompt,
                    'messages'   => [['role' => 'user', 'content' => $userMessage]],
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json([
                'message' => 'تعذّر الاتصال بخدمة الذكاء الاصطناعي. يرجى المحاولة مجدداً بعد لحظات.',
            ], 503);
        }

        if ($response->failed()) {
            return response()->json([
                'message' => 'Claude API error: ' . ($response->json('error.message') ?? $response->status()),
            ], 502);
        }

        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        $cleaned = preg_replace('/^```(?:json)?\n?/m', '', $text);
        $cleaned = preg_replace('/\n?```\s*$/m', '', $cleaned);
        $cleaned = trim($cleaned);

        $result = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! isset($result['updated_schema'])) {
            return response()->json([
                'message' => 'لم يتمكن الذكاء الاصطناعي من تحليل طلبك. حاول مرة أخرى بصياغة مختلفة.',
                'raw'     => substr($cleaned, 0, 300),
            ], 422);
        }

        return response()->json([
            'updated_schema' => $result['updated_schema'],
            'explanation'    => $result['explanation'] ?? '',
            'changes'        => $result['changes'] ?? [],
            'tokens_used'    => $response->json('usage.output_tokens'),
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $logs = AuditLog::with('user:id,name,email')
            ->where('organization_id', $request->user()->organization_id)
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($logs);
    }
}
