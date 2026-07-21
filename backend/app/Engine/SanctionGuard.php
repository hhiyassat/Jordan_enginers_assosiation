<?php

declare(strict_types=1);

namespace App\Engine;

use App\Models\Application;
use App\Models\Sanction;

/**
 * SanctionGuard — JORD-81
 *
 * Submit-time enforcement of active disciplinary sanctions. Runs
 * alongside SchemaValidator + CapacityGuard in
 * ApplicationController::submit. Any submission by an office with
 * an active blocking sanction (suspension_1yr / suspension_2yr /
 * deregistration) is rejected with a 422-shaped error naming the
 * sanction + its expiry.
 *
 * Warnings never block — they're informational.
 * Non-blocking / expired / future-dated sanctions are ignored.
 * Multiple active sanctions → the earliest one wins the message
 * (so the applicant sees "why can't I submit" once, not a list).
 */
class SanctionGuard
{
    /**
     * @return array<string, string>  Empty array = OK.
     */
    public function validate(Application $app): array
    {
        // Deregistrations + suspensions block; warnings don't.
        $active = Sanction::where('office_user_id', $app->applicant_id)
            ->whereIn('kind', [
                Sanction::KIND_SUSPENSION_1YR,
                Sanction::KIND_SUSPENSION_2YR,
                Sanction::KIND_DEREGISTRATION,
            ])
            ->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>', now());
            })
            ->orderBy('effective_from')
            ->first();

        if (!$active) return [];

        $label = match ($active->kind) {
            Sanction::KIND_SUSPENSION_1YR => 'إيقاف لمدة سنة',
            Sanction::KIND_SUSPENSION_2YR => 'إيقاف لمدة سنتين',
            Sanction::KIND_DEREGISTRATION => 'شطب من السجل',
            default                       => 'عقوبة تأديبية',
        };

        $untilPart = $active->effective_until
            ? sprintf(' (حتى %s)', $active->effective_until->format('Y-m-d'))
            : '';

        return [
            'sanction' => sprintf(
                'المكتب موقوف عن التقديم بسبب %s%s. لا يمكن تقديم طلبات جديدة أثناء فترة العقوبة.',
                $label, $untilPart,
            ),
        ];
    }
}
