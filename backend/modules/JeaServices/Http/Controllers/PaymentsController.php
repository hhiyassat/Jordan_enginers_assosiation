<?php

declare(strict_types=1);

namespace Modules\JeaServices\Http\Controllers;

use Modules\JeaServices\Engine\WorkflowEngine;
use App\Http\Controllers\Controller;
use Modules\JeaServices\Http\Requests\ConfirmPaymentRequest;
use Modules\JeaServices\Models\Application;
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
