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
  // JORD-84 (PM): /auth/me is now a public probe. Guest → {user: null}
  // with 200; authenticated → {user: <payload>}. Consumers must
  // null-check `user` before using it.
  me:     () => request<{ user: User | null }>('GET', '/auth/me'),
  logout: () => request<void>('POST', '/auth/logout'),
  changePassword: (current_password: string, password: string, password_confirmation: string, email?: string) =>
    request<{ message: string }>('POST', '/auth/password/change', {
      current_password, password, password_confirmation,
      ...(email ? { email } : {}),
    }),
  // JORD-10: user updates their own name / phone. Email + role stay
  // gated to the credential-change + admin flows respectively.
  updateProfile: (data: { name?: string; phone?: string | null }) =>
    request<{ user: User }>('PATCH', '/auth/me', data),
};
