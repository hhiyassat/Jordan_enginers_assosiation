import type { User } from '../types';
import { request } from './http';

/**
 * User-management domain — superuser CRUD over the organization's users.
 * Split out of client.ts (JORD-22).
 */
export const userManagementApi = {
  list:   () => request<{ users: User[] }>('GET', '/admin/users'),
  create: (data: { name: string; email: string; password: string; role: User['role']; phone?: string }) =>
    request<{ user: User }>('POST', '/admin/users', data),
  update: (id: number, data: Partial<{ name: string; email: string; role: User['role']; is_active: boolean; password: string }>) =>
    request<{ user: User }>('PUT', `/admin/users/${id}`, data),
  destroy: (id: number) =>
    request<{ message: string }>('DELETE', `/admin/users/${id}`),
};
