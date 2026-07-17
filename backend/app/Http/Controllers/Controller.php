<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Base Controller
 *
 * All controllers inherit from this. Helpers here are shared across every
 * feature so future-added controllers get the same NFR guarantees.
 */
abstract class Controller
{
    /**
     * NFR-002: return the authenticated user's organization_id.
     * Throws 401 if no auth user, 500 if user has no org (data integrity bug).
     */
    protected function orgId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($user->organization_id, 500, 'Authenticated user has no organization_id');
        return $user->organization_id;
    }
}
