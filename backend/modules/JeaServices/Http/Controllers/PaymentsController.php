<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentIntent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\JeaServices\Engine\WorkflowEngine;
use Modules\JeaServices\Http\Requests\ConfirmPaymentRequest;
use Modules\JeaServices\Http\Requests\InitiatePaymentRequest;
use Modules\JeaServices\Models\Application;

/**
 * PaymentsController — JEA payment endpoints.
 *
 * CS-03 (2026-07-31): completed the runtime integration with
 * `App\Services\Payment\PaymentGateway`. Two distinct surfaces:
 *
 * 1. `initiate()` — applicant-triggered. Resolves the gateway from
 *    the container, builds a `PaymentIntent`, hands it to
 *    `PaymentGateway::initiate()`, and persists the gateway's
 *    reference on the application so the callback can be joined
 *    back. Returns the redirect URL the SPA follows.
 *
 * 2. `confirm()` — ADMIN manual reconciliation. Was previously
 *    (mis-)used as a proof-of-payment endpoint that accepted any
 *    string reference from the caller. Rewritten: requires the
 *    admin role, an explicit `manual_reason` string, and writes a
 *    dedicated audit-log entry marked `application.payment_manual_reconciliation`.
 *    NOT usable as proof-of-payment for network-originated events —
 *    those go through PaymentCallbackController + gateway.verifyCallback().
 */
class PaymentsController extends Controller
{
    public function __construct(private PaymentGateway $gateway) {}

    /**
     * POST /applications/{id}/initiate-payment
     *
     * Applicant-triggered. Uses PaymentGateway::initiate().
     */
    public function initiate(InitiatePaymentRequest $request, int $id): JsonResponse
    {
        $app = Application::findForOrganizationOrFail($request->user()->organization_id, $id);

        if ($app->status !== Application::STATUS_APPROVED) {
            return response()->json([
                'message' => 'يمكن بدء الدفع فقط للطلبات الموافق عليها.',
            ], 422);
        }

        if ($app->payment_status === 'paid') {
            return response()->json([
                'message' => 'تم دفع هذا الطلب مسبقًا.',
                'payment_reference' => $app->payment_reference,
            ], 200);
        }

        $intent = new PaymentIntent(
            reference:      'app-' . $app->id,
            organizationId: (int) $app->organization_id,
            amount:         (float) $app->fee_amount,
            currency:       (string) ($app->serviceDefinition->currency ?? 'JOD'),
            description:    'ESP application ' . $app->reference_number,
            callbackUrl:    $request->input('callback_url'),
        );

        $initiation = $this->gateway->initiate($intent);

        DB::transaction(function () use ($app, $initiation, $request) {
            $app->update([
                'payment_reference' => $initiation->reference,
                'payment_status'    => 'pending',
            ]);
            AuditLog::record(
                user:    $request->user(),
                subject: $app,
                action:  'application.payment_initiated',
                extra:   [
                    'rule_id'      => 'ESP-WF-004',
                    'reference'    => $initiation->reference,
                    'amount'       => $initiation->amount,
                    'currency'     => $initiation->currency,
                    'redirect_url' => $initiation->redirectUrl,
                    'gateway_meta' => $initiation->meta,
                ],
            );
        });

        return response()->json([
            'reference'    => $initiation->reference,
            'redirect_url' => $initiation->redirectUrl,
            'amount'       => $initiation->amount,
            'currency'     => $initiation->currency,
        ], 201);
    }

    /**
     * POST /applications/{id}/confirm-payment
     *
     * ADMIN MANUAL RECONCILIATION only. Not usable as proof-of-payment.
     * Requires an explicit `manual_reason` — an audit-log field, not
     * decoration. Any use of this endpoint should be rare (a stuck
     * bank transfer, a gateway outage, a supported wire-transfer).
     */
    public function confirm(ConfirmPaymentRequest $request, int $id): JsonResponse
    {
        $app    = Application::findForOrganizationOrFail($request->user()->organization_id, $id);
        $engine = new WorkflowEngine($app->serviceDefinition);
        $actor  = $request->user();

        $app = $engine->confirmPayment($app, $actor, $request->input('payment_reference'));

        AuditLog::record(
            user:    $actor,
            subject: $app,
            action:  'application.payment_manual_reconciliation',
            extra: [
                'rule_id'           => 'ESP-WF-005-MANUAL',
                'payment_reference' => $request->input('payment_reference'),
                'manual_reason'     => $request->input('manual_reason'),
                'confirmed_by'      => $actor->id,
            ],
        );

        return response()->json(['application' => $app]);
    }
}
