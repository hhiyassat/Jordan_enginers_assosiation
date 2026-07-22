<?php

namespace Plugins\Captcha\Http\Middleware;

use Plugins\Captcha\Services\CaptchaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * VerifyCaptcha
 *
 * Guards a route by requiring a valid captcha_id + captcha_answer pair
 * in the request body. The challenge is single-use — verify() deletes
 * the cache entry regardless of outcome, so replays fail.
 *
 * Toggle via CAPTCHA_ENABLED env (default true). When disabled, the
 * middleware short-circuits and the request passes through — useful
 * for local dev and automated tests without patching every fixture.
 *
 * Wire on a route:
 *   Route::post('auth/login', [...])->middleware('captcha');
 */
class VerifyCaptcha
{
    public function __construct(private CaptchaService $captcha) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('esp.captcha_enabled', true)) {
            return $next($request);
        }

        $id     = $request->input('captcha_id');
        $answer = $request->input('captcha_answer');

        if (!$this->captcha->verify($id, $answer)) {
            return response()->json([
                'message' => 'رمز التحقق غير صحيح، يرجى المحاولة مرة أخرى.',
                'errors'  => ['captcha_answer' => ['رمز التحقق غير صحيح.']],
                'captcha_failed' => true,
            ], 422);
        }

        return $next($request);
    }
}
