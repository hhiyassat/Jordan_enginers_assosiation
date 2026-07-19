// ESP v2 — API client (barrel).
//
// JORD-22: what used to live in this 421-line dumping ground has been
// split into per-domain files under src/api/*.ts. Existing consumers
// still import from `'./client'` / `'../../api/client'` — those paths
// resolve to this file and pick up the re-exports below, so no one
// downstream has to change their import in the same PR.
//
// New code should import directly from the domain module, e.g.
// `import { servicesApi } from '../../api/services'`. React-Query hooks
// live in `src/api/hooks.ts`.

export { setUnauthorizedHandler } from './http';
export type { ApiError } from './http';

export { authApi } from './auth';
export { userManagementApi } from './users';
export { servicesApi } from './services';
export { projectsApi } from './projects';
export type { EngineerQuota, OfficeQuota } from './projects';
export { engineersApi } from './engineers';
export { applicationsApi } from './applications';
export type { StageAction } from './applications';
export { reviewApi } from './review';
export { integrationApi } from './integration';
export type { IntegrationCycle } from './integration';
export { adminApi } from './admin';
export type { AllApplicationsFilters, Paginated } from './admin';
export { notificationsApi } from './notifications';
export type { NotificationRow } from './notifications';
