<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Engine\WorkflowEngine;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Certificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CertificatesController — Workstream 5B extraction from
 * ApplicationController.
 *
 * Owns the JEA certificate lifecycle:
 *   POST /applications/{id}/issue-certificate    (admin/staff)
 *   GET  /certificates/verify/{certNumber}       (public, no auth)
 *   GET  /certificates/{certNumber}/pdf?token=…  (public, HMAC token)
 *
 * All three methods moved verbatim from ApplicationController —
 * behaviour is identical.
 *
 * Tag: SM (JEA certificate lifecycle + JEA workflow engine).
 */
class CertificatesController extends Controller
{
    public function issue(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->isAdmin() && ! $request->user()->isStaff()) {
            abort(403, 'المسؤولون والموظفون فقط يمكنهم إصدار الشهادات.');
        }

        $app    = Application::forOrganization($request->user()->organization_id)->findOrFail($id);
        $engine = new WorkflowEngine($app->serviceDefinition);
        $cert   = $engine->issueCertificate($app, $request->user());

        return response()->json(['certificate' => $cert, 'application' => $app->fresh()]);
    }

    // Public certificate verification — no auth. Any citizen can hit
    // this endpoint with a cert number to check validity.
    public function verify(string $certNumber): JsonResponse
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

    /**
     * GET /api/v1/certificates/{certNumber}/pdf?token=<qr_token>
     *
     * Public. Streams the certificate PDF when the caller presents the
     * exact HMAC qr_token that was recorded when the certificate was
     * issued (SHA-256 over the cert number, keyed by APP_KEY —
     * DATA-005). No account or session required, so applicants can
     * download by clicking a signed URL and third parties can verify by
     * scanning the printed QR.
     *
     * Security notes:
     *   • hash_equals() (not ===) so timing analysis can't leak the token.
     *   • Certificate lookup is by number only; the token check runs
     *     even if the record is missing so the response time for
     *     unknown/known certs matches.
     *   • Revoked certs (status !== 'active') return 410 Gone instead of
     *     silently rendering — the QR is meant to represent a live seal.
     */
    public function downloadPdf(Request $request, string $certNumber): \Illuminate\Http\Response
    {
        $token = (string) $request->query('token', '');
        // Constant-time compare against a dummy string when the row is
        // missing so response timing doesn't leak existence.
        $cert = Certificate::with([
            'application.serviceDefinition:id,name_ar,name_en',
            'issuedTo:id,name',
        ])->where('certificate_number', $certNumber)->first();

        // Timing-safe compare against a fixed-length dummy when the row
        // is missing, so response time is identical for unknown vs known
        // cert numbers.
        $expected = $cert === null ? str_repeat('0', 64) : $cert->qr_token;
        if (! hash_equals($expected, $token) || $cert === null) {
            abort(404, 'الشهادة غير موجودة أو رمز التحقق غير صحيح.');
        }
        if ($cert->status !== 'active') {
            abort(410, 'هذه الشهادة ملغاة.');
        }

        $service = $cert->application?->serviceDefinition;
        $certConfig = $service?->getCertificateConfig() ?? [];
        $titleAr = $certConfig['title_ar'] ?? ($service->name_ar ?? 'شهادة');
        $titleEn = $certConfig['title_en'] ?? ($service->name_en ?? 'Certificate');
        // Human labels for cert_data keys — falls back to the key itself
        // if the schema didn't provide one, so unknown fields still print.
        $fieldLabels = [];
        foreach ($service?->getFields() ?? [] as $field) {
            if (isset($field['id'], $field['label_ar'])) {
                $fieldLabels[$field['id']] = $field['label_ar'];
            }
        }

        // Verify URL points at the PUBLIC verify endpoint so a third
        // party scanning the QR gets the JSON that proves authenticity.
        $verifyUrl = url("/api/v1/certificates/verify/{$cert->certificate_number}");

        // Encode the QR as SVG so dompdf embeds it losslessly + tiny.
        $qrWriter = new \BaconQrCode\Writer(
            new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd(),
            )
        );
        $qrBase64 = base64_encode($qrWriter->writeString($verifyUrl));

        $html = view('certificates.pdf', [
            'certificate' => $cert,
            'service'     => $service,
            'issuedTo'    => $cert->issuedTo,
            'titleAr'     => $titleAr,
            'titleEn'     => $titleEn,
            'fieldLabels' => $fieldLabels,
            'qrBase64'    => $qrBase64,
        ])->render();

        $dompdf = new \Dompdf\Dompdf(new \Dompdf\Options([
            'defaultFont'          => 'DejaVu Sans',
            'isRemoteEnabled'      => false, // no outbound requests from PDF context
            'isHtml5ParserEnabled' => true,
        ]));
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $cert->certificate_number . '.pdf"',
            'Cache-Control'       => 'private, max-age=0, no-cache',
        ]);
    }
}
