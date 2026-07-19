import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import type { UseQueryOptions } from '@tanstack/react-query';
import { servicesApi } from './services';
import { projectsApi } from './projects';
import { applicationsApi } from './applications';
import { adminApi, type AllApplicationsFilters } from './admin';
import { userManagementApi } from './users';
import { notificationsApi } from './notifications';
import type { ApiError } from './http';
import type { Application, ServiceDefinition, User } from '../types';

/**
 * React Query hooks — one per read endpoint (JORD-33).
 *
 * Keys are namespaced by domain so `queryClient.invalidateQueries(['services'])`
 * clears every services query. Convention:
 *   [domain, kind, ...params]
 *
 * Every hook returns the raw useQuery result — pages destructure
 * `{ data, isPending, error }` themselves. Keeps the surface tiny.
 */

// ── Services ──────────────────────────────────────────────────────────

export function useServices() {
  return useQuery({
    queryKey: ['services', 'list'],
    queryFn:  async () => (await servicesApi.list()).services,
  });
}

export function useService(code: string | undefined) {
  return useQuery({
    queryKey: ['services', 'detail', code],
    queryFn:  async () => (await servicesApi.get(code!)).service,
    enabled: !!code,
  });
}

// ── Projects ──────────────────────────────────────────────────────────

export function useProjects() {
  return useQuery({
    queryKey: ['projects', 'list'],
    queryFn:  async () => (await projectsApi.list()).projects,
  });
}

export function useOfficeQuota() {
  return useQuery({
    queryKey: ['projects', 'quota'],
    queryFn:  () => projectsApi.quota(),
  });
}

// ── Applications ──────────────────────────────────────────────────────

export function useMyApplications() {
  return useQuery({
    queryKey: ['applications', 'mine'],
    queryFn:  async () => (await applicationsApi.list()).applications,
  });
}

export function useApplication(id: number | undefined) {
  return useQuery({
    queryKey: ['applications', 'detail', id],
    queryFn:  () => applicationsApi.get(id!),
    enabled: id !== undefined,
  });
}

export function useReviewQueue() {
  return useQuery({
    queryKey: ['review', 'queue'],
    queryFn:  async () => (await applicationsApi.reviewQueue()).applications,
  });
}

// ── Admin ─────────────────────────────────────────────────────────────

export function useAdminDashboardStats() {
  return useQuery({
    queryKey: ['admin', 'dashboard'],
    queryFn:  async () => (await adminApi.dashboard()).stats,
  });
}

/**
 * JORD-35: paginated + searchable admin applications feed.
 *
 * Every filter change becomes part of the query key, so React Query
 * caches each page separately. Debounce the `q` from the caller side —
 * this hook re-fires whenever filters change, and per-keystroke fires
 * would flood the backend.
 */
export function usePaginatedAdminApplications(
  filters: AllApplicationsFilters,
  options?: Partial<UseQueryOptions<Awaited<ReturnType<typeof adminApi.allApplicationsPaginated>>, ApiError>>,
) {
  return useQuery({
    queryKey: ['admin', 'applications', filters],
    queryFn:  () => adminApi.allApplicationsPaginated(filters),
    placeholderData: (previous) => previous, // smooth page transitions
    ...options,
  });
}

export function useAdminServices() {
  return useQuery({
    queryKey: ['admin', 'services'],
    queryFn:  async () => (await adminApi.listServices()).services,
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

// ── Mutations ────────────────────────────────────────────────────────
// Mutations return a useMutation with cache invalidation baked in so
// callers don't have to think about which keys to invalidate.

export function useCreateApplication() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { service_code: string; data: Record<string, unknown>; project_id?: number }) =>
      applicationsApi.create(vars.service_code, vars.data, vars.project_id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['applications'] }); },
  });
}

export function useSubmitApplication() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => applicationsApi.submit(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['applications'] });
      void qc.invalidateQueries({ queryKey: ['review', 'queue'] });
      void qc.invalidateQueries({ queryKey: ['admin', 'applications'] });
    },
  });
}

export function useClaimApplication() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => applicationsApi.claim(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['review'] });
      void qc.invalidateQueries({ queryKey: ['applications'] });
    },
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

// Re-export TypeScript aliases for consumers so pages don't have to
// import them from `../types` on top of `../api/hooks`.
export type { Application, ServiceDefinition, User };
