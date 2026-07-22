import type { Engineer } from '../types';
import { request } from './http';
import type { EngineerQuota } from './projects';

/**
 * Engineers domain — office roster + per-engineer quota lookup.
 * Split out of client.ts (JORD-22).
 */
export const engineersApi = {
  list:   () => request<{ engineers: Engineer[] }>('GET', '/engineers'),
  get:    (id: number) => request<{ engineer: Engineer }>('GET', `/engineers/${id}`),
  create: (data: Partial<Pick<Engineer, 'name_ar' | 'name_en' | 'membership_number' | 'specialization' | 'phone' | 'email' | 'annual_quota_m2'>>) =>
    request<{ engineer: Engineer }>('POST', '/engineers', data),
  quota:  (id: number) => request<EngineerQuota>('GET', `/engineers/${id}/quota`),
};
