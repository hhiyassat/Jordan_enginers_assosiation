<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Application;
use App\Models\Sanction;
use App\Models\SupervisionTransfer;
use App\Models\User;

/**
 * SupervisionTransferService — JORD-83
 *
 * Owns the write path for the C-07 supervision-transfer queue.
 * Called from ComplaintController when a blocking sanction is issued
 * against an office; scans that office's active supervision
 * contracts and opens one transfer row per app.
 *
 * "Active supervision contract" means an application that is:
 *   • STATUS_APPROVED (still open — not yet certificate_issued
 *     which is terminal), and
 *   • Belongs to a JEA-PROJ service (drawings services that carry
 *     the supervision_services_agreement doc per JORD-54).
 * Non-drawings services (CERT-*, MSC-*, etc.) don't have ongoing
 * supervision so they aren't in scope.
 *
 * Idempotent — the application_id unique constraint on
 * supervision_transfers means a re-call for the same office
 * doesn't duplicate rows.
 */
class SupervisionTransferService
{
    /**
     * @return int  Number of transfers opened.
     */
    public function openTransfersFor(User $office, ?Sanction $sanction = null): int
    {
        // Only long-suspension + deregistration warrant the transfer
        // machinery. A 1yr suspension is short enough that supervision
        // can pause; a warning obviously doesn't apply.
        if ($sanction && !$this->sanctionRequiresTransfer($sanction)) {
            return 0;
        }

        $count = 0;
        Application::where('applicant_id', $office->id)
            ->where('status', Application::STATUS_APPROVED)
            ->whereHas('serviceDefinition', function ($q) {
                $q->where('parent_code', 'JEA-PROJ');
            })
            ->chunkById(100, function ($apps) use ($office, $sanction, &$count) {
                foreach ($apps as $app) {
                    $transfer = SupervisionTransfer::firstOrCreate(
                        ['application_id' => $app->id],
                        [
                            'organization_id'         => $app->organization_id,
                            'source_office_user_id'   => $office->id,
                            'triggering_sanction_id'  => $sanction?->id,
                            'status'                  => SupervisionTransfer::STATUS_PENDING,
                            'fee_waived'              => true,   // C-07 free tier per manual
                        ],
                    );
                    if ($transfer->wasRecentlyCreated) $count++;
                }
            });

        return $count;
    }

    /**
     * Whether a given sanction kind triggers transfer processing.
     * Public so ComplaintController can predicate the call cleanly.
     */
    public function sanctionRequiresTransfer(Sanction $sanction): bool
    {
        return in_array($sanction->kind, [
            Sanction::KIND_SUSPENSION_2YR,
            Sanction::KIND_DEREGISTRATION,
        ], true);
    }
}
