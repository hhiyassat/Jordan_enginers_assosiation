<?php

declare(strict_types=1);

namespace App\Contracts\Applications;

/**
 * ApplicationSnapshot — read-only DTO for cross-module Application access.
 *
 * H-07 (session 3): the JEA `Application` model lives in
 * Modules\JeaServices. Every sibling module (JeaProjects, JeaDiscipline,
 * JeaDues) needs to READ application state without a compile-time
 * dependency on JeaServices. This snapshot carries the fields those
 * consumers use — reference number, org, applicant, status, current
 * stage, fee amount, cadastral keys. If a sibling needs more fields,
 * extend this DTO and rebuild the fixtures it feeds.
 *
 * Every field is snake_case to match the pre-existing Eloquent
 * attribute shape so callers migrate with zero property-name churn.
 */
final readonly class ApplicationSnapshot
{
    /**
     * @param  array<string, mixed>  $data  Free-form JSON `data` field.
     */
    public function __construct(
        public int $id,
        public string $reference_number,
        public int $organization_id,
        public int $service_definition_id,
        public int $applicant_id,
        public string $status,
        public ?string $current_stage,
        public string|float $fee_amount,
        public ?string $basin_number = null,
        public ?string $parcel_number = null,
        public array $data = [],
    ) {}
}
