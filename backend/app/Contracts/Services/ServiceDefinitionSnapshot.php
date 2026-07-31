<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * ServiceDefinitionSnapshot — provider-neutral DTO for consumers that
 * need read-only access to a JEA service definition without importing
 * the JEA `ServiceDefinition` Eloquent model.
 *
 * H-07: created so `Integrations\Nashmi\Services\NashmiIntegrationService`
 * (and any other outbound integration that pushes catalog data)
 * takes an immutable snapshot instead of a JeaServices class.
 *
 * Property names match the JEA `ServiceDefinition` Eloquent attribute
 * shape (snake_case) so callers previously coded against the model
 * migrate to the DTO with zero rewriting of property accesses.
 */
final readonly class ServiceDefinitionSnapshot
{
    /**
     * @param  array<string, mixed>  $schema  JSON schema shape — opaque
     *                                        to the DTO; callers may
     *                                        introspect fields / documents /
     *                                        workflow keys.
     */
    public function __construct(
        public int $id,
        public int $organization_id,
        public string $code,
        public string $name_ar,
        public string $name_en,
        public ?string $description_ar,
        public ?string $description_en,
        public string $status,
        public array $schema,
    ) {}
}
