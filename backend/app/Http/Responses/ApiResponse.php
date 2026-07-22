<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * ApiResponse — Workstream 11.
 *
 * Standard response envelope for every ESP v2 endpoint. Adoption is
 * OPTIONAL for existing endpoints today (Workstream 8 preserved every
 * envelope verbatim so the frontend didn't break). New endpoints
 * SHOULD use this helper; existing endpoints migrate opportunistically
 * during feature work.
 *
 * Envelope shape:
 *   Success (200/201):
 *     { "data": <payload> }
 *   Success paginated:
 *     { "data": <array>, "meta": { current_page, per_page, total, last_page } }
 *   Error (4xx/5xx):
 *     { "error": { "code": "MACHINE_READABLE", "message": "human copy" }, "details": {...}? }
 *
 * Correlation IDs (X-Request-Id) are attached automatically by the
 * CorrelationId middleware — this helper never needs to touch them.
 *
 * Envelope rationale:
 *   • Single top-level key ('data' or 'error') → frontend has ONE
 *     branch to check, not seven per-endpoint shapes.
 *   • Machine-readable error codes → frontend renders localized copy
 *     from a code, not a re-parsed backend message.
 *   • Pagination meta split from data → ergonomic for the SPA and
 *     matches Laravel's paginator shape at row-2 (data + meta).
 */
final class ApiResponse
{
    /**
     * 200 with a payload. Payload may be an array, an Eloquent model,
     * or a collection — Laravel's json() encoding handles all three.
     *
     * @param  mixed  $data
     */
    public static function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    /**
     * 201 Created. Shortcut for ok($data, 201).
     *
     * @param  mixed  $data
     */
    public static function created($data): JsonResponse
    {
        return self::ok($data, 201);
    }

    /**
     * 200 with a Laravel paginator. Splits ->items() into `data` and
     * ->{page,perPage,total,lastPage}() into `meta`. Frontend consumes
     * `data` + `meta` — never touches Laravel's other paginator keys.
     */
    public static function paginated(LengthAwarePaginator $p): JsonResponse
    {
        return response()->json([
            'data' => $p->items(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'per_page'     => $p->perPage(),
                'total'        => $p->total(),
                'last_page'    => $p->lastPage(),
            ],
        ]);
    }

    /**
     * Error response. `code` is machine-readable (SNAKE_CASE); the
     * frontend maps it to localized copy. `message` is a fallback for
     * when the frontend hasn't yet added a mapping — should stay
     * English and short.
     *
     * @param  array<string, mixed>|null  $details  Optional structured payload
     *                                              (e.g. validation errors).
     */
    public static function error(string $code, string $message, int $status = 400, ?array $details = null): JsonResponse
    {
        $body = ['error' => ['code' => $code, 'message' => $message]];
        if ($details !== null) {
            $body['details'] = $details;
        }
        return response()->json($body, $status);
    }
}
