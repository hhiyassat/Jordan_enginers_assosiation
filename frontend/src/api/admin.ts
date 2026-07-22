// api/admin.ts — back-compat barrel (Workstream 6).
//
// The 374-line god module was split into api/platform/admin.ts +
// api/jea/admin.ts. This barrel re-exports both under the original
// `adminApi` name + preserves the `AllApplicationsFilters` and
// `Paginated<T>` type exports, so every existing consumer keeps
// working unchanged:
//
//   import { adminApi } from '../../api/client';
//   adminApi.dashboard();          // → platformAdminApi
//   adminApi.listServices();       // → jeaAdminApi
//   import type { Paginated }      // → from platform side
//     from '../../api/admin';
//
// Prefer the domain files directly for new code:
//   import { platformAdminApi } from '../../api/platform/admin';
//   import { jeaAdminApi }      from '../../api/jea/admin';

import { jeaAdminApi }      from './jea/admin';
import { platformAdminApi } from './platform/admin';

// Type re-exports (unchanged public surface).
export type { AllApplicationsFilters, Paginated } from './platform/admin';

/**
 * Legacy combined `adminApi` — the pre-split shape. New code should
 * import `platformAdminApi` / `jeaAdminApi` directly; this stays for
 * back-compat with existing consumers until Workstream 8 folds the
 * frontend into its `modules/` folders and every consumer moves.
 */
export const adminApi = {
  ...platformAdminApi,
  ...jeaAdminApi,
};
