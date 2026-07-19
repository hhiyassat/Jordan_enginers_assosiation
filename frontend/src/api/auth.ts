import type { User } from '../types';
import { request } from './http';

/**
 * Auth domain — sign-in / who-am-I / sign-out / password change.
 *
 * Split out of the monolithic client.ts (JORD-22). Consumers still
 * import through `api/client` because that file now re-exports.
 */
export const authApi = {
  login: (email: string, password: string, captcha?: { id: string; answer: string }) =>
    request<{ token: string; user: User }>('POST', '/auth/login', {
      email,
      password,
      captcha_id:     captcha?.id,
      captcha_answer: captcha?.answer,
    }),
  me:     () => request<{ user: User }>('GET', '/auth/me'),
  logout: () => request<void>('POST', '/auth/logout'),
  changePassword: (current_password: string, password: string, password_confirmation: string, email?: string) =>
    request<{ message: string }>('POST', '/auth/password/change', {
      current_password, password, password_confirmation,
      ...(email ? { email } : {}),
    }),
};
