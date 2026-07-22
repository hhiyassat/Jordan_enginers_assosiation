// api/hooks.ts — back-compat barrel (Workstream 6).
//
// The 222-line combined hook module was split into per-domain files.
// This barrel re-exports both so every existing
// `import { useMyApplications, useUsers } from '../api/hooks'` call
// keeps working unchanged.
//
// Prefer the domain files directly for new code:
//   import { useAdminDashboardStats } from '../../api/platform/hooks';
//   import { useMyApplications }       from '../../api/jea/hooks';
//
// Type re-exports (the pre-split file exported these for convenience):
import type { Application, ServiceDefinition, User } from '../types';

export * from './platform/hooks';
export * from './jea/hooks';

// Pre-split type re-exports so pages that pulled `Application` off
// this file still resolve.
export type { Application, ServiceDefinition, User };
