// ESP v2 — API client (barrel).
//
// JORD-22: what used to live in this 421-line dumping ground has been
// split into per-domain files under src/api/*.ts. Existing consumers
// still import from `'./client'` / `'../../api/client'` — those paths
// resolve to this file and pick up the re-exports below, so no one
// downstream has to change their import in the same PR.
//
// New code should import directly from the owning entity/feature, e.g.
// `import { servicesApi } from '../../entities/service'`. React-Query
// hooks live alongside each entity/feature's model.

export { setUnauthorizedHandler } from '../shared/api/http';
export type { ApiError } from '../shared/api/http';

export { authApi } from './auth';
export { userManagementApi } from '../entities/user/api';
export { servicesApi } from '../entities/service/api';
export { projectsApi } from './projects';
export type { EngineerQuota, OfficeQuota } from './projects';
export { engineersApi } from '../entities/engineer/api';
export { applicationsApi } from '../entities/application/api';
export type { StageAction } from '../entities/application/api';
export { reviewApi } from './review';
export type { ReviewDashboardResponse } from './review';
export { integrationApi } from './integration';
export type { IntegrationCycle } from './integration';
export { adminApi } from './admin';
export type { AllApplicationsFilters, Paginated } from './admin';
export { myOfficeApi } from './myOffice';
export type { MyDuesResponse, MyComplaint, MySanction } from './myOffice';
export { notificationsApi } from './notifications';
export type { NotificationRow } from './notifications';
