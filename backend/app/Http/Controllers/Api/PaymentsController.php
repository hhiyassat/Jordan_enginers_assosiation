<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Engine\WorkflowEngine;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmPaymentRequest;
use App\Models\Application;
use Illuminate\Http\JsonResponse;

/**
 * PaymentsController — Workstream 5B extraction from
 * ApplicationController.
 *
 * Owns the JEA "confirm payment" endpoint:
 *   POST /applications/{id}/confirm-payment
 *
 * Method moved verbatim from ApplicationController — behaviour is
 * identical. Once Workstream 8 lifts JEA controllers into
 * modules/jea-services/, this file moves along with the rest of the
 * JEA billing / payment surface.
 *
 * Tag: SM (JEA-specific payment lifecycle).
 */
class PaymentsController extends Controller
{
    public function confirm(ConfirmPaymentRequest $request, int $id): JsonResponse
    {
        $app    = Application::forOrganization($request->user()->organization_id)->findOrFail($id);
        $engine = new WorkflowEngine($app->serviceDefinition);
        $app    = $engine->confirmPayment($app, $request->user(), $request->payment_reference);

        return response()->json(['application' => $app]);
    }
}
