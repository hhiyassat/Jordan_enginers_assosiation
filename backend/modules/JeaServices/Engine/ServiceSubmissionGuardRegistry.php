<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

use Modules\JeaServices\Models\Application;

/**
 * ServiceSubmissionGuardRegistry — service_code → ServiceSubmissionGuard.
 *
 * The registry is the ONLY place a service-code string appears in the
 * submission pipeline. ApplicationController stays generic:
 *
 *     $errors = app(ServiceSubmissionGuardRegistry::class)->validate($app);
 *     if ($errors) { ... }
 *
 * A service without a registered guard yields [] (pass-through).
 *
 * Binding lives in the module's service provider so tests and callers
 * can swap the map for fakes:
 *
 *     $this->app->instance(
 *         ServiceSubmissionGuardRegistry::class,
 *         new ServiceSubmissionGuardRegistry(['SRV-001' => new Srv001Guard()]),
 *     );
 */
class ServiceSubmissionGuardRegistry
{
    /**
     * @param  array<string, ServiceSubmissionGuard>  $guards
     *         Keyed by ServiceDefinition::code.
     */
    public function __construct(private readonly array $guards = []) {}

    public function validate(Application $app): array
    {
        $code = $app->serviceDefinition?->code;
        if ($code === null) {
            return []; // orphan application — nothing to guard.
        }
        $guard = $this->guards[$code] ?? null;
        return $guard?->validate($app) ?? [];
    }

    /** @return list<string>  registered service codes */
    public function registeredCodes(): array
    {
        return array_keys($this->guards);
    }
}
