<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

use Illuminate\Support\Collection;
use Modules\JeaServices\Models\Application;
use Modules\JeaServices\Support\ArabicNormalizer;

/**
 * CadastralPriorApplicationLookup — shared helper for CC-001 and CC-002.
 *
 * Both CadastralConflictGuard (different-owner rejection) and
 * OwnerMatchClearanceGuard (same-owner clearance requirement) need the
 * same candidate query: prior applications from OTHER organizations in
 * committed statuses whose cadastral triple normalizes to the current
 * app's triple. Extracted here to avoid two guards issuing duplicate
 * queries on every submission.
 *
 * Callers are responsible for their own error-shaping and for checking
 * owner_name equality on the returned candidates.
 */
final class CadastralPriorApplicationLookup
{
    public const CADASTRAL_FIELD_IDS = [
        'basin_number',
        'parcel_number',
        'basin_or_location_name',
    ];

    /**
     * Application statuses that "commit" a parcel to the applying office.
     * Draft + rejected are excluded — drafts have not committed; rejected
     * doesn't own the parcel.
     */
    public const CONFLICTING_STATUSES = [
        Application::STATUS_SUBMITTED,
        Application::STATUS_UNDER_REVIEW,
        Application::STATUS_MODIFICATIONS_REQUESTED,
        Application::STATUS_APPROVED,
        Application::STATUS_CERTIFICATE_ISSUED,
    ];

    /**
     * True when the app's service schema declares all three cadastral
     * field IDs (i.e. the service is cadastral-scoped and this app's
     * data will have the fields to check).
     */
    public static function isInScope(Application $app): bool
    {
        $service = $app->serviceDefinition;
        if ($service === null) {
            return false;
        }
        $schemaFieldIds = array_column($service->schema['fields'] ?? [], 'id');
        foreach (self::CADASTRAL_FIELD_IDS as $required) {
            if (! in_array($required, $schemaFieldIds, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Extract the cadastral triple from the app's data. Returns null when
     * any component is missing — SchemaValidator will have already caught
     * this and we want to avoid emitting a second error on the same
     * missing field.
     *
     * @return array{basin_number: string, parcel_number: string, basin_or_location_name: string}|null
     */
    public static function extractTriple(Application $app): ?array
    {
        $data = is_array($app->data) ? $app->data : [];
        $basin = $data['basin_number'] ?? null;
        $parcel = $data['parcel_number'] ?? null;
        $basinName = $data['basin_or_location_name'] ?? null;
        if ($basin === null || $parcel === null || $basinName === null
            || $basin === '' || $parcel === '' || $basinName === '') {
            return null;
        }
        return [
            'basin_number'           => (string) $basin,
            'parcel_number'          => (string) $parcel,
            'basin_or_location_name' => (string) $basinName,
        ];
    }

    /**
     * Return prior committed applications from OTHER organizations whose
     * normalized cadastral triple matches this app's triple.
     *
     * IMPORTANT: uses Application::withoutOrgScope() because the
     * OrganizationScope global scope from BelongsToOrganization would
     * auto-filter to the caller's own org under Sanctum context — which
     * hides exactly the cross-org candidates we want to detect.
     *
     * @return Collection<int, Application>
     */
    public static function candidates(Application $app): Collection
    {
        $triple = self::extractTriple($app);
        if ($triple === null) {
            return collect();
        }

        $candidates = Application::withoutOrgScope()
            ->where('organization_id', '!=', $app->organization_id)
            ->whereIn('status', self::CONFLICTING_STATUSES)
            ->where('id', '!=', $app->id)
            ->whereRaw("json_extract(data, '$.basin_number') = ?", [$triple['basin_number']])
            ->whereRaw("json_extract(data, '$.parcel_number') = ?", [$triple['parcel_number']])
            ->get(['id', 'data', 'organization_id']);

        $normalizedName = ArabicNormalizer::normalize($triple['basin_or_location_name']);

        return $candidates->filter(function (Application $c) use ($normalizedName): bool {
            $cData = is_array($c->data) ? $c->data : [];
            $cName = $cData['basin_or_location_name'] ?? null;
            return $cName !== null
                && ArabicNormalizer::normalize($cName) === $normalizedName;
        })->values();
    }

    /**
     * Split candidates into (same-owner, different-owner) buckets given
     * the current app's owner name.
     *
     * @param  Collection<int, Application>  $candidates
     * @return array{same_owner: Collection<int, Application>, different_owner: Collection<int, Application>}
     */
    public static function splitByOwner(Collection $candidates, ?string $currentOwnerName): array
    {
        $normalizedCurrent = ArabicNormalizer::normalize($currentOwnerName ?? '');
        $sameOwner = collect();
        $differentOwner = collect();
        foreach ($candidates as $c) {
            $cData = is_array($c->data) ? $c->data : [];
            $cOwner = ArabicNormalizer::normalize($cData['contract_owner_name'] ?? '');
            // If the current app has no owner name, treat as different (safe default).
            if ($normalizedCurrent !== '' && $cOwner === $normalizedCurrent) {
                $sameOwner->push($c);
            } else {
                $differentOwner->push($c);
            }
        }
        return ['same_owner' => $sameOwner, 'different_owner' => $differentOwner];
    }
}
