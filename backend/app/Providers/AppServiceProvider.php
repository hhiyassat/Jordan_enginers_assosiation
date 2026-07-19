<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Gsb\GsbAuthManager;
use App\Services\Gsb\GsbClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * AppServiceProvider
 *
 * Registers application services in the Laravel IoC container.
 * GSB services are bound as singletons so a single OAuth token
 * is shared across all GsbClient calls within a request lifecycle.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── GSB (Government Service Bus) services — MODEE Annex 4.15 ──────
        //
        // GsbAuthManager: singleton so the cached OAuth token is reused across
        // multiple GsbClient calls in the same request. Token is stored in the
        // Laravel cache (Redis/file), not in-memory, so it survives across
        // requests without re-fetching on every call.
        //
        // GsbClient: singleton so the same GsbAuthManager instance is injected
        // everywhere. Resolved lazily — only instantiated if a GSB route is hit.

        // GsbAuthManager reads config('gsb.oauth.*') internally — no constructor args.
        $this->app->singleton(GsbAuthManager::class);

        // GsbClient depends on GsbAuthManager — Laravel resolves it automatically
        // via the type-hinted constructor, but explicit binding makes it a singleton.
        $this->app->singleton(GsbClient::class, function ($app) {
            return new GsbClient(auth: $app->make(GsbAuthManager::class));
        });
    }

    public function boot(): void
    {
        $this->registerRateLimiters();
    }

    /**
     * Named rate limiters used by the route layer.
     *
     * Previous configuration was inline `throttle:5,1` on individual
     * routes. Two problems with that:
     *   • No per-user identity — an authenticated hammer got the same
     *     bucket as anonymous callers behind the same NAT.
     *   • No hooks to record the 429 to the security channel.
     *
     * Every limiter here logs its trip event to log:security so we can
     * see abuse patterns in api_access/security-YYYY-MM-DD.log without
     * digging through per-request 429s in the general access log.
     */
    private function registerRateLimiters(): void
    {
        // Public login attempts — 5/min per IP. Matches the pre-existing
        // throttle:5,1 semantics but adds structured logging.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by((string) $request->ip())
                ->response($this->logHitAndReply('login', $request));
        });

        // Public captcha challenge — issued anonymously; loose ceiling.
        RateLimiter::for('captcha-issue', function (Request $request) {
            return Limit::perMinute(30)->by((string) $request->ip());
        });

        // Public register — 10/min per IP. Captcha is still required in
        // front, so this exists mostly to short-circuit bulk attempts.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(10)
                ->by((string) $request->ip())
                ->response($this->logHitAndReply('register', $request));
        });

        // Nashmi integration webhooks — keyed by IP; Nashmi POSTs from a
        // small pool so 60/min is comfortably above steady-state.
        RateLimiter::for('integration', function (Request $request) {
            return Limit::perMinute(60)->by((string) $request->ip());
        });

        // AI schema generator — the most expensive endpoint we host.
        // Each call spends ~2k+ Claude tokens and takes 10-20s. 10/hour
        // per user is generous for legitimate authoring but crushes a
        // runaway script. Per-USER (not IP) so a shared office IP still
        // gets fair budgets across seats.
        // Both ai-schema and document-upload sit inside the auth:sanctum
        // route group, so $request->user() is never null by the time this
        // callback runs. If auth ever gets moved off these routes we want
        // the middleware to error loudly rather than silently fall back to
        // an IP bucket that a runaway script could easily reset.
        RateLimiter::for('ai-schema', function (Request $request) {
            return Limit::perHour(10)
                ->by('ai-schema:' . (string) $request->user()->id)
                ->response($this->logHitAndReply('ai-schema', $request));
        });

        // Applicant document uploads — the S3 cost path. 30/minute per
        // user is plenty for legitimate multi-doc submissions, but caps
        // a broken script that would otherwise fill the bucket.
        RateLimiter::for('document-upload', function (Request $request) {
            return Limit::perMinute(30)
                ->by('document-upload:' . (string) $request->user()->id)
                ->response($this->logHitAndReply('document-upload', $request));
        });
    }

    /**
     * Build a 429 responder that also emits a structured log line.
     * Returning a closure means the log fires only on the actual trip,
     * not on every request that consulted the limiter.
     */
    private function logHitAndReply(string $limiterName, Request $request): \Closure
    {
        return function (Request $req, array $headers) use ($limiterName, $request) {
            Log::channel('security')->warning('rate_limit_hit', [
                'limiter' => $limiterName,
                'ip'      => $request->ip(),
                'user_id' => $request->user()?->id,
                'path'    => $request->path(),
                'method'  => $request->method(),
                'ts'      => now()->toIso8601String(),
            ]);
            return response()->json([
                'message' => 'تم تجاوز الحد المسموح من الطلبات. حاول مرة أخرى بعد قليل.',
                'limiter' => $limiterName,
            ], 429, $headers);
        };
    }
}
