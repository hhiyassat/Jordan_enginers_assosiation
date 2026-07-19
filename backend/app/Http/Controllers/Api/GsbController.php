<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Gsb\GsbClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * GsbController
 *
 * Handles esp-v2 → GSB interactions under MODEE Annex 4.15 security policy.
 *
 * Routes:
 *   POST /api/v1/gsb/otp/request   — request OTP for citizen data access (§4.5.7)
 *   POST /api/v1/gsb/otp/verify    — verify OTP, issue short-lived session token
 *   GET  /api/v1/gsb/citizen        — lookup citizen data (requires verified OTP)
 *   GET  /api/v1/gsb/audit-logs     — internal — list GSB call logs (admin only)
 */
class GsbController extends Controller
{
    public function __construct(private readonly GsbClient $gsb) {}

    // ── §4.5.7: OTP Flow for Citizen Data ────────────────────────────

    /**
     * Step 1 — Request OTP.
     * Admin provides username + password to trigger OTP delivery via GSB.
     * OTP itself is delivered out-of-band (SMS/email by GSB).
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        try {
            // Ask GSB to send OTP to the registered contact of this user
            $result = $this->gsb->post('/auth/otp/request', [
                'username' => $data['username'],
                // Password goes to GSB — never stored in esp-v2 DB
                'password' => $data['password'],
                'user_id'  => $user->id,
            ], [
                'user_id'         => $user->id,
                'user_identifier' => $user->email,
                'source_ip'       => $request->ip(),
                'service_name'    => 'esp-v2',
                'operation'       => 'otp_request',
            ]);

            return response()->json([
                'message'    => 'رمز التحقق أُرسل — أدخله خلال ' . (config('gsb.citizen_data.otp_ttl') / 60) . ' دقائق',
                'otp_ref'    => $result['otp_ref'] ?? null, // reference ID from GSB
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Step 2 — Verify OTP.
     * If valid, caches a short-lived verification token that authorises
     * citizen data lookups for this user session.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'otp'     => ['required', 'string', 'size:6'],
            'otp_ref' => ['required', 'string'],
        ]);

        $user = $request->user();

        try {
            $result = $this->gsb->post('/auth/otp/verify', [
                'otp'     => $data['otp'],
                'otp_ref' => $data['otp_ref'],
                'user_id' => $user->id,
            ], [
                'user_id'         => $user->id,
                'user_identifier' => $user->email,
                'source_ip'       => $request->ip(),
                'operation'       => 'otp_verify',
            ]);

            if (empty($result['verified'])) {
                return response()->json(['message' => 'رمز التحقق غير صحيح أو انتهت صلاحيته.'], 422);
            }

            // Issue a short-lived OTP session token (§4.4.3 — short expiry)
            $otpToken = Str::random(40);
            $ttl      = config('gsb.citizen_data.otp_ttl', 300);
            cache()->put("gsb_otp_verified:{$user->id}:{$otpToken}", true, $ttl);

            return response()->json([
                'message'     => 'تم التحقق — يمكنك الآن الوصول لبيانات المواطنين',
                'otp_token'   => $otpToken,  // used in subsequent citizen data calls
                'expires_in'  => $ttl,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── Citizen Data Lookup (requires verified OTP) ───────────────────

    /**
     * Citizen data lookup via GSB.
     * Requires three parameters per §4.5 rule 4:
     *   national_id + dob + otp_token
     *
     * §4.5.2 — PII is masked in logs; only returned to authenticated caller.
     * §4.5.7 — endpoint requires verified OTP session.
     */
    public function citizenLookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'national_id' => ['required', 'string', 'regex:/^\d{10}$/'],
            'dob'         => ['required', 'date'],
            'otp_token'   => ['required', 'string'],
        ]);

        $user = $request->user();

        try {
            $citizen = $this->gsb->get('/citizens/lookup', [
                'national_id' => $data['national_id'],
                'dob'         => $data['dob'],
            ], [
                'user_id'         => $user->id,
                'user_identifier' => $user->email,
                'source_ip'       => $request->ip(),
                'service_name'    => 'esp-v2',
                'operation'       => 'citizen_lookup',
            ], $data['otp_token']); // §4.5.7: passes OTP token for citizen endpoint auth

            return response()->json(['citizen' => $citizen]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── §4.9 / §4.5.10: Audit Log Viewer (admin only) ─────────────────

    /**
     * Returns GSB call logs for internal audit review.
     * §4.9.1 — all API requests/responses logged.
     * §4.9.3 — retain minimum 180 days.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $logs = \App\Models\GsbCallLog::query()
            ->when($request->filled('from'),     fn ($q) => $q->where('logged_at', '>=', $request->from))
            ->when($request->filled('to'),       fn ($q) => $q->where('logged_at', '<=', $request->to))
            ->when($request->filled('source_ip'),fn ($q) => $q->where('source_ip', $request->source_ip))
            ->when($request->boolean('citizen_only'), fn ($q) => $q->citizenData())
            ->when($request->boolean('failed_only'),  fn ($q) => $q->failed())
            ->orderByDesc('logged_at')
            ->paginate(50);

        return response()->json($logs);
    }

    // ── Private ────────────────────────────────────────────────────────

    private function requireAdmin(Request $request): void
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Admins only.');
        }
    }
}
