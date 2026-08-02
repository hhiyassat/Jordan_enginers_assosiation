<?php

declare(strict_types=1);

namespace Modules\JeaServices\Governance;

/**
 * TD-03 · Dispatch registry for typed-decision service submission policies.
 *
 * Parallel to `ServiceSubmissionGuardRegistry` (legacy shape returning
 * error arrays). This registry dispatches to a `ServiceSubmissionPolicy`
 * (SG-05 contract) that returns a typed `ServiceSubmissionDecision`.
 *
 * Controllers look up a policy by service code via `forService()`; when
 * a policy IS registered the controller routes through the transactional
 * `SubmitApplicationUseCase`. When no policy is registered the
 * controller falls back to the legacy `ServiceSubmissionGuardRegistry`
 * path — no change to behaviour of unmigrated services.
 *
 * The lookup is service-code-agnostic — the controller never contains
 * `if service_code === 'SRV-001'`. Any service that registers a policy
 * uses the new runtime path.
 */
final class ServiceSubmissionPolicyRegistry
{
    /** @param array<string, ServiceSubmissionPolicy> $policies */
    public function __construct(private readonly array $policies = [])
    {
    }

    public function forService(string $serviceCode): ?ServiceSubmissionPolicy
    {
        return $this->policies[$serviceCode] ?? null;
    }

    /** @return list<string> */
    public function registeredCodes(): array
    {
        return array_keys($this->policies);
    }
}
