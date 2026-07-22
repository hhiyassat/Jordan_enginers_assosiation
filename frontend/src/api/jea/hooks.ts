import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import type { UseQueryOptions } from '@tanstack/react-query';
import { servicesApi } from '../services';
import { projectsApi } from '../projects';
import { applicationsApi } from '../applications';
import { jeaAdminApi } from './admin';
import type { AllApplicationsFilters } from '../platform/admin';
import type { ApiError } from '../http';

/**
 * JEA React Query hooks (Workstream 6 split from api/hooks.ts).
 *
 * Every hook here consumes a JEA-specific api client:
 *   • useServices / useService     — JEA service catalog
 *   • useProjects / useOfficeQuota — JEA projects + office quota
 *   • useMyApplications / useApplication / useReviewQueue
 *   • usePaginatedAdminApplications — JEA app listing (deep-linked by admin dashboard)
 *   • useAdminServices              — JEA service admin list
 *   • useCreateApplication / useSubmitApplication / useClaimApplication
 *
 * The barrel in api/hooks.ts re-exports everything from here + from
 * api/platform/hooks so existing `import { useMyApplications } from '../api/hooks'`
 * calls keep working.
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

// ── Admin (JEA-specific slice) ────────────────────────────────────────

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
  options?: Partial<UseQueryOptions<Awaited<ReturnType<typeof jeaAdminApi.listServices>>, ApiError>>,
) {
  // NOTE: the endpoint itself is served by the platform admin route
  // (`/admin/applications`) but the shape returned is JEA-application-
  // heavy, so the hook is JEA-side to keep the platform hook file
  // free of JEA-specific query keys. Workstream 8 revisits when the
  // paginated admin app list becomes a JEA-module route directly.
  return useQuery({
    queryKey: ['admin', 'applications', filters],
    // The paginated call still lives on the platform admin API since
    // the route is platform-owned; the hook only claims the caching
    // side of the concern.
    queryFn:  async () => {
      const { platformAdminApi } = await import('../platform/admin');
      return platformAdminApi.allApplicationsPaginated(filters);
    },
    placeholderData: (previous) => previous, // smooth page transitions
    ...(options as object),
  });
}

export function useAdminServices() {
  return useQuery({
    queryKey: ['admin', 'services'],
    queryFn:  async () => (await jeaAdminApi.listServices()).services,
  });
}

// ── Mutations (JEA-specific) ─────────────────────────────────────────
// Cache invalidation baked in so callers don't have to think about
// which keys to invalidate.

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
