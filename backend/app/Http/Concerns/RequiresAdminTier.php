<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use Illuminate\Http\Request;

/**
 * Admin-tier guard shared by AdminDashboardController and
 * AiSchemaController (Workstream 5C extraction).
 *
 * Both admin and superuser pass. The distinction between the two roles
 * lives in UserManagementController (only admin can create other
 * admins; superuser is user-management-only per project memory).
 *
 * Extracted from AdminController's private requireAdmin() so the two
 * new controllers share the exact same 403 envelope. When the
 * AdminController shell is finally removed, only this trait remains.
 */
trait RequiresAdminTier
{
    protected function requireAdminTier(Request $request): void
    {
        if (! $request->user()->canEditServices()) {
            abort(403, 'المسؤولون والمستخدم الأعلى فقط يمكنهم الوصول لهذه الوظيفة.');
        }
    }
}
