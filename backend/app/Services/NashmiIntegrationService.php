<?php

namespace App\Services;

use App\Models\IntegrationCycle;
use App\Models\ServiceDefinition;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NashmiIntegrationService
 *
 * Handles all outbound HTTP calls to the Nashmi AI Manager API.
 *
 * Outbound endpoint: POST {base_url}/api/integration/projects/create-from-requirements
 * Auth: X-Integration-Key header (shared secret)
 *
 * Two outbound use-cases:
 *   1. pushService()    — push a ServiceDefinition as a new requirements project
 *   2. notifyCodeDone() — notify Nashmi that code for a cycle is ready for review
 */
class NashmiIntegrationService
{
    private string $baseUrl;
    private string $integrationKey;
    private string $organizationId;
    private int    $timeout;

    public function __construct()
    {
        $this->baseUrl        = config('nashmi.base_url',        'https://nashmi.manager.eqratech.com');
        $this->integrationKey = config('nashmi.integration_key', '');
        $this->organizationId = config('nashmi.organization_id', '1');
        $this->timeout        = config('nashmi.timeout',         30);
    }

    // ── 1. Push a service definition as a project to Nashmi ──────────────────

    public function pushService(ServiceDefinition $service): array
    {
        $pdfContent = $this->generateServiceRequirementsDoc($service);
        $tmpPath    = $this->writeMinimalPdf($pdfContent, 'esp_service_' . $service->id);

        Log::channel('integration')->info('Nashmi push initiated', [
            'service_code' => $service->code,
            'service_name' => $service->name_en,
        ]);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-Integration-Key' => $this->integrationKey,
                    'Accept'            => 'application/json',
                ])
                ->attach(
                    'pdf_file',
                    file_get_contents($tmpPath),
                    'esp_service_' . $service->code . '_requirements.pdf'
                )
                ->post($this->baseUrl . '/api/integration/projects/create-from-requirements', [
                    'organization_id'     => $this->organizationId,
                    'project_name'        => '[ESP] ' . $service->name_en . ' — ' . $service->name_ar,
                    'project_description' => $this->buildServiceDescription($service),
                ]);

            @unlink($tmpPath);

            if ($response->successful()) {
                $data = $response->json();
                Log::channel('integration')->info('Nashmi push success', $data ?? []);
                return ['success' => true, 'data' => $data];
            }

            Log::channel('integration')->error('Nashmi push failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return [
                'success' => false,
                'error'   => $response->json('message') ?? 'Nashmi API error ' . $response->status(),
            ];

        } catch (\Throwable $e) {
            @unlink($tmpPath);
            Log::channel('integration')->error('Nashmi push exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── 2. Notify Nashmi that code is done for a cycle ────────────────────────

    public function notifyCodeDone(IntegrationCycle $cycle, array $codeSummary): array
    {
        $pdfContent = $this->generateCodeDoneDoc($cycle, $codeSummary);
        $tmpPath    = $this->writeMinimalPdf($pdfContent, 'esp_code_done_' . $cycle->id);

        Log::channel('integration')->info('Notifying Nashmi: code done', [
            'cycle_ref' => $cycle->cycle_ref,
        ]);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-Integration-Key' => $this->integrationKey,
                    'Accept'            => 'application/json',
                ])
                ->attach(
                    'pdf_file',
                    file_get_contents($tmpPath),
                    'esp_code_done_' . $cycle->cycle_ref . '.pdf'
                )
                ->post($this->baseUrl . '/api/integration/projects/create-from-requirements', [
                    'organization_id'     => $this->organizationId,
                    'project_name'        => '[ESP CODE DONE] ' . $cycle->service_name . ' — Ready for Review',
                    'project_description' => $this->buildCodeDoneDescription($cycle, $codeSummary),
                ]);

            @unlink($tmpPath);

            if ($response->successful()) {
                $data = $response->json();
                Log::channel('integration')->info('Nashmi notified of code-done', $data ?? []);
                return ['success' => true, 'data' => $data];
            }

            return [
                'success' => false,
                'error'   => $response->json('message') ?? 'Nashmi API error ' . $response->status(),
            ];

        } catch (\Throwable $e) {
            @unlink($tmpPath);
            Log::channel('integration')->error('Nashmi code-done exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Document generators ───────────────────────────────────────────────────

    private function generateServiceRequirementsDoc(ServiceDefinition $service): string
    {
        $now     = now()->format('Y-m-d H:i');
        $nameEn  = preg_replace('/[^\x20-\x7E]/', '', $service->name_en ?? '');
        $descEn  = preg_replace('/[^\x20-\x7E\n]/', '', $service->description_en ?? '');
        $status  = $service->status;

        $fieldCount = count($service->schema['fields'] ?? []);
        $docCount   = count($service->schema['documents'] ?? []);
        $stageCount = count($service->schema['workflow']['stages'] ?? []);

        return <<<TXT
        EQRATECH SERVICES PLATFORM (ESP v2)
        Service Requirements Document — Auto-generated
        Generated: {$now}
        ============================================================

        SERVICE: {$nameEn}
        CODE: {$service->code}
        STATUS: {$status}

        DESCRIPTION (English):
        {$descEn}

        SCHEMA SUMMARY:
        - Form Fields: {$fieldCount}
        - Required Documents: {$docCount}
        - Workflow Stages: {$stageCount}

        ============================================================
        INTEGRATION CONTEXT
        Platform: Eqratech Services Platform (ESP v2)
        Standards: MODEE Annex 4.7 e-Government, WCAG 2.1 AA
        Stack: Laravel 12 + React 18 + TypeScript + SQLite/MySQL
        Language: Arabic (RTL) primary, English secondary
        Auth: Laravel Sanctum RBAC (applicant | staff | auditor | admin)

        TASKS FOR AI PIPELINE (Nashmi → assign to team):
        1. Analyse requirements and finalize user stories
        2. Design API and data model changes
        3. Scaffold additional frontend pages if needed
        4. Define acceptance criteria per MODEE standards

        ============================================================
        END OF REQUIREMENTS DOCUMENT
        TXT;
    }

    private function buildServiceDescription(ServiceDefinition $service): string
    {
        return sprintf(
            'ESP service module: %s (%s). Schema-driven — %d fields, %d docs, %d stages. Auto-generated from ESP service registry on %s.',
            $service->name_en,
            $service->code,
            count($service->schema['fields'] ?? []),
            count($service->schema['documents'] ?? []),
            count($service->schema['workflow']['stages'] ?? []),
            now()->toDateTimeString()
        );
    }

    private function generateCodeDoneDoc(IntegrationCycle $cycle, array $summary): string
    {
        $now       = now()->format('Y-m-d H:i');
        $branch    = $summary['git_branch']    ?? 'main';
        $commit    = $summary['git_commit']    ?? 'N/A';
        $files     = implode(', ', $summary['files_changed']  ?? []);
        $endpoints = implode("\n        ", $summary['api_endpoints']   ?? []);
        $pages     = implode("\n        ", $summary['frontend_pages']  ?? []);
        $tables    = implode(', ', $summary['db_tables']    ?? []);
        $notes     = preg_replace('/[^\x20-\x7E\n]/', '', $summary['notes'] ?? '');

        return <<<TXT
        EQRATECH SERVICES PLATFORM (ESP v2)
        Code Completion Notification
        Generated: {$now}
        ============================================================

        CYCLE REF: {$cycle->cycle_ref}
        SERVICE: {$cycle->service_name}
        STATUS: Code Complete — Awaiting Review/Test/QA

        GIT INFO:
        Branch: {$branch}
        Commit: {$commit}
        Files: {$files}

        ============================================================
        WHAT WAS BUILT

        API ENDPOINTS:
        {$endpoints}

        FRONTEND PAGES:
        {$pages}

        DATABASE TABLES:
        {$tables}

        DEVELOPER NOTES:
        {$notes}

        ============================================================
        REVIEW TASKS FOR NASHMI TEAM:

        REVIEWER:  Code review controllers, services, RBAC, migrations
        TESTER:    Test all API endpoints and frontend flows per role
        QA:        Verify MODEE Annex 4.7, WCAG 2.1 AA, bilingual RTL

        FEEDBACK ENDPOINT (send results back to ESP):
        POST /api/integration/receive-feedback
        Headers: X-Integration-Key: <key>
        Body: { cycle_ref, overall_status, reviewer_notes, tester_notes, qa_notes, action_items, score }

        ============================================================
        END OF CODE COMPLETION NOTIFICATION
        TXT;
    }

    private function buildCodeDoneDescription(IntegrationCycle $cycle, array $summary): string
    {
        return sprintf(
            '[ESP Code Done] %s ready for review. Built: %d API endpoints, %d frontend pages, %d DB tables. Branch: %s. cycle_ref: %s',
            $cycle->service_name,
            count($summary['api_endpoints']  ?? []),
            count($summary['frontend_pages'] ?? []),
            count($summary['db_tables']      ?? []),
            $summary['git_branch']           ?? 'main',
            $cycle->cycle_ref
        );
    }

    // ── PDF writer ────────────────────────────────────────────────────────────

    /**
     * Write a minimal valid PDF wrapping plain text.
     * Returns the temp file path (caller must unlink after use).
     * For production use barryvdh/laravel-dompdf instead.
     */
    private function writeMinimalPdf(string $text, string $prefix): string
    {
        $path = sys_get_temp_dir() . '/' . $prefix . '_' . time() . '.pdf';

        $lines  = explode("\n", wordwrap($text, 90, "\n", false));
        $stream = "BT\n/F1 9 Tf\n50 800 Td\n12 TL\n";
        foreach ($lines as $line) {
            $esc     = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $stream .= "({$esc}) Tj T*\n";
        }
        $stream .= "ET\n";

        $pdf  = "%PDF-1.4\n";
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842]\n/Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
        $pdf .= "4 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n{$stream}endstream\nendobj\n";
        $pdf .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n";
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n9\n%%EOF\n";

        file_put_contents($path, $pdf);
        return $path;
    }
}
