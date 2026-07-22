<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JORD-9: notification inbox endpoints.
 *
 * GET  /api/v1/notifications         — paginated list for the caller
 * GET  /api/v1/notifications/unread-count — cheap count (header bell)
 * POST /api/v1/notifications/{id}/read  — mark one as read
 * POST /api/v1/notifications/read-all   — mark all as read
 *
 * Every endpoint scopes to the authenticated user — a caller can never
 * see another user's inbox, regardless of role.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = max(5, min(50, (int) $request->integer('per_page', 20)));

        $q = Notification::forUser($user)->orderByDesc('created_at');
        if ($request->boolean('unread_only')) {
            $q->unread();
        }

        return response()->json($q->paginate($perPage));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::forUser($request->user())->unread()->count();
        return response()->json(['count' => $count]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $n = Notification::forUser($request->user())->findOrFail($id);
        if ($n->read_at === null) {
            $n->update(['read_at' => now()]);
        }
        return response()->json(['notification' => $n->fresh()]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = Notification::forUser($request->user())
            ->unread()
            ->update(['read_at' => now()]);
        return response()->json(['updated' => $updated]);
    }
}
