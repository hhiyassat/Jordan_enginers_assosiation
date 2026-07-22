import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { platformAdminApi } from './admin';
import { userManagementApi } from '../users';
import { notificationsApi } from '../notifications';

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

// ── User management (superuser) ───────────────────────────────────────

export function useUsers() {
  return useQuery({
    queryKey: ['users', 'list'],
    queryFn:  async () => (await userManagementApi.list()).users,
    // JORD-24: keep the presence dot fresh without a page reload.
    refetchInterval: 30_000,
    staleTime: 15_000,
  });
}

export function useUpdateUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; data: Parameters<typeof userManagementApi.update>[1] }) =>
      userManagementApi.update(vars.id, vars.data),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['users'] }); },
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
