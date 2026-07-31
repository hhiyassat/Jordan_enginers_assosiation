<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentGateway;
use App\Support\SecurityEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\JeaServices\Engine\WorkflowEngine;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Models\PaymentCallback;

/**
 * PaymentCallbackController — CS-03.
 *
 * Single webhook entry point. Provider-neutral: sits behind
 * `PaymentGateway::verifyCallback()`. This is the only place a
 * network-originated event flips `applications.payment_status → paid`.
 * The admin manual path (PaymentsController::confirm) is a separate
 * reconciliation surface that requires an operator's rationale and
 * cannot be used as proof-of-payment.
 *
 * Idempotency: enforced via `unique(reference)` on `payment_callbacks`
 * (see 2026_07_31_000020_create_payment_callbacks_table.php). A
 * replayed webhook — even one whose signature verifies — returns 200
 * without a second workflow mutation. The gateway-side signature
 * check runs first so a bogus caller never even reaches the dedup
 * table.
 *
 * Replay window / signature verification is entirely the gateway's
 * responsibility. For test coverage see `SignedTestPaymentGateway`.
 * For a real provider, a corresponding `<Provider>Gateway` class
 * implements `PaymentGateway` and validates whatever HMAC/JWT the
 * provider actually posts.
 */
class PaymentCallbackController extends Controller
{
    public function __construct(private PaymentGateway $gateway) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        // 1) Signature verification — gateway throws on any tamper.
        try {
            $receipt = $this->gateway->verifyCallback($payload);
        } catch (\Throwable $e) {
            Log::channel('security')->warning('payment_callback_verification_failed', [
                'ip'    => $request->ip(),
                'error' => $e->getMessage(),
            ]);
            SecurityEvents::paymentCallbackFailure($request, $e->getMessage());
            return response()->json(['message' => 'Invalid callback.'], 401);
        }

        // 2) Application lookup — the reference on the receipt must
        //    match a previously-initiated payment. Scope-agnostic since
        //    the caller is a gateway, not a signed-in user.
        $application = Application::withoutOrgScope()
            ->where('payment_reference', $receipt->reference)
            ->first();

        if (! $application) {
            Log::channel('security')->warning('payment_callback_unknown_reference', [
                'reference' => $receipt->reference,
                'ip'        => $request->ip(),
            ]);
            return response()->json(['message' => 'Unknown payment reference.'], 404);
        }

        // 3) Idempotent reconciliation.
        //
        // Use insertOrIgnore rather than create()+catch(QueryException):
        // PostgreSQL aborts the surrounding transaction on any error,
        // so a catch()-then-continue pattern would fail the next
        // Eloquent call inside the same transaction with
        // "current transaction is aborted". insertOrIgnore is the
        // cross-driver-safe primitive for "insert if new, do nothing
        // if key already exists" and returns 1/0 accordingly.
        return DB::transaction(function () use ($application, $receipt) {
            $inserted = PaymentCallback::query()->insertOrIgnore([
                'application_id' => $application->id,
                'reference'      => $receipt->reference,
                'amount'         => $receipt->amount,
                'currency'       => $receipt->currency,
                'settled_at'     => $receipt->settledAt,
                'gateway_meta'   => json_encode($receipt->meta),
                'received_at'    => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            if ($inserted === 0) {
                Log::channel('security')->info('payment_callback_replay_ignored', [
                    'reference'      => $receipt->reference,
                    'application_id' => $application->id,
                ]);
                return response()->json([
                    'message'    => 'Callback already processed.',
                    'idempotent' => true,
                    'reference'  => $receipt->reference,
                ], 200);
            }

            $engine  = new WorkflowEngine($application->serviceDefinition);
            $actor   = $application->applicant;
            if (! $actor) {
                // A gateway callback for an application with no applicant
                // is a data-integrity bug, not a user error. Persist the
                // receipt (already done above) so operators can reconcile,
                // and return 500 so the gateway retries per its own policy.
                Log::channel('security')->error('payment_callback_missing_applicant', [
                    'reference'      => $receipt->reference,
                    'application_id' => $application->id,
                ]);
                return response()->json(['message' => 'Application applicant missing.'], 500);
            }

            $updated = $engine->confirmPaymentFromReceipt($application, $actor, $receipt);

            return response()->json([
                'message'        => 'Payment confirmed.',
                'reference'      => $receipt->reference,
                'application_id' => $updated->id,
                'payment_status' => $updated->payment_status,
                'idempotent'     => false,
            ], 200);
        });
    }
}
