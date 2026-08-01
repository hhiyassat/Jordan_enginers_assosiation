<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HttpJeaMembershipVerifier — production HTTP driver for JEA membership.
 *
 * C-03 remediation. Reads config from `jea.membership_api.*`:
 *   - base_url       (required in production)
 *   - auth_scheme    ('bearer' | 'basic' | 'header')
 *   - auth_token     (bearer / header value; empty string is a hard fail)
 *   - basic_user     (only used when auth_scheme = basic)
 *   - basic_password (only used when auth_scheme = basic)
 *   - auth_header    (only used when auth_scheme = header, e.g. 'X-JEA-Api-Key')
 *   - timeout        (seconds; default 5)
 *   - retries        (default 2)
 *   - retry_delay_ms (default 200)
 *
 * External contract intentionally treated as `BLOCKED_EXTERNAL_INPUT`:
 * the actual endpoint URL, auth scheme, request field names, and
 * response schema come from JEA. This driver picks a defensible
 * shape that mirrors office-registration-flow.md's stated
 * expectation (POST with `{name, membership_number}`, response
 * `{is_valid: bool, reason_ar?: string}`) and can be adjusted
 * without breaking callers when the real contract lands.
 *
 * Failure semantics (respected by the interface contract):
 *   - Endpoint returns 200 with `is_valid=false` → invalid result.
 *   - Endpoint returns 200 with `is_valid=true`  → valid result.
 *   - Network / auth / non-2xx after retries     → THROW
 *     (caller must treat "cannot determine" as separate from
 *     "definitively not a member").
 */
final class HttpJeaMembershipVerifier implements JeaMembershipVerifier
{
    public function verify(string $engineerName, string $membershipNumber): JeaMembershipResult
    {
        if (trim($engineerName) === '') {
            return JeaMembershipResult::invalid('اسم المهندس مطلوب.');
        }
        if (trim($membershipNumber) === '') {
            return JeaMembershipResult::invalid('رقم عضوية النقابة مطلوب.');
        }

        $config  = (array) config('jea.membership_api', []);
        $baseUrl = trim((string) ($config['base_url'] ?? ''));
        if ($baseUrl === '') {
            throw new \RuntimeException('JEA membership API base_url is not configured.');
        }

        $pending = Http::timeout((int) ($config['timeout'] ?? 5))
            ->retry(
                (int) ($config['retries'] ?? 2),
                (int) ($config['retry_delay_ms'] ?? 200),
                throw: false,
            )
            ->acceptJson()
            ->asJson();

        $pending = match ((string) ($config['auth_scheme'] ?? 'bearer')) {
            'basic'  => $pending->withBasicAuth((string) ($config['basic_user'] ?? ''),
                                                (string) ($config['basic_password'] ?? '')),
            'header' => $pending->withHeaders([
                (string) ($config['auth_header'] ?? 'X-Api-Key') => (string) ($config['auth_token'] ?? ''),
            ]),
            default  => $pending->withToken((string) ($config['auth_token'] ?? '')),
        };

        try {
            $response = $pending->post($baseUrl, [
                'name'              => $engineerName,
                'membership_number' => $membershipNumber,
            ]);
        } catch (ConnectionException $e) {
            Log::channel(config('logging.default'))->error(
                'jea_membership_api.connection_failed',
                ['error_class' => $e::class],
            );
            throw new \RuntimeException('JEA membership API unreachable.');
        }

        if (!$response->successful()) {
            Log::channel(config('logging.default'))->error(
                'jea_membership_api.non_success',
                ['status' => $response->status()],
            );
            throw new \RuntimeException('JEA membership API returned a non-success status.');
        }

        $body = (array) ($response->json() ?? []);
        $isValid = (bool) ($body['is_valid'] ?? false);

        if ($isValid) {
            return JeaMembershipResult::valid();
        }

        $reason = trim((string) ($body['reason_ar'] ?? ''));
        return JeaMembershipResult::invalid(
            $reason !== '' ? $reason : 'المهندس غير مسجل في نقابة المهندسين الأردنيين.',
        );
    }
}
