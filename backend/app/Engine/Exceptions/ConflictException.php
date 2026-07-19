<?php

declare(strict_types=1);

namespace App\Engine\Exceptions;

/**
 * A race or concurrent modification blocked the operation — the row was
 * deleted, someone else claimed it, or the status changed while we
 * were racing for the lock. Maps to HTTP 409.
 */
class ConflictException extends WorkflowException
{
    public function httpStatus(): int { return 409; }
}
