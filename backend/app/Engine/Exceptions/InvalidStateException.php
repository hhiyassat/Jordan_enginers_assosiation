<?php

declare(strict_types=1);

namespace App\Engine\Exceptions;

/**
 * The application isn't in a state that permits the requested operation
 * (e.g. submitting a non-draft, deciding on something not under_review,
 * confirming payment on an unapproved case). Maps to HTTP 422.
 */
class InvalidStateException extends WorkflowException
{
    public function httpStatus(): int { return 422; }
}
