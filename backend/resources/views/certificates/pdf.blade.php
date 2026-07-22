{{--
    Certificate PDF template.

    Rendered by ApplicationController::downloadCertificatePdf into dompdf.
    Kept intentionally minimal:
      • No external assets — dompdf runs headless in CI and can't reach
        the network.
      • DejaVu Sans is the default dompdf fallback and carries Arabic
        glyphs; no font-shipping required.
      • QR is inlined as an SVG data URL so no separate binary is fetched.

    All strings are literal Arabic + English pairs — the applicant printing
    the certificate keeps both languages on the page. The token used to
    validate the certificate in-app is NOT printed; only the certificate
    number + QR are visible, both of which are safe to expose.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $certificate->certificate_number }}</title>
    <style>
        @page { margin: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            padding: 0;
            color: #1e293b;
        }
        .frame {
            width: 100%;
            box-sizing: border-box;
            padding: 32px 40px;
            border: 8px double #1e40af;
            min-height: 100vh;
            position: relative;
        }
        .header      { text-align: center; margin-bottom: 24px; padding-bottom: 12px; border-bottom: 2px solid #cbd5e1; }
        .header h1   { font-size: 24px; margin: 0 0 4px; color: #1e3a8a; }
        .header h2   { font-size: 14px; margin: 0; color: #64748b; font-weight: normal; }
        .cert-title  { text-align: center; font-size: 20px; margin: 28px 0 4px; color: #0f172a; }
        .cert-sub    { text-align: center; font-size: 12px; color: #475569; margin: 0 0 24px; }
        .kv-table    { width: 100%; border-collapse: collapse; margin: 0 0 20px; }
        .kv-table th { text-align: right; padding: 8px 12px; width: 35%; background: #f1f5f9; font-size: 12px; color: #334155; border: 1px solid #e2e8f0; }
        .kv-table td { padding: 8px 12px; font-size: 13px; border: 1px solid #e2e8f0; }
        .footer      { position: absolute; bottom: 32px; left: 40px; right: 40px; display: table; width: calc(100% - 80px); }
        .footer > *  { display: table-cell; vertical-align: middle; }
        .qr          { text-align: left; width: 130px; }
        .qr img      { width: 120px; height: 120px; }
        .signature   { font-size: 11px; color: #475569; line-height: 1.6; }
        .cert-number { font-family: DejaVu Sans Mono, monospace; letter-spacing: 1px; }
    </style>
</head>
<body>
<div class="frame">

    <div class="header">
        <h1>نقابة المهندسين الأردنيين</h1>
        <h2 dir="ltr">Jordan Engineers Association</h2>
    </div>

    <p class="cert-title">{{ $titleAr }}</p>
    <p class="cert-sub" dir="ltr">{{ $titleEn }}</p>

    <table class="kv-table">
        <tr>
            <th>رقم الشهادة</th>
            <td><span class="cert-number" dir="ltr">{{ $certificate->certificate_number }}</span></td>
        </tr>
        <tr>
            <th>الخدمة</th>
            <td>{{ $service->name_ar }} <span dir="ltr" style="color:#94a3b8">· {{ $service->name_en }}</span></td>
        </tr>
        <tr>
            <th>الصادر إليه</th>
            <td>{{ $issuedTo->name }}</td>
        </tr>
        <tr>
            <th>تاريخ الإصدار</th>
            <td dir="ltr">{{ $certificate->issued_date?->toDateString() }}</td>
        </tr>
        @if ($certificate->expiry_date)
        <tr>
            <th>تاريخ الانتهاء</th>
            <td dir="ltr">{{ $certificate->expiry_date->toDateString() }}</td>
        </tr>
        @endif

        @foreach ($certificate->cert_data ?? [] as $key => $value)
            <tr>
                <th>{{ $fieldLabels[$key] ?? $key }}</th>
                <td>{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="footer">
        <div class="signature">
            <p><strong>الحالة:</strong>
                {{ $certificate->status === 'active' ? 'صالحة · Valid' : 'ملغاة · Revoked' }}
            </p>
            <p>للتحقق: امسح رمز QR أو زر
                <span dir="ltr">/certificates/verify/{{ $certificate->certificate_number }}</span>
            </p>
        </div>
        <div class="qr">
            <img src="data:image/svg+xml;base64,{{ $qrBase64 }}" alt="QR">
        </div>
    </div>

</div>
</body>
</html>
