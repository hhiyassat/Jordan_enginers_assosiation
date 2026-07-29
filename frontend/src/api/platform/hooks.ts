import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { platformAdminApi } from './admin';
import { notificationsApi } from '../notifications';

// User hooks moved to entities/user (FSD Task 13); re-exported here so
// existing `import { useUsers } from '../../api/platform/hooks'` (and the
// api/hooks.ts barrel) keep working.
export { useUsers, useUpdateUser } from '../../entities/user/model/hooks';

/**
 * Platform React Query hooks (Workstream 6 split from api/hooks.ts).
 *
 * Every hook here talks to a platform-generic endpoint:
 *   • useAdminDashboardStats — org-wide health tiles
 *   • useUsers               — user roster (superuser)
 *   • useUpdateUser          — user mutation
 *   • useUnreadNotificationCount / useNotifications
 *   • useMarkNotificationRead / useMarkAllNotificationsRead
 *
 * The barrel in api/hooks.ts re-exports everything from here + from
 * api/jea/hooks so existing `import { useUsers } from '../api/hooks'`
 * calls keep working.
 */

// ── Admin ─────────────────────────────────────────────────────────────

export function useAdminDashboardStats() {
  return useQuery({
    queryKey: ['admin', 'dashboard'],
    // JORD-11: return the full payload — stats + by_status + recent.
    // Consumers narrow which slice they need.
    queryFn:  () => platformAdminApi.dashboard(),
  });
}

// ── Notifications (JORD-9) ─────────────────────────────────────────────

/**
 * Header bell counter — polls once a minute so a new notification lands
 * without a full refetch of the whole inbox. staleTime is short (10s)
 * because the counter drives a red dot the user immediately notices.
 */
export function useUnreadNotificationCount() {
  return useQuery({
    queryKey: ['notifications', 'unread-count'],
    queryFn:  () => notificationsApi.unreadCount(),
    staleTime: 10_000,
    refetchInterval: 60_000,
  });
}

/**
 * Paginated inbox for the bell dropdown + the (future) full-inbox page.
 * `unread_only` splits the two use-cases: the dropdown wants only unread
 * for a compact list, the full inbox shows everything.
 */
export function useNotifications(params: { unread_only?: boolean; page?: number; per_page?: number } = {}) {
  return useQuery({
    queryKey: ['notifications', 'list', params],
    queryFn:  () => notificationsApi.list(params),
    staleTime: 10_000,
  });
}

export function useMarkNotificationRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => notificationsApi.markRead(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['notifications'] }); },
  });
}

export function useMarkAllNotificationsRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => notificationsApi.markAllRead(),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['notifications'] }); },
  });
}
