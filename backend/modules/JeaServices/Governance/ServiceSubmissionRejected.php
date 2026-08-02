<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

use RuntimeException;

/**
 * TD-03 · Thrown by the SRV-001 runtime path when a
 * `ServiceSubmissionPolicy` returns a rejected `ServiceSubmissionDecision`.
 *
 * Thrown inside `DB::transaction(...)` so the surrounding transaction
 * rolls back cleanly (no partial persistence). Caught by the caller
 * (`ApplicationController::submit`) to translate into a 422 JSON
 * response carrying the field-id-keyed error map.
 */
final class ServiceSubmissionRejected extends RuntimeException
{
    /**
     * @param array<string, list<string>>  $errors  field-id keyed
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('SRV-001 submission decision rejected');
    }
}
