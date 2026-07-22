<?php

declare(strict_types=1);

namespace Plugins\Captcha\Http\Controllers;

use App\Http\Controllers\Controller;
use Plugins\Captcha\Services\CaptchaService;
use Illuminate\Http\JsonResponse;

/**
 * CaptchaController
 *
 * Public (unauthed) endpoint that issues a new captcha challenge.
 * Frontend calls this before any protected form (login, register, etc.),
 * displays the SVG, and includes {captcha_id, captcha_answer} in the
 * submission — VerifyCaptcha middleware validates + consumes the
 * challenge server-side.
 */
class CaptchaController extends Controller
{
    public function issue(CaptchaService $captcha): JsonResponse
    {
        return response()->json($captcha->generate());
    }
}
