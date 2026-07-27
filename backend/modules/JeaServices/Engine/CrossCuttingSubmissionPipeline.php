<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

use Modules\JeaServices\Models\Application;

/**
 * CrossCuttingSubmissionPipeline — runs every registered
 * CrossCuttingSubmissionGuard for the current application, in order.
 *
 * Returns the FIRST non-empty error array to stop the submission at the
 * first failing gate; the guards themselves decide whether they are in
 * scope for the given application. A guard out of scope returns [] and
 * pipeline continues to the next.
 *
 * ApplicationController::submit invokes this via
 *   app(CrossCuttingSubmissionPipeline::class)->validate($app);
 * between schema validation and the per-service ServiceSubmissionGuardRegistry.
 *
 * The registration order matters — guards defined first are checked first.
 * See JeaServicesServiceProvider::register() for the current chain.
 */
class CrossCuttingSubmissionPipeline
{
    /**
     * @param  list<CrossCuttingSubmissionGuard>  $guards
     */
    public function __construct(private readonly array $guards = []) {}

    /**
     * @return array<string, string>  field-id keyed error messages, [] on pass
     */
    public function validate(Application $app): array
    {
        foreach ($this->guards as $guard) {
            $errors = $guard->validate($app);
            if (! empty($errors)) {
                return $errors;
            }
        }
        return [];
    }

    /** @return list<string>  class names of registered guards, for introspection */
    public function registeredGuards(): array
    {
        return array_map(static fn ($g) => $g::class, $this->guards);
    }
}
