// Platform-neutral types (Workstream 6 extraction from types/index.ts).
//
// These types describe the reusable core the platform ships with:
// tenants, users, and the shared org-level dashboard shape. Nothing
// here mentions a specific business domain — a non-JEA tenant would
// inherit exactly this set.
//
// JEA-specific types live in `./jea`. The `./index.ts` barrel
// re-exports both so `import { User } from '../../types'` keeps
// working unchanged for every consumer.

/**
 * Organization — a tenant. Every scoped model on the platform is
 * `where('organization_id', ...)`-filtered against this row.
 */
export interface Organization {
  id: number;
  name_ar: string;
  name_en: string;
  slug: string;
}

/**
 * User — a platform identity. Role vocabulary is intentionally small
 * (`applicant | staff | auditor | admin | superuser`) and generic to
 * the platform's RBAC. Service modules should never add to this
 * union directly — they read the current user through auth and gate
 * on `role` OR carry their own per-service permission model.
 */
export interface User {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  role: 'applicant' | 'staff' | 'auditor' | 'admin' | 'superuser';
  organization_id: number;
  // Present on /auth/me and /auth/login payloads. When true the SPA routes
  // the user to /auth/change-credentials before anything else.
  must_change_password?: boolean;
  // True only for the superuser role. Drives the user-management nav link.
  can_manage_users?: boolean;
  is_active?: boolean;
  created_at?: string;
  // JORD-24: server-computed presence bucket. Populated by the
  // /admin/users list; absent on other user payloads.
  presence?: 'online' | 'idle' | 'offline';
  last_seen_at?: string | null;
}
