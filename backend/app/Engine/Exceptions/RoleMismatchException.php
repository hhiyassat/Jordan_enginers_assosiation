<?php

declare(strict_types=1);

namespace App\Engine\Exceptions;

/**
 * The actor's role doesn't match the role the current stage requires.
 * Maps to HTTP 403 with structured hints so the client can render a
 * useful message ("this stage is for auditors, not staff").
 */
class RoleMismatchException extends WorkflowException
{
    public function __construct(
        string $message,
        public readonly ?string $stageId = null,
        public readonly ?string $requiredRole = null,
    ) {
        parent::__construct($message);
    }

    public function httpStatus(): int { return 403; }

    public function extraBody(): array
    {
        return array_filter([
            'error'               => 'wrong_role_for_stage',
            'stage_id'            => $this->stageId,
            'stage_role_required' => $this->requiredRole,
        ], fn ($v) => $v !== null);
    }
}
