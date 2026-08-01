<?php

namespace Integrations\Nashmi\Services;

use Integrations\Nashmi\Models\IntegrationCycle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NashmiIntegrationService
 *
 * Handles the outbound HTTP call to Nashmi's AI Manager API.
 *
 * Outbound endpoint: POST {base_url}/api/integration/projects/create-from-requirements
 * Auth: X-Integration-Key header (shared secret)
 *
 * CS-10 (2026-08-01): the older `pushService()` code-path — which
 * pushed a ServiceDefinitionSnapshot as a new requirements project
 * — was never wired to a route or a caller and is deleted along
 * with its helpers (generateServiceRequirementsDoc,
 * buildServiceDescription). Only the outbound `notifyCodeDone`
 * flow survives, and it is dispatched asynchronously through
 * ProcessNashmiOutboundJob (CS-02).
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

    // ── Notify Nashmi that code is done for a cycle ─────────────────────────

    /**
     * @param  array<string, mixed>  $codeSummary
     * @return array<string, mixed>
     */
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

    // ── Document generator ────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $summary
     */
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

    /**
     * @param  array<string, mixed>  $summary
     */
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
