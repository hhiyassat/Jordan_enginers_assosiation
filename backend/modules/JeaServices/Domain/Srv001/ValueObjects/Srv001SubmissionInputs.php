<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\ValueObjects;

/**
 * TD-01 · Typed input snapshot for SRV-001 target-domain evaluation.
 *
 * Immutable value object. Callers assemble it from Application form
 * data before invoking TargetSrv001SubmissionPolicy or any Target*
 * calculator.
 *
 * See docs/architecture/srv001-target-domain/judgment-records/
 *     JDG-TD00-02-readiness-verdict.md for scope authorization.
 *
 * SCOPE: replaces raw `applications.data` array reads inside the target
 * domain. Legacy path (Srv001Guard / LegacySrv001SubmissionPolicy)
 * continues to read the array directly — unchanged.
 *
 * Fields correspond to the pilot form (Srv001PilotSeeder) — this is
 * the *input* shape; derived values live in Srv001DerivedValues.
 */
final class Srv001SubmissionInputs
{
    /**
     * @param string|null                                          $projectSector             خاص | حكومي
     * @param string|null                                          $governorate               governorate code/label
     * @param string|null                                          $basinNumber               cadastral basin (string, leading zeros preserved)
     * @param string|null                                          $parcelNumber              cadastral parcel (string, leading zeros preserved)
     * @param string|null                                          $basinOrLocationName
     * @param string|null                                          $contractOwnerName
     * @param int|null                                             $floorCount                inputs to ExplorationRequirementMatrix
     * @param float|null                                           $floorArea                 largest floor area (m²) — pending BR-CALC-01 per-floor extension (RES-TD00-06)
     * @param float|null                                           $landOrContractArea        (m²)
     * @param float|null                                           $proposedBuiltArea         (m²)
     * @param int|null                                             $actualExplorationPointCount user-entered
     * @param int|null                                             $buildingCount
     * @param bool|null                                            $hasPartialBasement
     * @param float|null                                           $basementAreaM2
     * @param float|null                                           $roofAreaM2
     */
    public function __construct(
        public readonly ?string $projectSector,
        public readonly ?string $governorate,
        public readonly ?string $basinNumber,
        public readonly ?string $parcelNumber,
        public readonly ?string $basinOrLocationName,
        public readonly ?string $contractOwnerName,
        public readonly ?int $floorCount,
        public readonly ?float $floorArea,
        public readonly ?float $landOrContractArea,
        public readonly ?float $proposedBuiltArea,
        public readonly ?int $actualExplorationPointCount,
        public readonly ?int $buildingCount,
        public readonly ?bool $hasPartialBasement,
        public readonly ?float $basementAreaM2,
        public readonly ?float $roofAreaM2,
    ) {
    }

    /**
     * Constructor from the raw `applications.data` array shape used by
     * the pilot (Srv001PilotSeeder). Missing / mistyped values become
     * null — the validators inside TargetSrv001SubmissionPolicy raise
     * typed errors.
     *
     * @param array<string, mixed> $data
     */
    public static function fromApplicationData(array $data): self
    {
        return new self(
            projectSector:              self::str($data['project_sector'] ?? null),
            governorate:                self::str($data['governorate'] ?? null),
            basinNumber:                self::str($data['basin_number'] ?? null),
            parcelNumber:               self::str($data['parcel_number'] ?? null),
            basinOrLocationName:        self::str($data['basin_or_location_name'] ?? null),
            contractOwnerName:          self::str($data['contract_owner_name'] ?? null),
            floorCount:                 self::intOrNull($data['floor_count'] ?? null),
            floorArea:                  self::floatOrNull($data['floor_area'] ?? null),
            landOrContractArea:         self::floatOrNull($data['land_or_contract_area'] ?? null),
            proposedBuiltArea:          self::floatOrNull($data['proposed_built_area'] ?? null),
            actualExplorationPointCount: self::intOrNull($data['actual_exploration_point_count'] ?? null),
            buildingCount:              self::intOrNull($data['building_count'] ?? null),
            hasPartialBasement:         self::boolYesNo($data['has_partial_basement'] ?? null),
            basementAreaM2:             self::floatOrNull($data['basement_area_m2'] ?? null),
            roofAreaM2:                 self::floatOrNull($data['roof_area_m2'] ?? null),
        );
    }

    private static function str(mixed $v): ?string
    {
        return is_string($v) && $v !== '' ? $v : null;
    }

    private static function intOrNull(mixed $v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }

    private static function floatOrNull(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    private static function boolYesNo(mixed $v): ?bool
    {
        if ($v === 'yes' || $v === true) return true;
        if ($v === 'no' || $v === false) return false;
        return null;
    }
}
