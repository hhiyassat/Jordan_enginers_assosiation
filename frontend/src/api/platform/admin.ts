import type { Application, DashboardStats, User } from '../../types';
import { request } from '../http';

/**
 * Platform admin API (Workstream 6 split from api/admin.ts).
 *
 * Just the endpoints that survive on a non-JEA tenant:
 *   • dashboard        — org-wide health tiles
 *   • allApplications  — legacy status-filtered list
 *   • allApplicationsPaginated — server-side pagination + search
 *   • auditLogs        — the platform audit trail
 *   • listUsers / createUser / updateUser — legacy user CRUD
 *     (real user management lives in api/users.ts;
 *     these three still map to /admin/users routes for callers
 *     that predate api/users.ts.)
 *
 * The legacy `adminApi` barrel in api/admin.ts re-exports `{ ...platformAdminApi,
 * ...jeaAdminApi }` so nothing importing `adminApi.foo` needs to move.
 */

/**
 * Laravel paginator envelope — matches ->paginate()'s top-level shape.
 * Kept in the platform namespace because pagination is platform-generic.
 */
export interface Paginated<T> {
  data: T[];
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
}

export interface AllApplicationsFilters {
  status?: string;
  /** Free-text search — matches applicant name, reference number, service name. */
  q?: string;
  page?: number;
  per_page?: number;
}

function toQuery(filters: AllApplicationsFilters): string {
  const params = new URLSearchParams();
  if (filters.status)   params.set('status', filters.status);
  if (filters.q)        params.set('q', filters.q);
  if (filters.page)     params.set('page', String(filters.page));
  if (filters.per_page) params.set('per_page', String(filters.per_page));
  const qs = params.toString();
  return qs ? `?${qs}` : '';
}

export const platformAdminApi = {
  // JORD-11: dashboard endpoint returns stats + by_status + recent so
  // the admin page can render more than counter tiles.
  dashboard:       () => request<{
    stats: DashboardStats;
    by_status?: Record<string, number>;
    // JORD-74: tightened from Array<Record<string, unknown>> so
    // consumers don't have to cast the payload to render the
    // "recent applications" list.
    recent?: Array<{
      id: number;
      reference_number: string;
      status: string;
      created_at: string;
      service_definition?: { name_ar?: string; name_en?: string } | null;
      applicant?: { name?: string } | null;
    }>;
  }>('GET', '/admin/dashboard'),

  // Legacy user CRUD kept here for back-compat; new code should use
  // `userManagementApi` from `api/users.ts`.
  listUsers:       () => request<{ users: User[] }>('GET', '/admin/users'),
  createUser:      (data: unknown) => request<{ user: User }>('POST', '/admin/users', data),
  updateUser:      (id: number, data: unknown) => request<{ user: User }>('PUT', `/admin/users/${id}`, data),

  /**
   * Legacy status-only listing. Kept for the AdminDashboard "recent
   * applications" widget until it migrates to the paginated variant.
   */
  allApplications: (status?: string) =>
    request<{ data: Application[] }>('GET', `/admin/applications${status ? `?status=${status}` : ''}`),

  /**
   * JORD-35: paginated + searchable listing. Backend returns a meta
   * envelope. Use with usePaginatedAdminApplications() in the JEA
   * hooks file.
   */
  allApplicationsPaginated: (filters: AllApplicationsFilters) =>
    request<Paginated<Application>>('GET', `/admin/applications${toQuery(filters)}`),

  auditLogs: () => request<{ data: unknown[] }>('GET', '/admin/audit-logs'),
};
