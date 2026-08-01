<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Srv001\Contracts;

/**
 * TD-01 · Port for Department-of-Land-Survey (DLS) parcel lookups.
 *
 * Used by TargetSrv001SubmissionPolicy for:
 *   - Auto-populate governorate/directorate/village/basin/parcel from
 *     DLS Key (Srv001PilotSeeder L297, semantic_status=NEEDS_JEA_API)
 *   - Cadastral-conflict verification against DLS canonical record
 *   - FR-SS-087 QR-code reading of القوشان (registration deed)
 *
 * IMPLEMENTATION STATUS: interface-only in TD-01. No production adapter.
 * Real DLS adapter is BLOCKED_UNTIL_OD-30.
 */
interface DlsLookupPort
{
    /**
     * Fetch parcel details by DLS key. Returns null when key is
     * unknown OR when the port is unavailable.
     */
    public function findByDlsKey(string $dlsKey): ?DlsParcelRecord;

    /**
     * Fetch parcel details by cadastral identity (basin + parcel +
     * optional basin name). Multiple parcels can share basin/parcel
     * pairs across governorates, so the port MAY return the first
     * or a null when disambiguation is needed.
     */
    public function findByCadastralIdentity(
        string $basinNumber,
        string $parcelNumber,
        ?string $basinName = null,
    ): ?DlsParcelRecord;
}
