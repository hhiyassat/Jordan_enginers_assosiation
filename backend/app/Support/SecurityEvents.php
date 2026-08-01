<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SecurityEvents — thin emitter for the `security` log channel.
 *
 * P0-E-2 remediation. Every entry has:
 *   - `event`        (short lower_snake_case type, e.g. login_failed)
 *   - `ts`           (ISO-8601 timestamp)
 *   - `ip`           (client IP where a Request is available)
 *   - `request_id`   (correlation ID)
 *   - `user_id`      (nullable — pre-auth failures have none)
 *
 * NEVER call this with passwords, tokens, secrets, session cookies,
 * raw PII payloads, or document content. Pass structured metadata
 * only; caller must redact upstream.
 */
final class SecurityEvents
{
    /**
     * Emit a security-audit event.
     *
     * @param  array<string, mixed>  $context  Additional keys — must not include secrets/PII.
     */
    public static function emit(string $event, ?Request $request = null, array $context = []): void
    {
        $request ??= app()->bound('request') ? request() : null;

        $base = [
            'event'      => $event,
            'ts'         => now()->toIso8601String(),
            'ip'         => $request?->ip(),
            'request_id' => $request?->attributes->get('correlation_id')
                ?? $request?->header('X-Request-Id'),
            'user_id'    => $request?->user()?->id,
        ];

        Log::channel('security')->info($event, array_merge($base, $context));
    }

    public static function loginSuccess(Request $request, int $userId): void
    {
        self::emit('login_success', $request, ['user_id' => $userId]);
    }

    public static function loginFailed(Request $request, string $emailAttempt): void
    {
        // NOTE: we log the attempted-email so operators can spot targeted
        // credential-stuffing on specific accounts. Emails are considered
        // less sensitive than passwords and are the identifier of the
        // account they identify.
        self::emit('login_failed', $request, ['email_attempt' => $emailAttempt]);
    }

    public static function logout(Request $request): void
    {
        self::emit('logout', $request);
    }

    public static function passwordChanged(Request $request, int $userId): void
    {
        self::emit('password_changed', $request, ['user_id' => $userId]);
    }

    public static function tokenRevoked(?Request $request, int $userId, string $reason): void
    {
        self::emit('token_revoked', $request, [
            'user_id' => $userId,
            'reason'  => $reason,
        ]);
    }

    /**
     * @param  list<string>  $rolesRequired  role strings expected by the CheckRole middleware
     */
    public static function authorizationDenied(Request $request, array $rolesRequired): void
    {
        self::emit('authorization_denied', $request, [
            'roles_required' => $rolesRequired,
            'role_actual'    => $request->user()?->role,
            'method'         => $request->method(),
            'path'           => $request->path(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function integrationSignatureFailure(?Request $request, string $reason, array $extra = []): void
    {
        self::emit('integration_signature_failure', $request, array_merge([
            'reason' => $reason,
        ], $extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function paymentCallbackFailure(?Request $request, string $reason, array $extra = []): void
    {
        self::emit('payment_callback_failure', $request, array_merge([
            'reason' => $reason,
        ], $extra));
    }
}
