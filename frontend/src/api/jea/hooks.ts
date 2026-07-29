import { useQuery } from '@tanstack/react-query';
import type { UseQueryOptions } from '@tanstack/react-query';
import { projectsApi } from '../projects';
import { jeaAdminApi } from './admin';
import type { AllApplicationsFilters } from '../platform/admin';
import type { ApiError } from '../../shared/api/http';

// Application hooks moved to entities/application (FSD Task 9); re-exported
// here so existing `import { useMyApplications } from '../../api/jea/hooks'`
// (and the api/hooks.ts barrel) keep working.
export {
  useMyApplications,
  useApplication,
  useReviewQueue,
  useCreateApplication,
  useSubmitApplication,
  useClaimApplication,
} from '../../entities/application/model/hooks';

// Service hooks moved to entities/service (FSD Task 10); re-exported here
// so existing `import { useServices } from '../../api/jea/hooks'` (and the
// api/hooks.ts barrel) keep working.
export { useServices, useService } from '../../entities/service/model/hooks';

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

