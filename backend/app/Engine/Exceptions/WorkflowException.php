<?php

declare(strict_types=1);

namespace App\Engine\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Base exception for every business-rule failure in WorkflowEngine.
 *
 * JORD-5: the engine used to `abort(4xx, ...)` on every rule violation,
 * which is convenient inside a Laravel HTTP request but hard-couples the
 * engine to the HTTP layer. It couldn't be reused from an artisan
 * command, cron job, or queued job without those callers getting an
 * HttpException thrown at them.
 *
 * The engine now `throw`s subclasses of this exception. Laravel's
 * built-in `render()` hook picks up the JsonResponse this class
 * produces — so HTTP callers still see the same JSON shape as before,
 * but non-HTTP callers get a plain PHP exception they can catch by
 * type.
 */
abstract class WorkflowException extends RuntimeException
{
    /** HTTP status the exception maps to when rendered from a controller. */
    abstract public function httpStatus(): int;

    /**
     * Extra fields merged into the JSON response body. Subclasses
     * override this to carry structured hints (e.g. the required role
     * on a wrong-role claim). Default is just the message.
     */
    public function extraBody(): array
    {
        return [];
    }

    /**
     * Laravel calls this when the framework's default handler encounters
     * an exception with a render() method — that means we get automatic
     * HTTP responses without touching app/Exceptions/Handler.php.
     */
    public function render(): JsonResponse
    {
        return response()->json(array_merge(
            ['message' => $this->getMessage()],
            $this->extraBody(),
        ), $this->httpStatus());
    }
}
