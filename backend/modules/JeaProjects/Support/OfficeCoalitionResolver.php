<?php

declare(strict_types=1);

namespace Modules\JeaProjects\Support;

use App\Models\User;
use Modules\JeaProjects\Models\OfficeCoalition;
use Modules\JeaProjects\Models\OfficeCoalitionMember;

/**
 * OfficeCoalitionResolver — H-08 lift of the JEA-specific
 * `User::activeCoalition()` accessor out of the Platform User model.
 *
 * Platform code no longer imports any JEA class from
 * `app/Models/User.php`; consumers that need to know the office's
 * active coalition ask this resolver instead. The resolver lives
 * in the module that owns the OfficeCoalition + OfficeCoalitionMember
 * schema, keeping the dependency direction JEA → Platform (a User
 * is a Platform primitive) rather than Platform → JEA.
 */
final class OfficeCoalitionResolver
{
    /**
     * Returns the given office-user's active coalition, or null if the
     * office is standalone.
     *
     * A membership is active iff both the coalition isn't dissolved
     * AND the office hasn't left it.
     */
    public static function activeCoalitionFor(?User $officeUser): ?OfficeCoalition
    {
        if ($officeUser === null) return null;

        $member = OfficeCoalitionMember::where('office_user_id', $officeUser->id)
            ->whereNull('left_at')
            ->latest()
            ->first();

        if (!$member) return null;

        $coalition = $member->coalition;
        return ($coalition && $coalition->isActive()) ? $coalition : null;
    }
}
