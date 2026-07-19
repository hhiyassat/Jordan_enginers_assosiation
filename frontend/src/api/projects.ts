import type { Project } from '../types';
import { request } from './http';

/**
 * Projects domain — office project registry + per-office / per-engineer
 * quota consumption. Split out of client.ts (JORD-22).
 */

/** Per-engineer quota row (returned inside OfficeQuota.engineers). */
export interface EngineerQuota {
  engineer_id: number;
  engineer_name_ar: string;
  year: number;
  quota_m2: number | null;
  used_m2: number;
  remaining_m2: number | null;
  percent_used: number | null;
  projects_count: number;
  unlimited: boolean;
}

export interface OfficeQuota {
  year: number;
  totals: {
    quota_m2: number | null;
    used_m2: number;
    remaining_m2: number | null;
    percent_used: number | null;
    projects_count: number;
    unlimited: boolean;
    engineers_count: number;
  };
  engineers: EngineerQuota[];
}

export const projectsApi = {
  list:   () => request<{ projects: Project[] }>('GET', '/projects'),
  get:    (id: number) => request<{ project: Project }>('GET', `/projects/${id}`),
  create: (data: Partial<Pick<Project, 'name_ar' | 'name_en' | 'type' | 'area_m2' | 'city' | 'contract_no'>> & { engineer_id: number }) =>
    request<{ project: Project }>('POST', '/projects', data),
  quota:  () => request<OfficeQuota>('GET', '/projects/quota'),
};
